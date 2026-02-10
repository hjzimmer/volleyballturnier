"""
Flexible Finals - Unterstützt beliebige Team-Anzahlen
IDs werden dynamisch vergeben, keine festen IDs in der Config
"""

import json
from db import get_connection

def get_next_final_match_id(conn):
    """
    Gibt die nächste freie Match-ID für Final-Matches zurück.
    Finals beginnen direkt nach den letzten Gruppenmatches.
    """
    result = conn.execute("SELECT MAX(id) as max_id FROM matches WHERE phase = 'group'").fetchone()
    max_group_id = result['max_id'] if result['max_id'] else 0
    return max_group_id + 1


def resolve_team_ref(ref, conn, match_key_to_id=None):
    """
    Löst eine Team-Referenz auf.
    
    Unterstützte Formate:
    - "A_1" → Erster der Gruppe A
    - "B_2" → Zweiter der Gruppe B
    - "W_Halbfinale_1" → Gewinner des Matches mit key "Halbfinale_1"
    - "L_Halbfinale_1" → Verlierer des Matches mit key "Halbfinale_1"
    
    Args:
        ref: Team-Referenz String
        conn: Datenbankverbindung
        match_key_to_id: Optional - Mapping von match_key zu Match-ID
    
    Returns:
        Team-ID oder None
    """
    if not ref:
        return None
    
    # Gruppenplatzierung (z.B. A_1, B_2)
    if "_" in ref and ref[0] in ("A", "B") and not ref.startswith("W_") and not ref.startswith("L_"):
        group_name, position = ref.split("_")
        position = int(position)
        
        # Hole Team aus Gruppentabelle
        from standings import calculate_group_standings
        group_id = 1 if group_name == "A" else 2
        standings = calculate_group_standings(group_id)
        
        if position <= len(standings):
            return standings[position - 1]  # standings gibt Team-IDs zurück
        return None
    
    # Gewinner/Verlierer eines Matches mit match_key (z.B. W_Halbfinale_1)
    if ref.startswith("W_") or ref.startswith("L_"):
        match_key = ref[2:]  # Entferne W_ oder L_
        
        # Wenn wir ein match_key_to_id mapping haben, nutze das
        if match_key_to_id and match_key in match_key_to_id:
            match_id = match_key_to_id[match_key]
        else:
            # Versuche match_key über round-Namen zu finden
            result = conn.execute(
                "SELECT id FROM matches WHERE phase = 'final' AND round = ?",
                (match_key.replace('_', ' '),)
            ).fetchone()
            
            if not result:
                return None
            match_id = result['id']
        
        result = conn.execute(
            "SELECT winner_id, loser_id FROM matches WHERE id = ?",
            (match_id,)
        ).fetchone()
        
        if result:
            return result[0] if ref.startswith("W_") else result[1]
        return None
    
    return None


def create_final_matches(config_path, group_tables=None):
    """
    Erstellt Final-Matches aus flexibler Konfiguration.
    IDs werden automatisch vergeben basierend auf Anzahl Gruppenmatches.
    
    Config-Format (final_config.json):
    {
      "matches": [
        {
          "round": "Halbfinale 1",
          "match_key": "Halbfinale_1",
          "team1": "A_1",
          "team2": "B_2",
          "winner_placement": null,
          "loser_placement": null
        }
      ]
    }
    
    Args:
        config_path: Pfad zur Config-Datei
        group_tables: Optional - wird ignoriert, für Kompatibilität
    
    Returns:
        Dictionary mit Mapping von match_key zu Match-ID
    """
    with open(config_path, "r", encoding="utf-8") as f:
        config = json.load(f)

    conn = get_connection()
    
    # Hole nächste freie Match-ID
    next_id = get_next_final_match_id(conn)
    
    # Speichere Mapping von match_key zu tatsächlicher ID
    match_key_to_id = {}
    
    print(f"Erstelle {len(config['matches'])} Final-Matches (Start-ID: {next_id})...")
    
    for idx, m in enumerate(config["matches"]):
        match_id = next_id + idx
        match_key = m.get("match_key", m["round"])  # Falls match_key fehlt, nutze round
        
        match_key_to_id[match_key] = match_id
        
        winner_placement = m.get("winner_placement")
        loser_placement = m.get("loser_placement")
        
        print(f"  #{match_id}: {m['round']} (Key: {match_key})")
        
        conn.execute(
            '''
            INSERT INTO matches
            (id, phase, group_id, round, team1_ref, team2_ref, referee_team_id, 
             winner_placement, loser_placement)
            VALUES (?, 'final', NULL, ?, ?, ?, 0, ?, ?)
            ''',
            (match_id, m["round"], m["team1"], m["team2"], 
             winner_placement, loser_placement)
        )

    conn.commit()
    conn.close()
    
    print(f"✅ Final-Matches erstellt (IDs: {next_id} bis {next_id + len(config['matches']) - 1})")
    return match_key_to_id


def update_match_result(match_id, winner_id, loser_id):
    """
    Speichert das Ergebnis eines Matches (Gewinner und Verlierer).
    
    Args:
        match_id: ID des Matches
        winner_id: Team-ID des Gewinners
        loser_id: Team-ID des Verlierers
    """
    conn = get_connection()
    conn.execute(
        "UPDATE matches SET winner_id = ?, loser_id = ?, finished = 1 WHERE id = ?",
        (winner_id, loser_id, match_id)
    )
    conn.commit()
    conn.close()
    print(f"Match {match_id} Ergebnis gespeichert: Gewinner={winner_id}, Verlierer={loser_id}")


def get_resolved_match(match_id):
    """
    Gibt ein Match mit aufgelösten Team-IDs zurück.
    
    Wenn team1_id/team2_id bereits gesetzt sind, werden diese verwendet.
    Sonst werden die Referenzen (team1_ref/team2_ref) aufgelöst.
    
    Args:
        match_id: ID des Matches
    
    Returns:
        Dictionary mit Match-Daten oder None wenn nicht gefunden
    """
    conn = get_connection()
    match = conn.execute(
        "SELECT * FROM matches WHERE id = ?",
        (match_id,)
    ).fetchone()
    
    if not match:
        conn.close()
        return None
    
    # Wenn team1_id/team2_id schon gesetzt sind, nutze diese
    team1_id = match['team1_id'] if match['team1_id'] else resolve_team_ref(match['team1_ref'], conn)
    team2_id = match['team2_id'] if match['team2_id'] else resolve_team_ref(match['team2_ref'], conn)
    
    conn.close()
    
    return {
        'id': match['id'],
        'phase': match['phase'],
        'round': match['round'],
        'team1_id': team1_id,
        'team2_id': team2_id,
        'team1_ref': match['team1_ref'],
        'team2_ref': match['team2_ref'],
        'start_time': match['start_time'],
        'finished': match['finished']
    }

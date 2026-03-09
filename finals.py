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


def resolve_team_ref(ref, conn, group_tables=None, match_id_map=None):
    """
    Löst eine Team-Referenz auf.
    
    Unterstützte Formate:
    - "G1_1" → Erster der Gruppe mit ID G1
    - "Z2_3" → Dritter der Gruppe mit ID Z2
    - "W_M1" → Gewinner des Matches mit ID M1
    - "L_M2" → Verlierer des Matches mit ID M2
    
    Args:
        ref: Team-Referenz String
        conn: Datenbankverbindung
        group_tables: Dictionary mit Gruppentabellen (group_id -> Standings)
        match_id_map: Mapping von match-id (Config) zu DB-Match-ID
    
    Returns:
        Team-ID oder None
    """
    if not ref:
        return None
    
    # Gruppenplatzierung (z.B. G1_1, Z2_3)
    if "_" in ref and not ref.startswith("W_") and not ref.startswith("L_"):
        parts = ref.split("_")
        if len(parts) == 2:
            group_id, position = parts
            position = int(position)
            
            # Nutze group_tables falls vorhanden
            if group_tables and group_id in group_tables:
                standings = group_tables[group_id]
                if position <= len(standings):
                    return standings[position - 1]  # standings gibt Team-IDs zurück
            return None
    
    # Gewinner/Verlierer eines Matches mit match-id (z.B. W_M1, L_M2)
    if ref.startswith("W_") or ref.startswith("L_"):
        match_key = ref[2:]  # Entferne W_ oder L_
        
        # Wenn wir ein match_id_map haben, nutze das
        if match_id_map and match_key in match_id_map:
            match_id = match_id_map[match_key]
        else:
            # Versuche match über die ID in der Datenbank zu finden (falls schon gespeichert)
            return None
        
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
    Erstellt Matches aus allen Phasen mit einem matches-Array.
    IDs werden automatisch vergeben basierend auf Anzahl bestehender Matches.
    
    Args:
        config_path: Pfad zur Config-Datei
        group_tables: Dictionary mit Gruppentabellen (group_id -> Standings)
    
    Returns:
        Dictionary mit Mapping von match-id zu Match-ID in DB
    """
    with open(config_path, "r", encoding="utf-8") as f:
        config = json.load(f)

    conn = get_connection()
    
    # Hole nächste freie Match-ID
    next_id = get_next_final_match_id(conn)
    
    # Speichere Mapping von match-id (aus Config) zu tatsächlicher DB-ID
    match_id_map = {}
    
    phases = config.get("phases", [])
    all_matches = []
    
    # Sammle alle Matches aus allen Phasen mit einem "matches"-Array
    for phase in phases:
        if "matches" in phase:
            for m in phase["matches"]:
                all_matches.append({
                    "phase_name": phase["name"],
                    "match": m
                })
    
    print(f"Erstelle {len(all_matches)} Matches (Start-ID: {next_id})...")
    
    for idx, item in enumerate(all_matches):
        m = item["match"]
        team1 = m.get("team1")
        team2 = m.get("team2")
        # Prüfe, ob es ein echtes Spiel ist (mindestens ein Team vorhanden)
        is_real_match = (team1 is not None or team2 is not None)
        if not is_real_match:
            # Kein echtes Spiel, aber Mapping für Platzierungslogik anlegen
            match_key = m.get("id", m.get("name", f"Match_{idx}"))
            match_id_map[match_key] = None
            print(f"  [Kein echtes Spiel] {m.get('name', 'Unbenannt')} (ID: {match_key})")
            continue

        match_id = next_id
        next_id += 1
        match_key = m.get("id", m.get("name", f"Match_{idx}"))
        match_id_map[match_key] = match_id

        winner_placement = m.get("winner_placement")
        loser_placement = m.get("loser_placement")

        print(f"  #{match_id}: {m.get('name', 'Unbenannt')} (ID: {match_key})")

        import json as _json
        # Falls dict, als JSON-String speichern
        if isinstance(team1, dict):
            team1_ref = _json.dumps(team1, ensure_ascii=False)
            team1_id = -1
        elif isinstance(team1, int):
            team1_ref = None
            team1_id = team1
        else:
            team1_ref = team1 if isinstance(team1, str) else None
            team1_id = -1

        if isinstance(team2, dict):
            team2_ref = _json.dumps(team2, ensure_ascii=False)
            team2_id = -1
        elif isinstance(team2, int):
            team2_ref = None
            team2_id = team2
        else:
            team2_ref = team2 if isinstance(team2, str) else None
            team2_id = -1

        conn.execute(
            '''
            INSERT INTO matches
            (id, phase, group_id, round, team1_id, team2_id, team1_ref, team2_ref, referee_team_id, 
             winner_placement, loser_placement)
            VALUES (?, 'final', ?, ?, ?, ?, ?, ?, 0, ?, ?)
            ''' ,
            (match_id, m.get("id"), m.get("name", "Unbenannt"), team1_id, team2_id, team1_ref, team2_ref, 
             winner_placement, loser_placement)
        )

    conn.commit()
    conn.close()
    
    print(f"[OK] Matches erstellt (IDs: {next_id} bis {next_id + len(all_matches) - 1})")
    return match_id_map


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

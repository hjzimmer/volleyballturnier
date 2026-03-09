"""
Hilfsroutine zum automatischen Füllen der Gruppenspiele mit Zufallsergebnissen.
Nützlich zum Testen der Turnierfunktionalität.

Verwendung:
    python fill_group_results.py
"""

import random
import json
from db import get_connection


def load_config():
    """
    Lädt die Turnierkonfiguration.
    
    Returns:
        dict: Konfigurationswerte
    """
    with open('data/turnier_config.json', 'r', encoding='utf-8') as f:
        return json.load(f)


def generate_set_score():
    """
    Generiert ein Satzergebnis mit Punkten zwischen 5 und 10.
    Unentschieden sind möglich.
    
    Returns:
        tuple: (punkte_team1, punkte_team2)
    """
    team1_points = random.randint(5, 9)
    team2_points = random.randint(5, 9)
    
    return team1_points, team2_points


def fill_group_matches_with_results(group_id=None):
    """
    Füllt alle Gruppenspiele oder die einer bestimmten Gruppe mit Zufallsergebnissen.
    group_id: Optional, wenn angegeben, wird nur diese Gruppe gefüllt.
    """
    config = load_config()
    sets_per_match = config.get('sets_per_match', 2)
    conn = get_connection()
    filled_count = 0
    # Wenn group_id angegeben, nur diese Gruppe füllen
    if group_id:
        group_ids = [group_id]
        phase_name = None
        # Für Info-Ausgabe: Phasenname suchen
        for phase in config.get('phases', []):
            if 'groups' in phase:
                for group in phase['groups']:
                    if group['id'] == group_id:
                        phase_name = phase.get('name', phase.get('id', '?'))
                        break
        print(f"\n[INFO] Fülle Gruppe {group_id}{' (Phase: '+phase_name+')' if phase_name else ''}...")
    else:
        # Alle Gruppen aller Phasen
        group_ids = []
        for phase in config.get('phases', []):
            if 'groups' in phase:
                for group in phase['groups']:
                    group_ids.append(group['id'])
    for gid in group_ids:
        matches = conn.execute("""
            SELECT id, team1_id, team2_id, round
            FROM matches
            WHERE phase = 'group' AND group_id = ? AND finished = 0 AND team1_id != -1 AND team2_id != -1
            ORDER BY id
        """, (gid,)).fetchall()
        for match in matches:
            match_id = match['id']
            team1_id = match['team1_id']
            team2_id = match['team2_id']
            round_name = match['round']
            set_results = [generate_set_score() for _ in range(sets_per_match)]
            team1_wins = sum(1 for s1, s2 in set_results if s1 > s2)
            team2_wins = sum(1 for s1, s2 in set_results if s2 > s1)
            if team1_wins > team2_wins:
                winner_id = team1_id
                loser_id = team2_id
            elif team2_wins > team1_wins:
                winner_id = team2_id
                loser_id = team1_id
            else:
                winner_id = None
                loser_id = None
            conn.execute("DELETE FROM sets WHERE match_id = ?", (match_id,))
            for set_number, (set_team1, set_team2) in enumerate(set_results, start=1):
                conn.execute("""
                    INSERT INTO sets (match_id, set_number, team1_points, team2_points)
                    VALUES (?, ?, ?, ?)
                """, (match_id, set_number, set_team1, set_team2))
            conn.execute("""
                UPDATE matches
                SET finished = 1, winner_id = ?, loser_id = ?
                WHERE id = ?
            """, (winner_id, loser_id, match_id))
            conn.commit()
            team1_name = conn.execute("SELECT name FROM teams WHERE id = ?", (team1_id,)).fetchone()['name']
            team2_name = conn.execute("SELECT name FROM teams WHERE id = ?", (team2_id,)).fetchone()['name']
            score_display = ', '.join([f"{t1}:{t2}" for t1, t2 in set_results])
            print(f"[OK] Match #{match_id} ({round_name}): {team1_name} {score_display} {team2_name}")
            if winner_id is None:
                print(f"  -> Unentschieden (1:1 Satzpunkte)")
            else:
                winner_name = conn.execute("SELECT name FROM teams WHERE id = ?", (winner_id,)).fetchone()['name']
                print(f"  -> Gewinner: {winner_name}")
            filled_count += 1
        # Nach jeder Gruppe: Standings berechnen und Platzierungen auflösen
        print(f"[INFO] Aktualisiere Platzierungen für Gruppe {gid}...")
        update_group_positions_in_matches_with_ref(conn)
    conn.close()
    print(f"\n[OK] {filled_count} Gruppenspiele erfolgreich mit Ergebnissen gefüllt!")
    print(f"[INFO] Die Gruppenplatzierungen wurden in die Finalspiele übertragen.")
    print(f"[INFO] Überprüfe die Ergebnisse in der Web-Oberfläche (groups.php, bracket.php)")


def clear_all_group_results():
    """
    Löscht alle Gruppenspiel-Ergebnisse (hilfreich für Neustarts).
    """
    conn = get_connection()
    
    # Zähle betroffene Matches
    count = conn.execute("""
        SELECT COUNT(*) as cnt FROM matches 
        WHERE phase = 'group' AND finished = 1
    """).fetchone()['cnt']
    
    if count == 0:
        print("[INFO] Keine Ergebnisse zum Löschen vorhanden.")
        conn.close()
        return
    
    # Lösche alle Sets von Gruppenspielen
    conn.execute("""
        DELETE FROM sets 
        WHERE match_id IN (SELECT id FROM matches WHERE phase = 'group')
    """)
    
    # Setze alle Gruppenspiele zurück
    conn.execute("""
        UPDATE matches 
        SET finished = 0, winner_id = NULL, loser_id = NULL
        WHERE phase = 'group'
    """)
    # setze team ids zurück für Gruppenspiele mit team refs
    conn.execute("""
        UPDATE matches
        SET team1_id = -1, team2_id = -1, finished = 0, winner_id = NULL, loser_id = NULL
        WHERE phase = 'group' 
          AND (team1_ref IS NOT NULL OR team2_ref IS NOT NULL) 
    """)
    
    # Setze auch alle Finalspiele mit Gruppenreferenzen zurück
    conn.execute("""
        UPDATE matches
        SET team1_id = -1, team2_id = -1, finished = 0, winner_id = NULL, loser_id = NULL, referee_team_id = NULL
        WHERE phase = 'final' 
          AND (team1_ref IS NOT NULL OR team2_ref IS NOT NULL) 
    """)
    
    # Lösche auch die Sets von diesen Finalspielen
    conn.execute("""
        DELETE FROM sets 
        WHERE match_id IN (
            SELECT id FROM matches 
            WHERE phase = 'final' 
              AND (team1_ref IS NOT NULL OR team2_ref IS NOT NULL)
        )
    """)
    
    conn.commit()
    conn.close()
    
    print(f"[OK] {count} Gruppenspiel-Ergebnisse gelöscht.")
    print(f"[OK] Alle Gruppenspiele sind jetzt wieder offen.")
    print(f"[OK] Finalspiele mit Gruppenreferenzen wurden zurückgesetzt.")


def get_group_standing_team(conn, group_id, position):
    """
    Berechnet die Gruppentabelle und gibt die Team-ID an der gewünschten Position zurück.
    
    Args:
        conn: Datenbankverbindung
        group_id: ID der Gruppe 
        position: Gewünschte Position (1 = Platz 1, 2 = Platz 2, etc.)
        
    Returns:
        int: Team-ID oder None wenn Position nicht existiert
    """
    # Hole alle Teams der Gruppe
    # Für Vorrunde: group_teams, für Zwischen-/Finalrunden: Teams aus beendeten Matches
    team_ids = set()
    # 1. Versuche reguläre Zuordnung (group_teams)
    rows = conn.execute("SELECT team_id FROM group_teams WHERE group_id = ? AND team_id != -1 ORDER BY team_id", (group_id,)).fetchall()
    team_ids.update(row['team_id'] for row in rows)
    # 2. Falls keine festen Teams, nimm alle Teams aus beendeten Matches dieser Gruppe
    if not team_ids:
        rows = conn.execute("""
            SELECT DISTINCT team1_id as tid FROM matches WHERE group_id = ? AND team1_id != -1 AND finished = 1
            UNION
            SELECT DISTINCT team2_id as tid FROM matches WHERE group_id = ? AND team2_id != -1 AND finished = 1
        """, (group_id, group_id)).fetchall()
        team_ids.update(row['tid'] for row in rows)
    team_ids = list(team_ids)
    standings = {}
    for team_id in team_ids:
        standings[team_id] = {
            'team_id': team_id,
            'points': 0,           # Satzpunkte
            'sets_won': 0,
            'sets_lost': 0,
            'points_scored': 0,
            'points_conceded': 0,
            'point_diff': 0
        }
    
    # Hole alle Sätze der beendeten Gruppenspiele
    sets = conn.execute("""
        SELECT m.id as match_id, m.team1_id, m.team2_id,
               s.set_number, s.team1_points, s.team2_points
        FROM matches m
        JOIN sets s ON s.match_id = m.id
        WHERE m.group_id = ? AND m.phase = 'group' AND m.finished = 1
        ORDER BY m.id, s.set_number
    """, (group_id,)).fetchall()
    
    for set_row in sets:
        t1 = set_row['team1_id']
        t2 = set_row['team2_id']
        p1 = set_row['team1_points']
        p2 = set_row['team2_points']
        
        standings[t1]['points_scored'] += p1
        standings[t1]['points_conceded'] += p2
        standings[t2]['points_scored'] += p2
        standings[t2]['points_conceded'] += p1
        
        # Satzpunkte vergeben
        if p1 > p2:
            standings[t1]['points'] += 2
            standings[t1]['sets_won'] += 1
            standings[t2]['sets_lost'] += 1
        elif p2 > p1:
            standings[t2]['points'] += 2
            standings[t2]['sets_won'] += 1
            standings[t1]['sets_lost'] += 1
        else:
            standings[t1]['points'] += 1
            standings[t2]['points'] += 1
    
    # Punktdifferenz berechnen
    for team_id in standings:
        standings[team_id]['point_diff'] = standings[team_id]['points_scored'] - standings[team_id]['points_conceded']
    
    # Sortiere nach: 1. Satzpunkte, 2. Punktdifferenz, 3. Erzielte Punkte, 4. Gewonnene Sätze
    standings_list = sorted(
        standings.values(),
        key=lambda x: (
            -x['points'],          # 1. Satzpunkte (absteigend)
            -x['sets_won'],        # 2. Gewonnene Sätze (absteigend)
            -x['point_diff'],      # 3. Punktdifferenz (absteigend)
            -x['points_scored']    # 4. Erzielte Punkte (absteigend)
        )
    )
    
    # Rückgabe des Teams an der gewünschten Position
    if 0 < position <= len(standings_list):
        return standings_list[position - 1]['team_id']
    
    return None


def update_group_positions_in_matches_with_ref(conn):
    """
    Aktualisiert alle Spiele mit Gruppenreferenzen .
    Wenn NICHT alle Gruppenspiele abgeschlossen sind, werden die Finalspiele zurückgesetzt.
    
    Args:
        conn: Datenbankverbindung
    """
    import json
    # Prüfe ob ALLE Gruppenspiele abgeschlossen sind
    group_match_count = conn.execute("SELECT COUNT(*) as cnt FROM matches WHERE phase = 'group'").fetchone()['cnt']
    finished_group_match_count = conn.execute("SELECT COUNT(*) as cnt FROM matches WHERE phase = 'group' AND finished = 1").fetchone()['cnt']
#    all_group_matches_finished = (group_match_count > 0 and group_match_count == finished_group_match_count)
    # Finde alle Matches mit Platzhalter-Teams (team_id = -1, team_ref nicht NULL)
    placeholder_matches = conn.execute("""
        SELECT id, team1_ref, team2_ref, team1_id, team2_id, round, phase, group_id
        FROM matches
        WHERE (team1_id = -1 OR team2_id = -1)
          AND (team1_ref IS NOT NULL OR team2_ref IS NOT NULL)
    """).fetchall()
    updated_count = 0
    reset_count = 0
    for match in placeholder_matches:
        new_team1_id = match['team1_id']
        new_team2_id = match['team2_id']
        # team1_ref prüfen
        if match['team1_ref']:
            try:
                ref1 = json.loads(match['team1_ref']) if match['team1_ref'].startswith('{') else match['team1_ref']
            except Exception:
                ref1 = match['team1_ref']
            if isinstance(ref1, dict) and ref1.get('type') == 'group_place':
                group_id = ref1['group']
                place = ref1['place']
                # Prüfe, ob alle Gruppenspiele dieser Gruppe fertig sind
                group_match_count = conn.execute("SELECT COUNT(*) as cnt FROM matches WHERE phase = 'group' AND group_id = ?", (group_id,)).fetchone()['cnt']
                finished_group_match_count = conn.execute("SELECT COUNT(*) as cnt FROM matches WHERE phase = 'group' AND group_id = ? AND finished = 1", (group_id,)).fetchone()['cnt']
                if group_match_count > 0 and group_match_count == finished_group_match_count:
                    new_team1_id = get_group_standing_team(conn, group_id, int(place))
                else:
                    new_team1_id = -1
        # team2_ref prüfen
        if match['team2_ref']:
            try:
                ref2 = json.loads(match['team2_ref']) if match['team2_ref'].startswith('{') else match['team2_ref']
            except Exception:
                ref2 = match['team2_ref']
            if isinstance(ref2, dict) and ref2.get('type') == 'group_place':
                group_id = ref2['group']
                place = ref2['place']
                group_match_count = conn.execute("SELECT COUNT(*) as cnt FROM matches WHERE phase = 'group' AND group_id = ?", (group_id,)).fetchone()['cnt']
                finished_group_match_count = conn.execute("SELECT COUNT(*) as cnt FROM matches WHERE phase = 'group' AND group_id = ? AND finished = 1", (group_id,)).fetchone()['cnt']
                if group_match_count > 0 and group_match_count == finished_group_match_count:
                    new_team2_id = get_group_standing_team(conn, group_id, int(place))
                else:
                    new_team2_id = -1
        # Aktualisiere nur, wenn sich etwas geändert hat
        if (new_team1_id != match['team1_id']) or (new_team2_id != match['team2_id']):
            conn.execute("UPDATE matches SET team1_id = ?, team2_id = ? WHERE id = ?", (new_team1_id, new_team2_id, match['id']))
            updated_count += 1
            # Ausgabe
            team1_name = conn.execute("SELECT name FROM teams WHERE id = ?", (new_team1_id,)).fetchone() if new_team1_id and new_team1_id != -1 else None
            team2_name = conn.execute("SELECT name FROM teams WHERE id = ?", (new_team2_id,)).fetchone() if new_team2_id and new_team2_id != -1 else None
            print(f"  -> Match #{match['id']} ({match['round']}): "
                  f"{team1_name['name'] if team1_name else 'TBD'} vs {team2_name['name'] if team2_name else 'TBD'}")
    conn.commit()
    if updated_count > 0:
        print(f"[OK] {updated_count} Matches mit Platzierungen aktualisiert.")
    else:
        print(f"[INFO] Keine Matches mit Platzhalter-Teams zu aktualisieren.")


if __name__ == "__main__":
    print("=" * 60)
    print("GRUPPENSPIEL-ERGEBNIS GENERATOR")
    print("=" * 60)
    print()
    while True:
        print("Wähle eine Option:")
        print("  [1] Alle Gruppenspiele mit Zufallsergebnissen füllen")
        print("  [2] Einzelne Gruppe füllen")
        print("  [3] Alle Gruppenspiel-Ergebnisse löschen")
        print("  [q] Beenden")
        print()

        choice = input("Deine Wahl: ").strip().lower()

        if choice == "1":
            print()
            fill_group_matches_with_results()
        elif choice == "2":
            # Einzelne Gruppe füllen
            config = load_config()
            conn = get_connection()
            group_options = []
            print("Verfügbare Gruppen zum Füllen:")
            for phase in config.get('phases', []):
                if 'groups' not in phase:
                    continue
                for group in phase['groups']:
                    group_id = group['id']
                    teams = group.get('teams', [])
                    # Vorrunde: feste Teams
                    if all(isinstance(t, int) for t in teams):
                        matches = conn.execute("""
                            SELECT COUNT(*) as cnt FROM matches
                            WHERE phase = 'group' AND group_id = ? AND finished = 0 AND team1_id != -1 AND team2_id != -1
                        """, (group_id,)).fetchone()['cnt']
                        if matches > 0:
                            group_options.append((group_id, group['name']))
                    # Gruppen mit Referenzen: nur anbieten, wenn alle Team-Referenzen aufgelöst wurden
                    elif all(isinstance(t, dict) for t in teams):
                        # Prüfe, ob alle Matches der Gruppe konkrete Teams haben
                        unresolved = conn.execute("""
                            SELECT COUNT(*) as cnt FROM matches
                            WHERE phase = 'group' AND group_id = ? AND (team1_id = -1 OR team2_id = -1)
                        """, (group_id,)).fetchone()['cnt']
                        open_matches = conn.execute("""
                            SELECT COUNT(*) as cnt FROM matches
                            WHERE phase = 'group' AND group_id = ? AND finished = 0 AND team1_id != -1 AND team2_id != -1
                        """, (group_id,)).fetchone()['cnt']
                        if unresolved == 0 and open_matches > 0:
                            group_options.append((group_id, group['name']))
            if not group_options:
                print("[INFO] Keine Gruppe mit konkreten Teams und offenen Matches verfügbar.")
            else:
                for idx, (gid, gname) in enumerate(group_options, 1):
                    print(f"  [{idx}] {gname} ({gid})")
                sel = input("Gruppe wählen (Nummer): ").strip()
                try:
                    sel_idx = int(sel) - 1
                    if 0 <= sel_idx < len(group_options):
                        group_id = group_options[sel_idx][0]
                        print()
                        fill_group_matches_with_results(group_id=group_id)
                    else:
                        print("[FEHLER] Ungültige Auswahl.")
                except Exception:
                    print("[FEHLER] Ungültige Eingabe.")
            conn.close()
        elif choice == "3":
            print()
            confirm = input("[WARNUNG] Wirklich alle Gruppenspiel-Ergebnisse löschen? (ja/nein): ").strip().lower()
            if confirm in ['ja', 'j', 'yes', 'y']:
                print()
                clear_all_group_results()
            else:
                print("[ABBRUCH] Abgebrochen.")
        elif choice == "q":
            print("Tschüss!")
            break
        else:
            print("[FEHLER] Ungültige Eingabe.")
        print()

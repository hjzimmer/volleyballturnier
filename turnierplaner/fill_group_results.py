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
    with open('turnier_config.json', 'r', encoding='utf-8') as f:
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


def fill_group_matches_with_results():
    """
    Füllt alle Gruppenspiele mit Zufallsergebnissen.
    Die Anzahl der Sätze wird aus der Konfiguration gelesen.
    """
    # Lade Konfiguration
    config = load_config()
    sets_per_match = config.get('sets_per_match', 2)
    
    conn = get_connection()
    
    # Hole alle Gruppenspiele, die noch nicht beendet sind
    matches = conn.execute("""
        SELECT id, team1_id, team2_id, round
        FROM matches
        WHERE phase = 'group' AND finished = 0
        ORDER BY id
    """).fetchall()
    
    if len(matches) == 0:
        print("[WARNUNG] Keine offenen Gruppenspiele gefunden.")
        print("   Entweder sind alle Spiele bereits ausgefüllt oder die DB ist leer.")
        conn.close()
        return
    
    print(f"[INFO] Fülle {len(matches)} Gruppenspiele mit Zufallsergebnissen ({sets_per_match} Satz/Sätze pro Spiel)...\n")
    
    filled_count = 0
    
    for match in matches:
        match_id = match['id']
        team1_id = match['team1_id']
        team2_id = match['team2_id']
        round_name = match['round']
        
        # Generiere Sätze dynamisch
        set_results = []
        for i in range(sets_per_match):
            set_results.append(generate_set_score())
        
        # Bestimme Gewinner (wer mehr Sätze gewonnen hat)
        team1_wins = 0
        team2_wins = 0
        
        # Zähle Satzgewinne (Unentschieden werden nicht gezählt)
        for set_team1, set_team2 in set_results:
            if set_team1 > set_team2:
                team1_wins += 1
            elif set_team2 > set_team1:
                team2_wins += 1
        
        # Bestimme Gewinner/Verlierer (None bei Unentschieden)
        if team1_wins > team2_wins:
            winner_id = team1_id
            loser_id = team2_id
        elif team2_wins > team1_wins:
            winner_id = team2_id
            loser_id = team1_id
        else:
            # Unentschieden - keine Gewinner
            winner_id = None
            loser_id = None
        
        # Lösche evtl. vorhandene alte Ergebnisse
        conn.execute("DELETE FROM sets WHERE match_id = ?", (match_id,))
        
        # Speichere Sätze dynamisch
        for set_number, (set_team1, set_team2) in enumerate(set_results, start=1):
            conn.execute("""
                INSERT INTO sets (match_id, set_number, team1_points, team2_points)
                VALUES (?, ?, ?, ?)
            """, (match_id, set_number, set_team1, set_team2))
        
        # Markiere Match als beendet und setze Gewinner/Verlierer
        conn.execute("""
            UPDATE matches
            SET finished = 1, winner_id = ?, loser_id = ?
            WHERE id = ?
        """, (winner_id, loser_id, match_id))
        
        conn.commit()
        
        # Hole Teamnamen für Ausgabe
        team1_name = conn.execute("SELECT name FROM teams WHERE id = ?", (team1_id,)).fetchone()['name']
        team2_name = conn.execute("SELECT name FROM teams WHERE id = ?", (team2_id,)).fetchone()['name']
        
        # Formatiere Ergebnisanzeige
        score_display = ', '.join([f"{t1}:{t2}" for t1, t2 in set_results])
        print(f"[OK] Match #{match_id} ({round_name}): {team1_name} {score_display} {team2_name}")
        
        if winner_id is None:
            print(f"  -> Unentschieden (1:1 Satzpunkte)")
        else:
            winner_name = conn.execute("SELECT name FROM teams WHERE id = ?", (winner_id,)).fetchone()['name']
            print(f"  -> Gewinner: {winner_name}")
        
        filled_count += 1
    
    # Aktualisiere Finalspiele mit Gruppenreferenzen
    print(f"\n[INFO] Aktualisiere Finalspiele mit Gruppenplatzierungen...")
    update_group_positions_in_finals(conn)
    
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
    
    # Setze auch alle Finalspiele mit Gruppenreferenzen zurück
    conn.execute("""
        UPDATE matches
        SET team1_id = NULL, team2_id = NULL, finished = 0, winner_id = NULL, loser_id = NULL
        WHERE phase = 'final' 
          AND (team1_ref LIKE 'A_%' OR team1_ref LIKE 'B_%' 
               OR team2_ref LIKE 'A_%' OR team2_ref LIKE 'B_%')
    """)
    
    # Lösche auch die Sets von diesen Finalspielen
    conn.execute("""
        DELETE FROM sets 
        WHERE match_id IN (
            SELECT id FROM matches 
            WHERE phase = 'final' 
              AND (team1_ref LIKE 'A_%' OR team1_ref LIKE 'B_%' 
                   OR team2_ref LIKE 'A_%' OR team2_ref LIKE 'B_%')
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
        group_id: ID der Gruppe (1 für A, 2 für B)
        position: Gewünschte Position (1 = Platz 1, 2 = Platz 2, etc.)
        
    Returns:
        int: Team-ID oder None wenn Position nicht existiert
    """
    # Hole alle Teams der Gruppe
    team_ids = [row['team_id'] for row in conn.execute("""
        SELECT team_id FROM group_teams WHERE group_id = ? ORDER BY team_id
    """, (group_id,)).fetchall()]
    
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
            -x['point_diff'],      # 2. Punktdifferenz (absteigend)
            -x['points_scored'],   # 3. Erzielte Punkte (absteigend)
            -x['sets_won']         # 4. Gewonnene Sätze (absteigend)
        )
    )
    
    # Rückgabe des Teams an der gewünschten Position
    if 0 < position <= len(standings_list):
        return standings_list[position - 1]['team_id']
    
    return None


def update_group_positions_in_finals(conn):
    """
    Aktualisiert alle Finalspiele mit Gruppenreferenzen (A_1, A_2, B_1, B_2).
    Wenn NICHT alle Gruppenspiele abgeschlossen sind, werden die Finalspiele zurückgesetzt.
    
    Args:
        conn: Datenbankverbindung
    """
    # Prüfe ob ALLE Gruppenspiele abgeschlossen sind
    group_match_count = conn.execute("SELECT COUNT(*) as cnt FROM matches WHERE phase = 'group'").fetchone()['cnt']
    finished_group_match_count = conn.execute("SELECT COUNT(*) as cnt FROM matches WHERE phase = 'group' AND finished = 1").fetchone()['cnt']
    
    all_group_matches_finished = (group_match_count > 0 and group_match_count == finished_group_match_count)
    
    # Finde alle Finalspiele mit Gruppenreferenzen
    final_matches = conn.execute("""
        SELECT id, team1_ref, team2_ref FROM matches WHERE phase = 'final'
    """).fetchall()
    
    updated_count = 0
    reset_count = 0
    
    for match in final_matches:
        has_group_ref1 = match['team1_ref'] and '_' in match['team1_ref'] and match['team1_ref'][0] in ['A', 'B']
        has_group_ref2 = match['team2_ref'] and '_' in match['team2_ref'] and match['team2_ref'][0] in ['A', 'B']
        
        # Wenn dieses Match Gruppenreferenzen hat
        if has_group_ref1 or has_group_ref2:
            # Wenn NICHT alle Gruppenspiele fertig sind, setze die Teams auf NULL zurück
            if not all_group_matches_finished:
                conn.execute("""
                    UPDATE matches SET team1_id = NULL, team2_id = NULL WHERE id = ?
                """, (match['id'],))
                reset_count += 1
            else:
                # Alle Gruppenspiele sind fertig - berechne die Platzierungen
                new_team1_id = None
                new_team2_id = None
                
                # Prüfe team1_ref auf Gruppenreferenz
                if has_group_ref1:
                    group_name, position = match['team1_ref'].split('_')
                    group_id = 1 if group_name == 'A' else 2
                    new_team1_id = get_group_standing_team(conn, group_id, int(position))
                
                # Prüfe team2_ref auf Gruppenreferenz
                if has_group_ref2:
                    group_name, position = match['team2_ref'].split('_')
                    group_id = 1 if group_name == 'A' else 2
                    new_team2_id = get_group_standing_team(conn, group_id, int(position))
                
                # Hole aktuelle Werte falls nur eine Seite aktualisiert wird
                if new_team1_id is None:
                    current = conn.execute("SELECT team1_id FROM matches WHERE id = ?", (match['id'],)).fetchone()
                    new_team1_id = current['team1_id']
                if new_team2_id is None:
                    current = conn.execute("SELECT team2_id FROM matches WHERE id = ?", (match['id'],)).fetchone()
                    new_team2_id = current['team2_id']
                
                conn.execute("""
                    UPDATE matches SET team1_id = ?, team2_id = ? WHERE id = ?
                """, (new_team1_id, new_team2_id, match['id']))
                
                updated_count += 1
                
                # Hole Matchinfo für Ausgabe
                match_info = conn.execute("SELECT round FROM matches WHERE id = ?", (match['id'],)).fetchone()
                team1_name = conn.execute("SELECT name FROM teams WHERE id = ?", (new_team1_id,)).fetchone() if new_team1_id else None
                team2_name = conn.execute("SELECT name FROM teams WHERE id = ?", (new_team2_id,)).fetchone() if new_team2_id else None
                
                if team1_name and team2_name:
                    print(f"  -> Match #{match['id']} ({match_info['round']}): {team1_name['name']} vs {team2_name['name']}")
    
    conn.commit()
    
    if reset_count > 0:
        print(f"[INFO] {reset_count} Finalspiel(e) zurückgesetzt (nicht alle Gruppenspiele abgeschlossen).")
    if updated_count > 0:
        print(f"[OK] {updated_count} Finalspiel(e) mit Gruppenplatzierungen aktualisiert.")
    if reset_count == 0 and updated_count == 0:
        print(f"[INFO] Keine Finalspiele mit Gruppenreferenzen gefunden.")


if __name__ == "__main__":
    print("=" * 60)
    print("GRUPPENSPIEL-ERGEBNIS GENERATOR")
    print("=" * 60)
    print()
    print("Wähle eine Option:")
    print("  [1] Gruppenspiele mit Zufallsergebnissen füllen")
    print("  [2] Alle Gruppenspiel-Ergebnisse löschen")
    print("  [q] Beenden")
    print()
    
    choice = input("Deine Wahl: ").strip().lower()
    
    if choice == "1":
        print()
        fill_group_matches_with_results()
    elif choice == "2":
        print()
        confirm = input("[WARNUNG] Wirklich alle Gruppenspiel-Ergebnisse löschen? (ja/nein): ").strip().lower()
        if confirm in ['ja', 'j', 'yes', 'y']:
            print()
            clear_all_group_results()
        else:
            print("[ABBRUCH] Abgebrochen.")
    elif choice == "q":
        print("Tschüss!")
    else:
        print("[FEHLER] Ungültige Eingabe.")
    
    print()

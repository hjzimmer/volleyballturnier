"""
Korrigiert winner_id und loser_id für alle bereits eingegebenen Matches
basierend auf den tatsächlichen Satzergebnissen
"""

import sqlite3

def fix_match_results():
    conn = sqlite3.connect('data/tournament.db')
    cursor = conn.cursor()
    
    # Hole alle fertigen Matches mit Rundeninfo
    cursor.execute("""
        SELECT m.id, m.team1_id, m.team2_id, m.winner_id, m.loser_id, m.round
        FROM matches m
        WHERE m.finished = 1
    """)
    
    matches = cursor.fetchall()
    fixed_count = 0
    
    print(f"Überprüfe {len(matches)} fertige Matches...\n")
    
    for match_id, team1_id, team2_id, current_winner, current_loser, round_name in matches:
        # Hole Satzergebnisse
        cursor.execute("""
            SELECT team1_points, team2_points
            FROM sets
            WHERE match_id = ?
            ORDER BY set_number
        """, (match_id,))
        
        sets = cursor.fetchall()
        
        if not sets:
            print(f"[WARNUNG] Match {match_id}: Keine Sätze gefunden")
            continue
        
        # Zähle Satzgewinne
        team1_wins = 0
        team2_wins = 0
        total_team1 = 0
        total_team2 = 0
        
        for team1_points, team2_points in sets:
            total_team1 += team1_points
            total_team2 += team2_points
            
            if team1_points > team2_points:
                team1_wins += 1
            elif team2_points > team1_points:
                team2_wins += 1
            # Bei Gleichstand wird nichts gezählt
        
        # Bestimme ob Playoff-Match
        is_playoff = round_name in ['Halbfinale 1', 'Halbfinale 2', 'Finale', 'Spiel um Platz 3']
        
        # Bestimme korrekten Gewinner/Verlierer
        if team1_wins > team2_wins:
            correct_winner = team1_id
            correct_loser = team2_id
        elif team2_wins > team1_wins:
            correct_winner = team2_id
            correct_loser = team1_id
        else:
            # 1:1 Satzstand
            if is_playoff:
                # In der Endrunde entscheidet die Punktdifferenz
                if total_team1 > total_team2:
                    correct_winner = team1_id
                    correct_loser = team2_id
                else:
                    correct_winner = team2_id
                    correct_loser = team1_id
            else:
                # In der Vorrunde bleibt es unentschieden
                correct_winner = None
                correct_loser = None
        
        # Prüfe ob Korrektur nötig ist
        needs_fix = (current_winner != correct_winner) or (current_loser != correct_loser)
        
        if needs_fix:
            # Hole Team-Namen für Ausgabe
            cursor.execute("SELECT name FROM teams WHERE id = ?", (team1_id,))
            team1_name = cursor.fetchone()[0]
            cursor.execute("SELECT name FROM teams WHERE id = ?", (team2_id,))
            team2_name = cursor.fetchone()[0]
            
            # Status anzeigen
            set_results = " | ".join([f"{s[0]}:{s[1]}" for s in sets])
            status_info = f"Satzpunkte: {team1_wins}:{team2_wins}"
            
            if team1_wins == team2_wins and is_playoff:
                status_info += f" (Gesamt: {total_team1}:{total_team2}, Diff: {total_team1 - total_team2:+d})"
            
            print(f"[FIX] Match {match_id}: {team1_name} - {team2_name} [{round_name}]")
            print(f"   Sätze: {set_results} ({status_info})")
            
            if current_winner:
                cursor.execute("SELECT name FROM teams WHERE id = ?", (current_winner,))
                old_winner = cursor.fetchone()[0]
                print(f"   ALT: Gewinner = {old_winner}")
            else:
                print(f"   ALT: Gewinner = NULL")
            
            if correct_winner:
                cursor.execute("SELECT name FROM teams WHERE id = ?", (correct_winner,))
                new_winner = cursor.fetchone()[0]
                print(f"   NEU: Gewinner = {new_winner}")
            else:
                print(f"   NEU: Unentschieden (winner_id = NULL)")
            
            # Update
            cursor.execute("""
                UPDATE matches
                SET winner_id = ?, loser_id = ?
                WHERE id = ?
            """, (correct_winner, correct_loser, match_id))
            
            fixed_count += 1
            print()
    
    conn.commit()
    conn.close()
    
    print(f"\n[OK] {fixed_count} Matches korrigiert!")
    if fixed_count == 0:
        print("   Alle Matches waren bereits korrekt.")

if __name__ == "__main__":
    print("=" * 60)
    print("MATCH-ERGEBNISSE KORREKTUR")
    print("=" * 60)
    print()
    
    fix_match_results()

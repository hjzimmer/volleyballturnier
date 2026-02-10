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
    team1_points = random.randint(5, 10)
    team2_points = random.randint(5, 10)
    
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
    
    conn.close()
    
    print(f"\n[OK] {filled_count} Gruppenspiele erfolgreich mit Ergebnissen gefüllt!")
    print(f"[INFO] Die Gruppenplatzierungen werden jetzt automatisch berechnet.")
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
    
    conn.commit()
    conn.close()
    
    print(f"[OK] {count} Gruppenspiel-Ergebnisse gelöscht.")
    print(f"[OK] Alle Gruppenspiele sind jetzt wieder offen.")


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

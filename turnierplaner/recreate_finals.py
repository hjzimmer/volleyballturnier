"""
Script zum Neu-Erstellen der Endrunde, ohne Teams und Ergebnisse der Vorrunde zu löschen.
"""

import sys
from db import get_connection
from standings import calculate_group_standings
from finals import create_final_matches
from scheduling import schedule_all_matches
from referees import assign_group_referees
import json
from datetime import datetime

def recreate_finals_only():
    """
    Erstellt nur die Endrunde neu, behält Teams und Vorrunden-Ergebnisse bei.
    
    Vorgehensweise:
    1. Lösche nur Finalrunden-Matches und deren Sets
    2. Berechne Gruppenstände neu
    3. Erstelle Finalrunden-Matches neu
    4. Plane Zeitplan neu (alle Matches)
    5. Weise Schiedsrichter für Endrunde zu
    """
    conn = get_connection()
    
    # 1. Lösche nur die Finalrunden-Matches und deren Sets
    print("[1/5] Lösche alte Endrunden-Matches...")
    
    # Hole alle Final-Match IDs
    final_match_ids = [row['id'] for row in conn.execute(
        "SELECT id FROM matches WHERE phase = 'final'"
    ).fetchall()]
    
    if final_match_ids:
        # Lösche Sets der Finalrunden-Matches
        placeholders = ','.join('?' * len(final_match_ids))
        conn.execute(f"DELETE FROM sets WHERE match_id IN ({placeholders})", final_match_ids)
        print(f"   → {conn.total_changes} Sets gelöscht")
        
        # Lösche Finalrunden-Matches
        conn.execute("DELETE FROM matches WHERE phase = 'final'")
        print(f"   → {len(final_match_ids)} Finalrunden-Matches gelöscht")
    else:
        print("   → Keine Finalrunden-Matches gefunden")
    
    conn.commit()
    
    # 2. Berechne Gruppenstände neu
    print("[2/5] Berechne Gruppenstände neu...")
    standings_a = calculate_group_standings(1)
    standings_b = calculate_group_standings(2)
    
    print(f"   → Gruppe A: {len(standings_a)} Teams")
    print(f"   → Gruppe B: {len(standings_b)} Teams")
    
    group_tables = {
        "A": standings_a,
        "B": standings_b
    }
    
    # 3. Erstelle Finalrunden-Matches neu
    print("[3/5] Erstelle Finalrunden-Matches neu...")
    create_final_matches("final_config.json", group_tables)
    
    # 4. Plane Zeitplan für alle Matches neu
    print("[4/5] Plane Zeitplan neu...")
    schedule_all_matches("turnier_config.json")
    
    # 5. Weise Schiedsrichter für Endrunde zu
    print("[5/5] Weise Schiedsrichter für Endrunde zu...")
    
    # Hole alle Finalrunden-Matches mit beiden Teams
    final_matches = conn.execute("""
        SELECT id, team1_id, team2_id 
        FROM matches 
        WHERE phase = 'final' 
          AND team1_id IS NOT NULL 
          AND team2_id IS NOT NULL
          AND (referee_team_id IS NULL OR referee_team_id = 0)
    """).fetchall()
    
    assigned_count = 0
    for match in final_matches:
        playing_teams = [match['team1_id'], match['team2_id']]
        
        # Finde Team mit wenigsten Schiedsrichter-Einsätzen, das nicht spielt
        referee = conn.execute("""
            SELECT t.id, COUNT(m.id) as ref_count
            FROM teams t
            LEFT JOIN matches m ON m.referee_team_id = t.id
            WHERE t.id NOT IN (?, ?)
            GROUP BY t.id
            ORDER BY ref_count ASC, t.id ASC
            LIMIT 1
        """, playing_teams).fetchone()
        
        if referee:
            conn.execute(
                "UPDATE matches SET referee_team_id = ? WHERE id = ?",
                (referee['id'], match['id'])
            )
            assigned_count += 1
    
    conn.commit()
    conn.close()
    
    print(f"   → {assigned_count} Schiedsrichter zugewiesen")
    print("\n[OK] Endrunde erfolgreich neu erstellt!")
    print("   Teams und Vorrunden-Ergebnisse bleiben erhalten.\n")


if __name__ == "__main__":
    # Prüfe ob --yes Parameter übergeben wurde (für CLI-Nutzung)
    skip_confirm = len(sys.argv) > 1 and sys.argv[1] == "--yes"
    
    if not skip_confirm:
        print("=" * 60)
        print("ENDRUNDE NEU ERSTELLEN")
        print("=" * 60)
        print()
        print("!!  ACHTUNG: Dies löscht alle Finalrunden-Matches und deren")
        print("   Ergebnisse, aber behält Teams und Vorrunden-Ergebnisse bei.")
        print()
        
        confirm = input("Fortfahren? (ja/nein): ").strip().lower()
        
        if confirm not in ['ja', 'j', 'yes', 'y']:
            print("\nAbgebrochen.")
            sys.exit(1)
        
        print()
    
    recreate_finals_only()

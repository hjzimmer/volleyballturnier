"""
Sicheres Umbenennen von Teams - kann während des Turniers verwendet werden
ÄNDERT NUR DEN TEAMNAMEN, behält alle Ergebnisse, Zuordnungen und Historie
"""

import sqlite3
import sys

def list_teams(conn):
    """Zeigt alle Teams mit ihrer ID an"""
    cursor = conn.cursor()
    cursor.execute("SELECT id, name FROM teams ORDER BY id")
    teams = cursor.fetchall()
    
    print("\n" + "=" * 60)
    print("AKTUELLE TEAMS")
    print("=" * 60)
    for team_id, name in teams:
        print(f"  {team_id:2d}. {name}")
    print("=" * 60)
    
    return teams

def get_team_stats(conn, team_id):
    """Zeigt Statistiken für ein Team"""
    cursor = conn.cursor()
    
    # Gruppe
    cursor.execute("""
        SELECT g.name 
        FROM groups g
        JOIN group_teams gt ON g.id = gt.group_id
        WHERE gt.team_id = ?
    """, (team_id,))
    group = cursor.fetchone()
    group_name = group[0] if group else "Keine"
    
    # Matches gespielt
    cursor.execute("""
        SELECT COUNT(*) 
        FROM matches 
        WHERE (team1_id = ? OR team2_id = ?) AND finished = 1
    """, (team_id, team_id))
    matches_played = cursor.fetchone()[0]
    
    # Matches geplant
    cursor.execute("""
        SELECT COUNT(*) 
        FROM matches 
        WHERE (team1_id = ? OR team2_id = ?)
    """, (team_id, team_id))
    matches_total = cursor.fetchone()[0]
    
    # Siege
    cursor.execute("""
        SELECT COUNT(*) 
        FROM matches 
        WHERE winner_id = ? AND finished = 1
    """, (team_id,))
    wins = cursor.fetchone()[0]
    
    return {
        'group': group_name,
        'matches_played': matches_played,
        'matches_total': matches_total,
        'wins': wins
    }

def rename_team(conn, team_id, new_name):
    """Benennt ein Team um"""
    cursor = conn.cursor()
    
    # Hole alten Namen
    cursor.execute("SELECT name FROM teams WHERE id = ?", (team_id,))
    result = cursor.fetchone()
    
    if not result:
        print(f"❌ Team mit ID {team_id} nicht gefunden!")
        return False
    
    old_name = result[0]
    
    # Stats anzeigen
    stats = get_team_stats(conn, team_id)
    
    print(f"\n[INFO] TEAM-INFORMATION:")
    print(f"   ID: {team_id}")
    print(f"   Alter Name: {old_name}")
    print(f"   Neuer Name: {new_name}")
    print(f"   Gruppe: {stats['group']}")
    print(f"   Matches: {stats['matches_played']} gespielt von {stats['matches_total']}")
    print(f"   Siege: {stats['wins']}")
    
    # Bestätigung
    print(f"\n[WARNUNG] UMBENENNUNG:")
    print(f"   '{old_name}' -> '{new_name}'")
    confirmation = input(f"\n   Fortfahren? (ja/nein): ").strip().lower()
    
    if confirmation not in ['ja', 'j', 'yes', 'y']:
        print("[ABBRUCH] Abgebrochen.")
        return False
    
    # Update durchführen
    cursor.execute("UPDATE teams SET name = ? WHERE id = ?", (new_name, team_id))
    conn.commit()
    
    print(f"\n[OK] Team erfolgreich umbenannt!")
    print(f"   '{old_name}' -> '{new_name}'")
    
    return True

def interactive_rename():
    """Interaktive Umbenennung"""
    conn = sqlite3.connect('data/tournament.db')
    conn.row_factory = sqlite3.Row
    
    print("=" * 60)
    print("TEAM UMBENENNEN")
    print("=" * 60)
    print("Dieses Skript ändert NUR den Teamnamen.")
    print("Alle Matches, Ergebnisse und Zuordnungen bleiben erhalten.")
    print()
    
    # Zeige alle Teams
    teams = list_teams(conn)
    
    if not teams:
        print("❌ Keine Teams in der Datenbank!")
        conn.close()
        return
    
    # Frage nach Team-ID
    print()
    try:
        team_id = int(input("Team-ID eingeben (oder 0 zum Abbrechen): ").strip())
    except ValueError:
        print("❌ Ungültige Eingabe!")
        conn.close()
        return
    
    if team_id == 0:
        print("Abgebrochen.")
        conn.close()
        return
    
    # Frage nach neuem Namen
    print()
    new_name = input("Neuer Teamname: ").strip()
    
    if not new_name:
        print("❌ Name darf nicht leer sein!")
        conn.close()
        return
    
    # Führe Umbenennung durch
    rename_team(conn, team_id, new_name)
    
    # Zeige aktualisierte Liste
    print()
    list_teams(conn)
    
    conn.close()

def batch_rename_from_config():
    """Benennt mehrere Teams aus data/team_config.json um"""
    import json
    
    try:
        with open('data/team_config.json', 'r', encoding='utf-8') as f:
            config = json.load(f)
    except FileNotFoundError:
        print("❌ data/team_config.json nicht gefunden!")
        return
    
    conn = sqlite3.connect('data/tournament.db')
    conn.row_factory = sqlite3.Row
    
    print("=" * 60)
    print("TEAMS AUS CONFIG AKTUALISIEREN")
    print("=" * 60)
    
    updated = 0
    
    for team in config['teams']:
        team_id = team['id']
        new_name = team['name']
        
        cursor = conn.cursor()
        cursor.execute("SELECT name FROM teams WHERE id = ?", (team_id,))
        result = cursor.fetchone()
        
        if result:
            old_name = result[0]
            if old_name != new_name:
                cursor.execute("UPDATE teams SET name = ? WHERE id = ?", (new_name, team_id))
                print(f"[OK] Team {team_id}: '{old_name}' -> '{new_name}'")
                updated += 1
    
    if updated > 0:
        conn.commit()
        print(f"\n[OK] {updated} Teams aktualisiert!")
    else:
        print("\n[OK] Alle Teams sind bereits aktuell.")
    
    conn.close()

if __name__ == "__main__":
    print()
    print("=" * 60)
    print("TEAM VERWALTUNG")
    print("=" * 60)
    print()
    print("Optionen:")
    print("  [1] Team interaktiv umbenennen")
    print("  [2] Alle Teams aus data/team_config.json aktualisieren")
    print("  [q] Beenden")
    print()
    
    choice = input("Auswahl: ").strip().lower()
    
    if choice == '1':
        interactive_rename()
    elif choice == '2':
        batch_rename_from_config()
    elif choice == 'q':
        print("Beendet.")
    else:
        print("❌ Ungültige Auswahl!")

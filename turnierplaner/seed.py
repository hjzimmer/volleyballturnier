from db import get_connection
import json
import os

def seed_teams():
    """
    Erstellt Teams aus team_config.json
    Falls team_config.json nicht existiert, werden Standard-Teams erstellt
    """
    conn = get_connection()
    
    # Versuche team_config.json zu laden
    if os.path.exists('team_config.json'):
        try:
            with open('team_config.json', 'r', encoding='utf-8') as f:
                config = json.load(f)
            
            teams = [(team['id'], team['name']) for team in config['teams']]
            print(f"[OK] Lade {len(teams)} Teams aus team_config.json")
        except Exception as e:
            print(f"[WARNUNG] Fehler beim Laden von team_config.json: {e}")
            print(f"[OK] Verwende Standard-Teams")
            teams = [(i, f"Team {i}") for i in range(1, 4)]
    else:
        print(f"[WARNUNG] team_config.json nicht gefunden")
        print(f"[OK] Verwende Standard-Teams")
        teams = [(i, f"Team {i}") for i in range(1, 4)]
    
    conn.execute("DELETE FROM teams")
    conn.executemany("INSERT INTO teams (id, name) VALUES (?, ?)", teams)
    conn.commit()
    conn.close()
    print(f"Teams initialisiert.")


def seed_groups():
    """
    Erstellt Gruppen aus team_config.json
    Falls team_config.json nicht existiert, werden Standard-Gruppen erstellt
    """
    conn = get_connection()
    conn.execute("DELETE FROM groups")
    conn.execute("INSERT INTO groups VALUES (1, 'A')")
    conn.execute("INSERT INTO groups VALUES (2, 'B')")

    # Versuche Gruppenzuordnung aus team_config.json zu laden
    if os.path.exists('team_config.json'):
        try:
            with open('team_config.json', 'r', encoding='utf-8') as f:
                config = json.load(f)
            
            group_a = [team['id'] for team in config['teams'] if team['group'] == 'A']
            group_b = [team['id'] for team in config['teams'] if team['group'] == 'B']
            
            print(f"[OK] Gruppe A: {len(group_a)} Teams, Gruppe B: {len(group_b)} Teams")
        except Exception as e:
            print(f"[WARNUNG] Fehler beim Laden der Gruppen: {e}")
            print(f"[OK] Verwende Standard-Gruppen (1-5: A, 6-10: B)")
            group_a = [1,2,3,4,5]
            group_b = [6,7,8,9,10]
    else:
        group_a = [1,2,3,4,5]
        group_b = [6,7,8,9,10]

    for t in group_a:
        conn.execute("INSERT INTO group_teams VALUES (1, ?)", (t,))
    for t in group_b:
        conn.execute("INSERT INTO group_teams VALUES (2, ?)", (t,))

    conn.commit()
    conn.close()

    print(f"Gruppen initialisiert.")

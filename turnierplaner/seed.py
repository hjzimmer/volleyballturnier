from db import get_connection
import json
import os

def seed_teams():
    """
    Erstellt Teams aus data/team_config.json
    Falls data/team_config.json nicht existiert, werden Standard-Teams erstellt
    """
    conn = get_connection()
    
    # Versuche data/team_config.json zu laden
    if os.path.exists('data/team_config.json'):
        try:
            with open('data/team_config.json', 'r', encoding='utf-8') as f:
                config = json.load(f)
            
            teams = [(team['id'], team['name']) for team in config['teams']]
            print(f"[OK] Lade {len(teams)} Teams aus data/team_config.json")
        except Exception as e:
            print(f"[WARNUNG] Fehler beim Laden von data/team_config.json: {e}")
            print(f"[OK] Verwende Standard-Teams")
            teams = [(i, f"Team {i}") for i in range(1, 4)]
    else:
        print(f"[WARNUNG] data/team_config.json nicht gefunden")
        print(f"[OK] Verwende Standard-Teams")
        teams = [(i, f"Team {i}") for i in range(1, 4)]
    
    conn.execute("DELETE FROM teams")
    conn.executemany("INSERT INTO teams (id, name) VALUES (?, ?)", teams)
    conn.commit()
    conn.close()
    print(f"Teams initialisiert.")


def seed_groups():
    """
    Erstellt Gruppen aus data/turnier_config.json
    Falls data/turnier_config.json nicht existiert, werden Standard-Gruppen erstellt
    """
    conn = get_connection()
    conn.execute("DELETE FROM groups")
    conn.execute("DELETE FROM group_teams")

    # Lade alle Gruppen aus allen Phasen, Teams nur für Startphase
    if os.path.exists('data/turnier_config.json'):
        try:
            with open('data/turnier_config.json', 'r', encoding='utf-8') as f:
                config = json.load(f)
            phases = config.get('phases', [])
            startphase = None
            # Finde Startphase (erste Phase mit nur Integer-Teamzuordnung)
            for phase in phases:
                if all(
                    all(isinstance(team, int) for team in group.get('teams', []))
                    for group in phase.get('groups', [])
                ):
                    startphase = phase
                    break
            # Lege alle Gruppen aus allen Phasen an (nutze die IDs aus der Config!)
            group_ids = set()
            for phase in phases:
                for group in phase.get('groups', []):
                    group_id = group['id']
                    if group_id in group_ids:
                        continue  # Doppelte Gruppen vermeiden
                    group_ids.add(group_id)
                    conn.execute("INSERT INTO groups (id, name, phase_name) VALUES (?, ?, ?)", (group_id, group['name'], phase['name']))
                    # Teams für Startphase zuordnen
                    if startphase and group in startphase.get('groups', []):
                        for team_id in group.get('teams', []):
                            conn.execute("INSERT INTO group_teams (group_id, team_id) VALUES (?, ?)", (group_id, team_id))
                    # else:
                        # Für spätere Gruppen Platzhalter-Team eintragen
                        # conn.execute("INSERT INTO group_teams (group_id, team_id) VALUES (?, ?)", (group_id, -1))
            print(f"[OK] {len(group_ids)} Gruppen angelegt. Teams für Startphase zugeordnet, Platzhalter für spätere Gruppen eingetragen.")
        except Exception as e:
            print(f"[WARNUNG] Fehler beim Laden der Gruppen: {e}")
    else:
        print(f"[WARNUNG] data/turnier_config.json nicht gefunden. Keine Gruppen importiert.")

    conn.commit()
    conn.close()
    print(f"Gruppen initialisiert.")

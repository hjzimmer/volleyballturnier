"""
Validiert turnier_config.json für beliebige Gruppen- und Finalphasen
Prüft Syntax, Schlüsselwörter und grundlegende Logik
"""

import json
import sys

def validate_turnier_config(config_path='turnier_config.json'):
    print("=" * 70)
    print("TURNIER-KONFIGURATION VALIDIERUNG")
    print("=" * 70)
    print()
    try:
        with open(config_path, 'r', encoding='utf-8') as f:
            config = json.load(f)
    except FileNotFoundError:
        print(f"[FEHLER] {config_path} nicht gefunden!")
        return False
    except json.JSONDecodeError as e:
        print(f"[FEHLER] Ungültiges JSON in {config_path}")
        print(f"   {e}")
        return False

    errors = []
    warnings = []

    # 1. Top-Level Schlüssel prüfen
    required_top_keys = [
        'tournament_name', 'fields', 'phases', 'sets_per_match', 'set_minutes',
        'pause_between_sets', 'pause_between_matches', 'lunch_break'
    ]
    for key in required_top_keys:
        if key not in config:
            errors.append(f"Top-Level Schlüssel fehlt: '{key}'")
    if not isinstance(config.get('phases'), list):
        errors.append("'phases' muss ein Array sein!")

    # 2. Phasen prüfen
    phase_ids = set()
    group_ids = set()
    for phase in config.get('phases', []):
        if 'id' not in phase or 'name' not in phase:
            errors.append(f"Phase ohne 'id' oder 'name': {phase}")
            continue
        if phase['id'] in phase_ids:
            errors.append(f"Doppelte Phase-ID: {phase['id']}")
        phase_ids.add(phase['id'])
        # Mindestens 'groups' oder 'matches' muss vorhanden sein
        if 'groups' not in phase and 'matches' not in phase:
            errors.append(f"Phase {phase['id']} ({phase['name']}) hat weder 'groups' noch 'matches'!")
        # Gruppenphase
        if 'groups' in phase:
            if not isinstance(phase['groups'], list):
                errors.append(f"'groups' in Phase {phase['id']} ist kein Array!")
            for group in phase['groups']:
                if 'id' not in group or 'name' not in group or 'teams' not in group:
                    errors.append(f"Gruppe ohne 'id', 'name' oder 'teams' in Phase {phase['id']}")
                    continue
                if group['id'] in group_ids:
                    errors.append(f"Doppelte Gruppen-ID: {group['id']}")
                group_ids.add(group['id'])
                if not isinstance(group['teams'], list):
                    errors.append(f"'teams' in Gruppe {group['id']} ist kein Array!")
        # Finalphase
        if 'matches' in phase:
            if not isinstance(phase['matches'], list):
                errors.append(f"'matches' in Phase {phase['id']} ist kein Array!")
            for match in phase['matches']:
                for key in ['id', 'name', 'team1', 'team2']:
                    if key not in match:
                        errors.append(f"Match in Phase {phase['id']} fehlt Schlüssel '{key}': {match}")
                # Prüfe Referenzstruktur
                for team_key in ['team1', 'team2']:
                    team = match.get(team_key)
                    if isinstance(team, dict):
                        if 'type' not in team:
                            errors.append(f"{team_key} in Match {match.get('id')} fehlt 'type'")
                        elif team['type'] == 'group_place':
                            if 'group' not in team or 'place' not in team:
                                errors.append(f"{team_key} in Match {match.get('id')} (group_place) fehlt 'group' oder 'place'")
                        elif team['type'] == 'match_winner':
                            if 'match_id' not in team or 'winner' not in team:
                                errors.append(f"{team_key} in Match {match.get('id')} (match_winner) fehlt 'match_id' oder 'winner'")
    # 3. Zusätzliche Checks
    if len(phase_ids) == 0:
        errors.append("Keine Phasen definiert!")
    if len(group_ids) == 0:
        warnings.append("Keine Gruppen gefunden (nur Finalphasen?)")
    # 4. Ausgabe
    print()
    if errors:
        print("[FEHLER] VALIDIERUNG FEHLGESCHLAGEN\n")
        for i, error in enumerate(errors, 1):
            print(f"   {i}. {error}")
        print("\n   -> Korrigiere diese Fehler in turnier_config.json vor dem Start!")
        return False
    if warnings:
        print("[WARNUNG] VALIDIERUNG MIT WARNUNGEN\n")
        for i, warning in enumerate(warnings, 1):
            print(f"   {i}. {warning}")
        print("\n   -> Das Turnier kann gestartet werden, aber überprüfe die Warnungen.")
    else:
        print("[OK] VALIDIERUNG ERFOLGREICH\n   Alle Checks bestanden!")
    print("=" * 70)
    print()
    return True

if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser(description="Validiert turnier_config.json")
    parser.add_argument('--config', default='turnier_config.json', help='Pfad zur Config-Datei')
    args = parser.parse_args()
    is_valid = validate_turnier_config(args.config)
    if not is_valid:
        sys.exit(1)
    else:
        sys.exit(0)

from validate_turnier_config import validate_turnier_config
"""
Validiert team_config.json für flexible Team-Anzahlen
Prüft auf häufige Fehler bevor das Turnier initialisiert wird
"""

import json
import sys
from collections import Counter

def validate_team_config(config_path='team_config.json'):
    """
    Validiert die Team-Konfiguration
    """
    print("=" * 70)
    print("TEAM-KONFIGURATION VALIDIERUNG")
    print("=" * 70)
    print()
    
    try:
        with open(config_path, 'r', encoding='utf-8') as f:
            config = json.load(f)
    except FileNotFoundError:
        print(f"[FEHLER] {config_path} nicht gefunden!")
        print(f"   Erstelle die Datei oder nutze team_config_beispiel.json als Vorlage.")
        return False
    except json.JSONDecodeError as e:
        print(f"[FEHLER] Ungültiges JSON in {config_path}")
        print(f"   {e}")
        return False
    
    if 'teams' not in config:
        print(f"[FEHLER] 'teams' Feld fehlt in der Config!")
        return False
    
    teams = config['teams']
    errors = []
    warnings = []
    
    # 1. Prüfe Anzahl Teams
    total_teams = len(teams)
    print(f"[INFO] Gesamt-Teams: {total_teams}")
    
    if total_teams == 0:
        errors.append("Keine Teams definiert!")
    elif total_teams < 4:
        errors.append(f"Zu wenige Teams ({total_teams}). Minimum: 4 Teams (2 pro Gruppe)")
    elif total_teams % 2 != 0:
        errors.append(f"Ungerade Anzahl Teams ({total_teams}). Es muss eine gerade Anzahl sein!")
    else:
        print(f"   [OK] Gerade Anzahl Teams")
    
    # 2. Gruppenprüfung entfällt, Teams haben keine Gruppenzuordnung mehr
    print(f"\n[INFO] Gruppenzuordnung: (nicht mehr in team_config, wird dynamisch zugewiesen)")
    
    # 3. Prüfe IDs
    ids = [t.get('id') for t in teams]
    id_counts = Counter(ids)
    duplicates = [id for id, count in id_counts.items() if count > 1]
    
    print(f"\n[INFO] Team-IDs:")
    if duplicates:
        errors.append(f"Doppelte IDs gefunden: {duplicates}")
    elif None in ids:
        errors.append("Einige Teams haben keine ID!")
    else:
        print(f"   [OK] Alle IDs eindeutig ({min(ids)} bis {max(ids)})")
    
    # 4. Prüfe Namen
    names = [t.get('name', '') for t in teams]
    name_counts = Counter(names)
    duplicate_names = [name for name, count in name_counts.items() if count > 1 and name]
    
    print(f"\n[INFO] Team-Namen:")
    if duplicate_names:
        warnings.append(f"Doppelte Namen gefunden: {duplicate_names}")
        warnings.append("   (Erlaubt, aber nicht empfohlen)")
    
    empty_names = [t for t in teams if not t.get('name') or t.get('name').strip() == '']
    if empty_names:
        warnings.append(f"{len(empty_names)} Teams haben leere Namen")
    
    if not warnings and not duplicate_names and not empty_names:
        print(f"   [OK] Alle Namen gesetzt")
    
    # 5. Einfache Statistiken (keine Gruppen mehr)
    print(f"\n[INFO] Turnier-Statistiken:")
    print(f"   - Gesamtzahl Teams: {total_teams}")
    
    # 6. Ausgabe Zusammenfassung
    print()
    print("=" * 70)
    
    if errors:
        print("[FEHLER] VALIDIERUNG FEHLGESCHLAGEN")
        print()
        print("FEHLER:")
        for i, error in enumerate(errors, 1):
            print(f"   {i}. {error}")
        print()
        print("   -> Korrigiere diese Fehler in team_config.json vor dem Start!")
    elif warnings:
        print("[WARNUNG] VALIDIERUNG MIT WARNUNGEN")
        print()
        print("WARNUNGEN:")
        for i, warning in enumerate(warnings, 1):
            print(f"   {i}. {warning}")
        print()
        print("   -> Das Turnier kann gestartet werden, aber überprüfe die Warnungen.")
    else:
        print("[OK] VALIDIERUNG ERFOLGREICH")
        print()
        print("   Alle Checks bestanden! Bereit für:")
        print("   python main.py")
    
    print("=" * 70)
    print()
    
    return len(errors) == 0


def show_team_list(config_path='team_config.json'):
    """
    Zeigt alle Teams übersichtlich an
    """
    try:
        with open(config_path, 'r', encoding='utf-8') as f:
            config = json.load(f)
    except:
        print(f"❌ Kann {config_path} nicht lesen")
        return
    
    teams = config.get('teams', [])
    
    print("\n" + "=" * 70)
    print("TEAM-ÜBERSICHT")
    print("=" * 70)
    
    group_a = sorted([t for t in teams if t.get('group') == 'A'], key=lambda x: x.get('id', 0))
    group_b = sorted([t for t in teams if t.get('group') == 'B'], key=lambda x: x.get('id', 0))
    
    print("\n🔵 GRUPPE A:")
    for t in group_a:
        print(f"   {t.get('id', '?'):2d}. {t.get('name', 'Unbenannt')}")
    
    print("\n🔴 GRUPPE B:")
    for t in group_b:
        print(f"   {t.get('id', '?'):2d}. {t.get('name', 'Unbenannt')}")
    
    print("=" * 70)
    print()


if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Validiert team_config.json")
    parser.add_argument('--config', default='team_config.json', help='Pfad zur Config-Datei')
    parser.add_argument('--show-teams', action='store_true', help='Zeigt Team-Liste an')
    args = parser.parse_args()
    
    if args.show_teams:
        show_team_list(args.config)
    
    is_valid_team = validate_team_config(args.config)
    is_valid_turnier = validate_turnier_config("turnier_config.json")
    if not is_valid_team or not is_valid_turnier:
        sys.exit(1)  # Exit mit Fehlercode
    else:
        sys.exit(0)  # Erfolg

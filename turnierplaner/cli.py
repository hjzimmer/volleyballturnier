import subprocess
import sys
from scheduling import schedule_all_matches
from referees import assign_group_referees, assign_final_referees


def show_menu():
    """Zeigt das Hauptmenü"""
    print()
    print("=" * 70)
    print("           VOLLEYBALL TURNIER - VERWALTUNG")
    print("=" * 70)
    print()
    print("[GEFAEHRLICH - Loescht Daten]")
    print("  [1] Init - Turnier neu initialisieren")
    print("      Erstellt neue Datenbank, laedt Teams/Config, generiert Matches")
    print("      [!] WARNUNG: LOESCHT ALLE EXISTIERENDEN DATEN!")
    print()
    print("  [2] Recreate Finals - Endrunde neu erstellen")
    print("      Loescht nur Endrunden-Matches, behaelt Vorrunde bei")
    print("      [!] WARNUNG: LOESCHT ENDRUNDEN-ERGEBNISSE!")
    print()
    print("[SICHER - Aendert nur Zeitplanung/Zuordnungen]")
    print("  [3] Schedule - Zeitplan neu berechnen")
    print("      Berechnet Start-Zeiten und Feldzuordnungen neu")
    print()
    print("  [4] Assign Group Refs - Schiedsrichter fuer Gruppenspiele")
    print("      Weist automatisch Schiedsrichter fuer Gruppenspiele zu")
    print()
    print("  [5] Assign Final Refs - Schiedsrichter fuer Endrunde")
    print("      Weist Schiedsrichter nur fuer unbespielte Finalrunden-Spiele zu")
    print()
    print("  [6] Rename Team - Team umbenennen")
    print("      Interaktives Menue zum Umbenennen von Teams")
    print()
    print("[VALIDIERUNG]")
    print("  [7] Validate Config - Konfiguration pruefen")
    print("      Prueft data/team_config.json auf Fehler und zeigt Statistiken")
    print()
    print("[NUR ZUM TESTEN]")
    print("  [8] Fill Results - Testdaten generieren")
    print("      Fuellt alle Gruppenspiele mit Zufallsergebnissen")
    print("      [!] WARNUNG: Ueberschreibt existierende Ergebnisse!")
    print()
    print("  [q] Beenden")
    print()
    print("=" * 70)


def run_action(action):
    """Führt die gewählte Aktion aus"""
    print()
    
    if action == "1" or action == "init":
        print("[INIT] Starte Turnier-Initialisierung...")
        print("-" * 70)
        
        print("=" * 60)
        print("[INIT] Starte Turnier-Initialisierung...")
        print("=" * 60)
        print()
        print("!!!  ACHTUNG: Dies löscht die gesamte Datenbank und startet von vorne.  !!!")
        print()
        confirm = input("Fortfahren? (ja/nein): ").strip().lower()
        if confirm in ['ja', 'j', 'yes', 'y']:
          print()
          result = subprocess.run([sys.executable, "main.py"], capture_output=True, text=True)
          print(result.stdout)
          if result.stderr:
              print(result.stderr, file=sys.stderr)
          return result.returncode == 0
        else:
          print("\nAbgebrochen.")
          return False          

    elif action == "2" or action == "recreate_finals":
        print("[RECREATE FINALS] Starte Endrunden-Neuerstellung...")
        print("-" * 70)
        
        print("=" * 60)
        print("[RECREATE FINALS] Endrunde neu erstellen")
        print("=" * 60)
        print()
        print("!!!  ACHTUNG: Dies löscht alle Endrunden-Matches und deren Ergebnisse.  !!!")
        print("     Teams und Vorrunden-Ergebnisse bleiben erhalten.")
        print()
        confirm = input("Fortfahren? (ja/nein): ").strip().lower()
        if confirm in ['ja', 'j', 'yes', 'y']:
            print()
            result = subprocess.run([sys.executable, "recreate_finals.py", "--yes"])
            return result.returncode == 0
        else:
            print("\nAbgebrochen.")
            return False

    elif action == "3" or action == "schedule":
        print("[SCHEDULE] Berechne Zeitplan neu...")
        print("-" * 70)
        schedule_all_matches("data/turnier_config.json")
        print()
        print("[OK] Zeitplan erstellt")
        return True
    
    elif action == "4" or action == "assign_refs" or action == "assign_group_refs":
        print("[ASSIGN GROUP REFS] Weise Schiedsrichter fuer Gruppenspiele zu...")
        print("-" * 70)
        assign_group_referees()
        print()
        print("[OK] Schiedsrichter fuer Gruppenspiele zugewiesen")
        return True
    
    elif action == "5" or action == "assign_final_refs":
        print("[ASSIGN FINAL REFS] Weise Schiedsrichter fuer Endrunde zu...")
        print("-" * 70)
        assign_final_referees()
        print()
        print("[OK] Schiedsrichter fuer Endrunde zugewiesen")
        return True
    
    elif action == "6" or action == "rename_team":
        print("[RENAME TEAM] Starte Team-Umbenennung...")
        print("-" * 70)
        result = subprocess.run([sys.executable, "rename_team.py"])
        return result.returncode == 0
    
    elif action == "7" or action == "validate":
        print("[VALIDATE] Pruefe Konfiguration...")
        print("-" * 70)
        result = subprocess.run([sys.executable, "validate_config.py"])
        return result.returncode == 0
    
    elif action == "8" or action == "fill_results":
        print("[FILL RESULTS] Generiere Testdaten...")
        print("-" * 70)
        result = subprocess.run([sys.executable, "fill_group_results.py"])
        return result.returncode == 0
    
    else:
        print(f"[FEHLER] Unbekannte Aktion: {action}")
        return False


def interactive_mode():
    """Interaktiver Modus mit Menü"""
    while True:
        show_menu()
        choice = input("Waehle eine Option: ").strip().lower()
        
        if choice == "q" or choice == "quit" or choice == "exit":
            print()
            print("Auf Wiedersehen!")
            break
        
        if choice in ["1", "2", "3", "4", "5", "6", "7", "8"]:
            success = run_action(choice)
            
            print()
            print("-" * 70)
            if success:
                print("[FERTIG] Aktion abgeschlossen")
            else:
                print("[FEHLER] Aktion fehlgeschlagen")
            print("-" * 70)
            
            input("\nDruecke Enter um fortzufahren...")
        else:
            print()
            print("[FEHLER] Ungueltige Eingabe. Bitte waehle 1-8 oder 'q'.")
            input("\nDruecke Enter um fortzufahren...")


def main():
    """Hauptfunktion - unterstützt interaktiven und direkten Modus"""
    # Direkter Modus: python cli.py <action>
    if len(sys.argv) > 1:
        action = sys.argv[1]
        valid_actions = ["init", "recreate_finals", "schedule", "assign_refs", "assign_group_refs", "assign_final_refs", "fill_results", "rename_team", "validate"]
        
        if action in valid_actions:
            success = run_action(action)
            sys.exit(0 if success else 1)
        else:
            print(f"[FEHLER] Unbekannte Aktion: {action}")
            print(f"Verfuegbare Aktionen: {', '.join(valid_actions)}")
            sys.exit(1)
    
    # Interaktiver Modus: python cli.py
    else:
        try:
            interactive_mode()
        except KeyboardInterrupt:
            print()
            print()
            print("Abgebrochen durch Benutzer.")
            sys.exit(0)


if __name__ == "__main__":
    main()

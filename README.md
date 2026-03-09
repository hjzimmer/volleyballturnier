# 🏐 Volleyball Turnierverwaltung – Kurzanleitung

## Projektüberblick

Dieses Projekt verwaltet Volleyball-Turniere mit flexibler Teamanzahl, automatischer Spielplan- und Finalrundenerstellung, Live-Ergebniseingabe und Zeitplanung. Die Bedienung erfolgt komfortabel über ein PHP-Webinterface. Die Datenhaltung und Logik erfolgt mit Python und SQLite.

### Hauptfunktionen
- Flexible Teamanzahl (4, 6, 8, 10, ...)
- Automatische Gruppeneinteilung und Spielplanerstellung
- Dynamische Finalrunden (Halbfinale, Finale, Platzierungsspiele)
- Live-Ergebniseingabe und -anzeige im Web
- Schiedsrichter-Zuordnung
- Zeitplanung für mehrere Felder
- Integrierter Spiel-Timer
- Konfiguration und Verwaltung komplett über das Webinterface

---

## Verzeichnisstruktur (wichtigste Ordner & Dateien)

- `data/` – Konfigurationsdateien (Teams, Turnier, Beispiele), Datenbank, Logo
- `php/` – PHP-Webinterface (Spielplan, Ergebnisse, Tabellen, Admin-Tools)
  - `Timer/` – Web-Timer und zugehörige Dateien
- `Docker/` – Dockerfile & docker-compose für einfache Bereitstellung
- Python-Skripte im Hauptverzeichnis (z.B. `cli.py`, `main.py`, `validate_config.py`)

Beispielstruktur:
```
├── data/
│   ├── team_config.json
│   ├── turnier_config.json
│   ├── tournament.db
│   └── vsvlogo.jpg
├── php/
│   ├── index.php
│   ├── groups.php
│   ├── bracket.php
│   ├── result_entry.php
│   ├── Timer/
│   │   └── index.php
│   └── ...
├── Docker/
│   ├── Dockerfile
│   └── docker-compose.yml
├── cli.py
├── main.py
└── ...
```

---

## Bereitstellung mit Docker

1. **Voraussetzungen:**
   - Docker & Docker Compose installiert

2. **Starten:**
   - Im Projektverzeichnis ausführen:
     ```bash
     docker-compose -f Docker/docker-compose.yml up --build
     ```
   - Das Webinterface ist danach erreichbar unter:  
     `http://localhost:8080/`

3. **Konfiguration:**
   - Teams und Turnierdaten werden über das Webinterface gepflegt (oder direkt in den JSON-Dateien im `data/`-Ordner).

4. **Daten persistieren:**
   - Die Datenbank und Konfigurationsdateien liegen im `data/`-Ordner und bleiben beim Neustart erhalten.

---

## Hinweise
- Änderungen an Teams oder Turnierstruktur erfordern ggf. ein Re-Init über das Webinterface oder per Skript (`python cli.py init`).
- Für Testzwecke können Zufallsergebnisse generiert werden.
- Die gesamte Verwaltung ist webbasiert, Python-Skripte stehen für Spezialfälle zur Verfügung.

---

## Konfigurationsdateien: Aufbau und Referenzen

### team_config.json

Die Datei `team_config.json` enthält die Teamdefinitionen für das Turnier. Jedes Team hat eine eindeutige ID, einen Namen und eine Gruppenzuordnung (z.B. "A" oder "B").

Beispiel:
```json
{
  "teams": [
    {"id": 1, "name": "SV Musterhausen"},
    {"id": 2, "name": "FC Beispiel"},
    {"id": 3, "name": "TSV Turnier"},
    ...
  ]
}
```
- `id`: Eindeutige Teamnummer
- `name`: Anzeigename

### turnier_config.json

Die Datei `turnier_config.json` beschreibt die gesamte Turnierstruktur, Zeitplanung und die Phasen (Gruppen, Zwischenrunden, Finals).

#### Allgemeiner Abschnitt
- `tournament_name`, `logo_path`, `tournament_start`, `fields`, `sets_per_match`, `set_minutes`, `pause_between_sets`, `pause_between_matches`, `lunch_break`, `result_entry_password`: Allgemeine Turnierparameter und Einstellungen.

#### Gruppen (groups)
- Im Abschnitt `phases` werden Gruppenphasen als `groups` definiert.
- Jede Gruppe hat eine ID, einen Namen und eine Liste von Teams (direkt als Team-IDs oder als Referenzen auf Platzierungen aus anderen Gruppen).
- Beispiel für eine Gruppenphase:
```json
{
  "id": "G1",
  "name": "Gruppe A",
  "teams": [1, 2, 3, 4, 5]
}
```
- Beispiel für eine Zwischenrunde mit Referenzen:
```json
{
  "id": "ZG1",
  "name": "Zwischenrunde ZG1",
  "teams": [
    {"type": "group_place", "group": "G1", "place": 1},
    {"type": "group_place", "group": "G2", "place": 2},
    ...
  ]
}
```
- Referenztypen in Gruppen:
  - `{ "type": "group_place", "group": "G1", "place": 1 }`: Nimmt den 1. Platz aus Gruppe G1

#### Finals (matches)
- Im Abschnitt `phases` mit `matches` werden die Finalspiele und Platzierungsspiele definiert.
- Jedes Match hat eine ID, einen Namen, Team-Referenzen und Platzierungszuweisungen.
- Beispiel:
```json
{
  "id": "F2",
  "name": "Spiel um Platz 3",
  "team1": {"type": "group_place", "group": "ZG1", "place": 2},
  "team2": {"type": "group_place", "group": "ZG2", "place": 2},
  "winner_placement": 3,
  "loser_placement": 4
}
```
- Referenztypen in Matches:
  - `{ "type": "group_place", "group": "ZG1", "place": 2 }`: Nimmt den 2. Platz aus Zwischenrunde ZG1
  - Auch möglich: Referenz auf vorherige Matches (z.B. Gewinner/Verlierer eines anderen Spiels)

#### Unterschied: groups vs. matches
- `groups`: Definieren eine Gruppe von Teams, die im Modus "jeder gegen jeden" (Round Robin) gegeneinander spielen. Die Platzierungen werden für spätere Phasen weiterverwendet.
- `matches`: Definieren einzelne Spiele (z.B. Halbfinale, Finale, Platzierungsspiele) mit expliziten Teamzuweisungen und Platzierungslogik.

#### Dummy-Matches für Platzierungen
- Es ist möglich, Dummy-Matches zu definieren, um Teams aus Gruppen ohne echtes Spiel direkt auf einen Platz im finalen Ranking zu setzen.
- Beispiel:
```json
{
  "id": "P7",
  "name": "Platz 7 aus Zwischenrunde",
  "team1": null,
  "team2": null,
  "winner_placement": {"type": "group_place", "group": "ZG3", "place": 1, "final_placement": 7},
  "loser_placement": {"type": "group_place", "group": "ZG3", "place": 2, "final_placement": 8}
}
```
- Damit werden z.B. die Plätze 7 und 8 direkt aus der Gruppenplatzierung übernommen, ohne ein weiteres Spiel auszutragen.

#### Zusammenfassung Referenzarten
- Direkte Team-ID (nur in Vorrunde): `1, 2, 3, ...`
- Platzierung aus Gruppe: `{ "type": "group_place", "group": "G1", "place": 1 }`
- Platzierung aus Zwischenrunde: `{ "type": "group_place", "group": "ZG2", "place": 3 }`
- (Optional) Gewinner/Verlierer eines Matches: `{ "type": "match_winner", "match": "F1" }`, `{ "type": "match_loser", "match": "F2" }`
- Dummy-Match für direkte Platzierung ohne Spiel: `team1`/`team2` = null, Platzierung über `winner_placement`/`loser_placement`

---

## Webinterface: Ergebniseingabe & Konfiguration

### result_entry.php – Ergebniseingabe und Verwaltung

Die Seite `result_entry.php` ist das zentrale Tool zur Verwaltung und Eingabe der Spielergebnisse während des Turniers.

**Funktionen:**
- Übersicht aller anstehenden und abgeschlossenen Matches
- Ergebnisse für jedes Match eintragen oder bearbeiten (per Klick auf "Eintragen"/"Bearbeiten")
- Satzergebnisse für 1 oder 2 Sätze (je nach Konfiguration)
- Automatische Berechnung des Gewinners nach Speichern
- Schiedsrichter für jedes Match zuweisen (Dropdown)
- Ergebnisse löschen (setzt Match auf "offen")
- Statusanzeige: Offen, Beendet, Warten auf Teams
- Sofortige Aktualisierung der Tabellen und Finalrunden nach Ergebniseingabe

**Ablauf:**
1. Match suchen und auswählen
2. "Eintragen" oder "löschen" klicken
3. Satzergebnisse eingeben, ggf. Schiedsrichter auswählen
4. Speichern – das System berechnet automatisch den Gewinner und aktualisiert alle relevanten Ansichten

### setup_config_edit.php – Live-Konfiguration im Web

Mit `setup_config_edit.php` können zentrale Einstellungen und Konfigurationsdateien direkt im Browser bearbeitet werden:
- Bearbeitung von `team_config.json` (Teams, Namen, Gruppenzuordnung)
- Bearbeitung von `turnier_config.json` (Turnierstruktur, Zeiten, Felder, Pausen, Phasen)
- Validierung der Konfiguration vor dem Speichern
- Änderungen werden sofort übernommen und wirken sich auf das laufende Turnier aus (ggf. Re-Init nötig)
- Ermöglicht schnelle Anpassungen ohne direkten Dateizugriff

**Typische Editiermöglichkeiten:**
- Teams hinzufügen, entfernen, umbenennen
- Startzeiten, Pausen, Felder anpassen
- Turniername, Logo, Passwort ändern
- Gruppen- und Finalstruktur flexibel anpassen

### Verbindung zum Timer (Timer/Countdown)

Das System ist mit einem webbasierten Timer gekoppelt, der über `php/Timer/index.php` und `php/Timer/countdown.php` gesteuert wird:
- Der Timer zeigt die aktuelle Restzeit für das laufende Match an
- Start, Pause, Reset und Zeitvorgaben können über das Webinterface gesteuert werden
- Die Zeitsteuerung ist mit dem Spielplan und den Ergebnissen verknüpft: Nach Abschluss eines Matches kann automatisch der nächste Countdown gestartet werden
- Der Status des Timers wird in `timer_status.json` gespeichert und von allen relevanten Seiten (z.B. Spielplan, Ergebniseingabe) angezeigt

**Zusammenspiel:**
- Nach Ergebniseintrag in `result_entry.php` kann der Timer für das nächste Match automatisch oder manuell gestartet werden
- Die aktuelle Zeit und der Status werden live im Webinterface angezeigt
- Über das Admin-Panel kann der Timer jederzeit angepasst werden

---

## Python-Interface: Verwaltung und Spezialfunktionen

### Einstieg: cli.py

Das Python-Interface wird über das Skript `cli.py` im Hauptverzeichnis gestartet. Es bietet ein interaktives Menü für alle wichtigen Verwaltungs- und Spezialfunktionen rund um das Turnier.

**Start:**
```bash
python cli.py
```

**Hauptoptionen im Menü:**
- **[1] Init – Turnier neu initialisieren**
  - Erstellt die Datenbank neu, lädt Teams und Konfiguration, generiert alle Matches und Gruppen
  - ⚠️ Achtung: Löscht alle bisherigen Ergebnisse und Teams (nur vor Turnierbeginn nutzen)
- **[2] Recreate Finals – Endrunde neu erstellen**
  - Löscht nur die Endrunden-Matches und deren Ergebnisse, Vorrunde bleibt erhalten
  - ⚠️ Achtung: Endrunden-Ergebnisse gehen verloren
- **[3] Schedule – Zeitplan neu berechnen**
  - Berechnet Startzeiten und Feldzuordnungen neu (z.B. nach Änderung der Konfiguration)
  - Ergebnisse und Teams bleiben erhalten
- **[4] Assign Group Refs – Schiedsrichter für Gruppenspiele zuweisen**
  - Weist automatisch Schiedsrichter für alle Gruppenspiele zu
- **[5] Assign Final Refs – Schiedsrichter für Endrunde zuweisen**
  - Weist Schiedsrichter für alle noch offenen Finalrunden-Spiele zu
- **[6] Rename Team – Team umbenennen**
  - Interaktives Menü zum Umbenennen einzelner Teams (startet rename_team.py)
- **[7] Validate Config – Konfiguration prüfen**
  - Prüft die aktuelle Konfiguration auf Fehler, zeigt Statistiken und Warnungen (startet validate_config.py)
- **[8] Fill Results – Testdaten generieren**
  - Füllt alle Gruppenspiele mit Zufallsergebnissen (nur für Tests, überschreibt echte Ergebnisse)
- **[q] Beenden**
  - Beendet das Menü

**Weitere Möglichkeiten:**
- Die meisten Funktionen können auch direkt als Kommandozeilen-Argument ausgeführt werden, z.B.:
  - `python cli.py init`
  - `python cli.py recreate_finals`
  - `python cli.py schedule`
  - `python cli.py assign_group_refs`
  - `python cli.py assign_final_refs`
  - `python cli.py rename_team`
  - `python cli.py validate`
  - `python cli.py fill_results`

**Spezialskripte:**
- Für fortgeschrittene oder spezielle Aufgaben stehen weitere Python-Skripte zur Verfügung, z.B.:
  - `validate_config.py` – Validiert die Konfiguration unabhängig vom Menü
  - `fill_group_results.py` – Füllt Gruppenergebnisse mit Zufallswerten
  - `fix_match_results.py` – Korrigiert Gewinner/Verlierer nach Regeländerungen
  - `rename_team.py` – Teams außerhalb des Menüs umbenennen

**Hinweis:**
- Das Python-Interface ist vor allem für Administratoren und zur Vorbereitung/Test des Turniers gedacht. Die meisten Aufgaben können komfortabel über das Webinterface erledigt werden, für Spezialfälle und Massenänderungen ist das Python-CLI jedoch weiterhin sehr nützlich.

---

**Viel Erfolg beim Turnier!** 🏐🏆

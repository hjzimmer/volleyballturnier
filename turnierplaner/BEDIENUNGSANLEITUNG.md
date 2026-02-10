# 🏐 Volleyball Turnierverwaltung - Bedienungsanleitung

## 📋 Inhaltsverzeichnis
- [Projektübersicht](#projektübersicht)
- [Flexible Team-Anzahlen](#flexible-team-anzahlen)
- [Python Skripte](#python-skripte)
- [PHP Web-Interface](#php-web-interface)
- [Konfigurationsdateien](#konfigurationsdateien)
- [Workflows](#workflows)
- [⚠️ Sicherheitshinweise](#️-sicherheitshinweise)
- [Troubleshooting](#troubleshooting)

---

## Projektübersicht

Dieses System verwaltet ein Volleyball-Turnier mit:
- **Flexible Anzahl Teams** (4, 6, 8, 10, 12, 14, ... - gerade Anzahl)
- **2 Gruppen** (A & B) mit gleicher Teamanzahl
- **Gruppenphase** mit Round-Robin (jeder gegen jeden)
- **Endrunde** mit Halbfinale, Finale und Spiel um Platz 3
- **Schiedsrichter-Zuordnung**
- **Zeitplanung** auf mehreren Feldern
- **Web-Interface** für Live-Ergebniseingabe und Anzeige

**Technologie:**
- Backend: Python 3.x + SQLite
- Frontend: PHP 7+ mit Bootstrap 5

---

## Flexible Team-Anzahlen

### 💡 System unterstützt beliebige Team-Anzahlen!

Das System ist **nicht mehr auf 10 Teams festgelegt**. Du kannst jetzt Turniere mit 4, 6, 8, 10, 12, 14, 16 oder mehr Teams erstellen!

**Voraussetzungen:**
- ✅ Gerade Anzahl Teams (4, 6, 8, 10, ...)
- ✅ Gleiche Anzahl pro Gruppe (Gruppe A = Gruppe B)
- ✅ Minimum 4 Teams (2 pro Gruppe)

**Konfiguration:**

1. **Validierung vor dem Start:**
```bash
python validate_config.py
```

Zeigt:
- Anzahl Teams pro Gruppe
- Anzahl zu erwartender Matches
- Geschätzte Turnierdauer
- Fehler und Warnungen

2. **Team-Config für 8 Teams:**
```json
{
  "teams": [
    {"id": 1, "name": "Team 1", "group": "A"},
    {"id": 2, "name": "Team 2", "group": "A"},
    {"id": 3, "name": "Team 3", "group": "A"},
    {"id": 4, "name": "Team 4", "group": "A"},
    {"id": 5, "name": "Team 5", "group": "B"},
    {"id": 6, "name": "Team 6", "group": "B"},
    {"id": 7, "name": "Team 7", "group": "B"},
    {"id": 8, "name": "Team 8", "group": "B"}
  ]
}
```

3. **Nutze flexible Final-Config:**
```python
# In main.py:
from finals import create_final_matches

create_final_matches("final_config.json", group_tables)
```

**Anzahl Matches nach Team-Anzahl:**

| Teams | Pro Gruppe | Gruppenmatches | Final Start-ID | Geschätzte Dauer* |
|-------|------------|----------------|----------------|-------------------|
| 4     | 2          | 2              | 3              | ~15 min           |
| 6     | 3          | 6              | 7              | ~45 min           |
| 8     | 4          | 12             | 13             | ~1,5h             |
| 10    | 5          | 20             | 21             | ~2,5h             |
| 12    | 6          | 30             | 31             | ~3,8h             |
| 14    | 7          | 42             | 43             | ~5,3h             |
| 16    | 8          | 56             | 57             | ~7h               |

*Bei 2 Feldern parallel, 15 Min/Match inkl. Pausen

**Formel:** Matches pro Gruppe = n × (n-1) / 2, wobei n = Teams pro Gruppe

---

### 🔧 Technische Details: Flexible Match-IDs

Das System verwendet **dynamische Match-IDs** statt fester IDs. Dies ermöglicht Turniere mit beliebiger Team-Anzahl.

**Alte Methode (nicht mehr unterstützt):**
```json
{
  "matches": [
    { "id": 21, "round": "Halbfinale 1", "team1": "A_1", "team2": "B_2" },
    { "id": 22, "round": "Halbfinale 2", "team1": "B_1", "team2": "A_2" },
    { "id": 23, "round": "Finale", "team1": "W_21", "team2": "W_22" }
  ]
}
```
❌ Problem: Bei 12 Teams würden Finals bei ID 31 starten, nicht 21!

**Neue Methode (final_config.json):**
```json
{
  "matches": [
    { 
      "round": "Halbfinale 1",
      "match_key": "Halbfinale_1",
      "team1": "A_1", 
      "team2": "B_2"
    },
    { 
      "round": "Halbfinale 2",
      "match_key": "Halbfinale_2",
      "team1": "B_1", 
      "team2": "A_2"
    },
    { 
      "round": "Finale",
      "match_key": "Finale",
      "team1": "W_Halbfinale_1", 
      "team2": "W_Halbfinale_2"
    },
    { 
      "round": "Platz 3",
      "match_key": "Platz_3",
      "team1": "L_Halbfinale_1", 
      "team2": "L_Halbfinale_2"
    }
  ]
}
```

✅ Vorteile:
- IDs werden automatisch nach Gruppenmatches vergeben
- Funktioniert mit beliebiger Team-Anzahl
- Referenzen nutzen `match_key` statt feste ID (z.B. `W_Halbfinale_1` statt `W_21`)

---

## Python Skripte

### � **CLI - Command Line Interface**

#### Interaktiver Modus (EMPFOHLEN)
```bash
python cli.py
```

**Zeigt interaktives Menü mit allen verfügbaren Aktionen:**

```
======================================================================
           VOLLEYBALL TURNIER - VERWALTUNG
======================================================================

[GEFAEHRLICH - Loescht Daten]
  [1] Init - Turnier neu initialisieren
      Erstellt neue Datenbank, laedt Teams/Config, generiert Matches
      [!] WARNUNG: LOESCHT ALLE EXISTIERENDEN DATEN!

[SICHER - Aendert nur Zeitplanung/Zuordnungen]
  [2] Schedule - Zeitplan neu berechnen
      Berechnet Start-Zeiten und Feldzuordnungen neu

  [3] Assign Refs - Schiedsrichter zuweisen
      Weist automatisch Schiedsrichter fuer Gruppenspiele zu

  [4] Rename Team - Team umbenennen
      Interaktives Menue zum Umbenennen von Teams

[VALIDIERUNG]
  [5] Validate Config - Konfiguration pruefen
      Prueft team_config.json auf Fehler und zeigt Statistiken

[NUR ZUM TESTEN]
  [6] Fill Results - Testdaten generieren
      Fuellt alle Gruppenspiele mit Zufallsergebnissen
      [!] WARNUNG: Ueberschreibt existierende Ergebnisse!

  [q] Beenden
```

**Vorteile:**
- Übersichtliche Darstellung aller Funktionen
- Klare Kategorisierung (GEFÄHRLICH / SICHER / etc.)
- Kurze Beschreibung jeder Aktion
- Nach jeder Aktion zurück zum Menü
- Mehrere Aktionen nacheinander ausführbar

**Bedienung:**
1. `python cli.py` starten
2. Zahl (1-6) oder 'q' eingeben
3. Aktion wird ausgeführt
4. Enter drücken um zurück zum Menü zu gelangen
5. Nächste Aktion wählen oder 'q' zum Beenden

---

#### Direkter Modus (für Skripting)
```bash
python cli.py <action>
```

**Verfügbare Aktionen:**
- `python cli.py init` - Turnier initialisieren
- `python cli.py schedule` - Zeitplan berechnen
- `python cli.py assign_refs` - Schiedsrichter zuweisen
- `python cli.py rename_team` - Team umbenennen
- `python cli.py validate` - Konfiguration prüfen
- `python cli.py fill_results` - Testdaten generieren

**Vorteil:** Gut für Automatisierung und Skripte

---

### �🔴 **GEFÄHRLICH: Löscht alle Daten**

#### `cli.py init` (EMPFOHLEN)
```bash
python cli.py init
```

**Was es macht:**
1. Erstellt neue Datenbank in `data/tournament.db` (**ÜBERSCHREIBT ALTE!**)
2. Legt Teams aus `team_config.json` an
3. Erstellt Gruppen A & B
4. Generiert alle Gruppenspiele
5. Weist Schiedsrichter zu
6. Erstellt Endrunden-Matches aus `final_config.json`
7. Plant Zeiten und Felder ein aus `turnier_config.json`

**⚠️ WARNUNG:**
- Löscht **ALLE** existierenden Daten
- Überschreibt Teams, Gruppen, Ergebnisse
- **NUR VOR TURNIERBEGINN** ausführen!

**Alternative:** Einzelne Befehle direkt ausführen
```bash
python cli.py init
```

---

#### `main.py` (Alternative zu CLI)
```bash
python main.py
```

**Was es macht:** Identisch mit `cli.py init`

**💡 Tipp:** Verwende lieber `cli.py init` für eine einheitliche CLI-Schnittstelle

---

### 🟢 **SICHER: CLI-Befehle**

**💡 Tipp:** Nutze den interaktiven Modus `python cli.py` für eine übersichtliche Darstellung!

#### `cli.py schedule` - Zeitplanung
```bash
python cli.py schedule
```

**Was es macht:**
- Berechnet Zeiten und Feldzuordnungen **NEU**
- Plant automatisch Mittagspause ein
- Basiert auf `turnier_config.json`

**✅ SICHER weil:**
- Ändert **NUR** `start_time` und `field_number`
- Behält Teams, Gruppen, Ergebnisse unverändert
- Kann jederzeit ausgeführt werden

**Wann nutzen:**
- Änderung der Startzeit
- Andere Feldanzahl
- Anpassung der Spielzeiten/Pausen

---

#### `cli.py assign_refs` - Schiedsrichter zuweisen
```bash
python cli.py assign_refs
```

**Was es macht:**
- Weist automatisch Schiedsrichter für alle Gruppenspiele zu
- Stellt sicher, dass kein Team bei eigenem Spiel Schiedsrichter ist

**✅ SICHER:** Ändert nur `referee_team_id`

#### `cli.py validate` - Konfiguration validieren
```bash
python cli.py validate
```

**Was es macht:**
- Prüft `team_config.json` auf Fehler
- Zeigt Anzahl Teams, Gruppen, Matches
- Berechnet geschätzte Turnierdauer
- Warnt bei Problemen (doppelte IDs, ungleiche Gruppen, etc.)

**✅ SICHER:** Liest nur, ändert nichts

**Alternative:**
```bash
python validate_config.py
```

---

### 🟢 **SICHER: Team umbenennen**

#### `cli.py rename_team` (EMPFOHLEN)
```bash
python cli.py rename_team
```

**Was es macht:**
- Startet interaktives Menü zur Teamumbenennung
- Identisch mit `rename_team.py`

**Alternative:**
```bash
python rename_team.py
```

**Interaktives Menü:**
- `[1]` Team interaktiv umbenennen (einzelnes Team)
- `[2]` Alle Teams aus `team_config.json` aktualisieren
- `[q]` Beenden

**Was es macht:**
- Ändert **NUR** den Teamnamen
- Behält alle Matches, Ergebnisse, Gruppenzuordnung
- Zeigt Statistiken vor Umbenennung (Matches gespielt, Siege)
- Sicherheitsabfrage vor Änderung

**✅ SICHER weil:**
- Keine Ergebnisse werden geändert
- Keine Match-Zuordnungen betroffen
- Kann während des Turniers genutzt werden

**Anwendungsfall:**
```
Szenario: Team 3 kann nicht erscheinen, Team "Springer" ersetzt sie
→ python rename_team.py
→ [1] auswählen
→ Team-ID 3 eingeben
→ Neuen Namen "Springer" eingeben
→ Bestätigen
```

---

### 🟢 **SICHER: Config validieren**

#### `validate_config.py` (Alternative zu `cli.py validate`)
```bash
python validate_config.py
```

**Was es macht:**
- Prüft `team_config.json` auf Fehler
- Zeigt Anzahl Teams, Gruppen, Matches
- Berechnet geschätzte Turnierdauer
- Warnt bei Problemen (doppelte IDs, ungleiche Gruppen, etc.)

**✅ SICHER weil:**
- Liest nur, ändert nichts
- Kann jederzeit ausgeführt werden
- Empfohlen VOR `python main.py`

**Optionen:**
```bash
# Validierung mit Team-Liste
python validate_config.py --show-teams

# Alternative Config prüfen
python validate_config.py --config team_config_8teams.json
```

**Ausgabe:**
```
TEAM-KONFIGURATION VALIDIERUNG
========================================================
📊 Gesamt-Teams: 10
   ✓ Gerade Anzahl Teams

📋 Gruppenzuordnung:
   Gruppe A: 5 Teams
   Gruppe B: 5 Teams
   ✓ Gruppen sind ausgeglichen

📈 Turnier-Statistiken:
   • Teams pro Gruppe: 5
   • Matches pro Gruppe: 10
   • Gesamt Gruppenmatches: 20
   • Final-Matches beginnen bei ID: 21
   • Geschätzte Gruppenphase-Dauer: ~2h 30min (bei 2 Feldern)

✅ VALIDIERUNG ERFOLGREICH
```

---

### 🟡 **VORSICHTIG: Testdaten**

**💡 Tipp:** Option [6] im interaktiven Menü `python cli.py`

#### `cli.py fill_results` (EMPFOHLEN)
```bash
python cli.py fill_results
```

**Was es macht:**
- Füllt ALLE Gruppenspiele mit Zufallsergebnissen
- Nützlich zum Testen des Web-Interfaces

**⚠️ WARNUNG:**
- Überschreibt existierende Gruppenergebnisse
- Nur für Tests vor dem Turnier!
- **Nicht während echtem Turnier verwenden!**

**Alternative:**
```bash
python fill_group_results.py
```

**Interaktives Menü:**
- `[1]` Füllt ALLE Gruppenspiele mit Zufallsergebnissen
- `[2]` Löscht ALLE Gruppenergebnisse
- `[q]` Beenden

---

#### `fix_match_results.py`
```bash
python fix_match_results.py
```

**Was es macht:**
- Überprüft alle fertigen Matches
- Korrigiert winner_id/loser_id basierend auf Satzergebnissen
- Wendet Punktdifferenz-Regel für Playoffs an

**Wann nutzen:**
- Nach Bugfixes an Gewinner-Logik
- Zur Überprüfung der Datenintegrität
- Nach manuellen Datenbankänderungen

**✅ Sicher:** Korrigiert nur inkonsistente Daten

---

#### `check_match_21.py`
```bash
python check_match_21.py
```

**Was es macht:**
- Zeigt detaillierte Analyse von Match 21
- Prüft Satzergebnisse und Punktdifferenz
- Debug-Tool für Halbfinale 1

---

### 📚 **Hilfsdateien (nicht direkt ausführen)**

- **`db.py`**: Datenbankverbindung
- **`seed.py`**: Teams & Gruppen erstellen
- **`group_stage.py`**: Gruppenspiele generieren
- **`standings.py`**: Tabellen berechnen
- **`finals.py`**: Endrunden-Matches erstellen
- **`scheduling.py`**: Zeitplanung
- **`referees.py`**: Schiedsrichter-Zuordnung
- **`results.py`**: (falls vorhanden) Ergebnisverarbeitung

---

## PHP Web-Interface

### 🌐 **Haupt-Seiten**

**Einheitlicher Header:**
Alle PHP-Seiten zeigen oben:
- **Turniername** (aus `turnier_config.json` → `tournament_name`)
- **Logo** (optional, aus `turnier_config.json` → `logo_path`)
- Navigation zwischen den Seiten

#### `index.php` - Spielplan
**URL:** `http://localhost/turnierplaner/php/index.php`

**Anzeige:**
- Alle Matches nach Zeit sortiert
- 2-Spalten-Layout (Feld 1 & 2)
- Farbcodierung: Gewinner (grün), Verlierer (hellrot)
- Satzergebnisse bei fertigen Matches (1 oder 2 Sätze je nach Konfiguration)
- Zugeordnete Schiedsrichter (read-only)

**Features:**
- Gruppenbadges in Zeitleiste
- Hover-Effekt auf Match-Karten
- Übersichtliche Zeit-Darstellung

---

#### `groups.php` - Gruppentabellen
**URL:** `http://localhost/turnierplaner/php/groups.php`

**Anzeige:**
- **Neues Layout:** Pro Gruppe links die Spiele, rechts die Tabelle (2-spaltig)
- Gruppe A & B untereinander
- Sortierung: Satzpunkte → Direkter Vergleich → Punktdifferenz
- Spalten: Team, Satzpunkte, S/U/N, gewonnene/verlorene Sätze, Punktdifferenz

**Besonderheiten:**
- Volleyball-Punktsystem: 2-1-0 (Sieg-Unentschieden-Niederlage)
- Unentschieden = 1:1 Satzpunkte (beide Teams bekommen 1 Punkt pro gewonnenem Satz)
- Automatische Aktualisierung bei neuen Ergebnissen

---

#### `bracket.php` - Turnierplan Endrunde
**URL:** `http://localhost/turnierplaner/php/bracket.php`

**Anzeige:**
- Halbfinale 1 & 2
- Finale
- Spiel um Platz 3
- Einzelne Set-Spalten mit Farbcodierung:
  - 🟢 Grün = gewonnen
  - 🔴 Rot = verloren
  - 🟡 Gelb = unentschieden

**Besonderheiten:**
- Teams werden dynamisch aus Gruppenplatzierungen übernommen
- Zeigt TBD wenn Gruppen noch nicht abgeschlossen

---

#### `result_entry.php` - Ergebniseingabe
**URL:** `http://localhost/turnierplaner/php/result_entry.php`

**Funktionen:**

1. **Ergebnisse eintragen:**
   - Button "Eintragen" oder "Bearbeiten"
   - Modal-Dialog mit 1 oder 2 Sätzen (abhängig von `sets_per_match` in `turnier_config.json`)
   - Speichern → automatische Gewinner-Berechnung

2. **Schiedsrichter zuweisen:**
   - Dropdown in separater Spalte
   - Spielende Teams automatisch deaktiviert
   - AJAX-Update (speichert sofort)

3. **Ergebnisse löschen:**
   - Button "Löschen" bei fertigen Matches
   - Sicherheitsabfrage
   - Setzt Match auf "Offen" zurück

**Status-Anzeige:**
- ✓ Beendet (grün) + Satzergebnisse
- Offen (gelb)
- Warten auf Teams (grau)

---

#### `table.php` - Turnier-Übersicht
**URL:** `http://localhost/turnierplaner/php/table.php`

**Anzeige:**
- **Turnierstatus** (volle Breite oben): Prozentsatz abgeschlossener Matches
- **Letzte 4 Spiele:** Farbcodierung für Gewinner (grün) und Verlierer (rot)
- **Nächste Spiele:** Anstehende Matches
- **Tabellen/Platzierungen:** 
  - Während Gruppenphase: Zeigt beide Gruppentabellen
  - Nach Gruppenphase: Zeigt finale Platzierungen 1-10

---

#### `schedule.php`
**URL:** `http://localhost/turnierplaner/php/schedule.php`

Alternative Spielplanansicht (falls eigenständig vorhanden)

---

### 🔧 **Backend-Dateien (AJAX)**

#### `update_referee.php`
- Wird per AJAX von `result_entry.php` aufgerufen
- Aktualisiert `referee_team_id` für ein Match
- Keine direkte Nutzung nötig

#### `db.php`
- Datenbankverbindung für PHP
- PDO mit SQLite

---

## Konfigurationsdateien

### `team_config.json`
```json
{
  "teams": [
    {"id": 1, "name": "Team 1", "group": "A"},
    {"id": 2, "name": "Team 2", "group": "A"},
    ...
  ]
}
```

**Parameter:**
- `id`: Eindeutige Team-ID (1-10)
- `name`: Teamname (frei wählbar)
- `group`: Gruppenzuordnung ("A" oder "B")

**Verwendung:**
1. **Vor Turnier:** Teamnamen anpassen, dann `python main.py` ausführen
2. **Während Turnier:** Einzelne Teams mit `python rename_team.py` umbenennen

**Beispiel:**
```json
{
  "teams": [
    {"id": 1, "name": "SV Musterhausen", "group": "A"},
    {"id": 2, "name": "FC Beispiel", "group": "A"},
    {"id": 3, "name": "TSV Turnier", "group": "A"},
    ...
  ]
}
```

---

### `turnier_config.json`
```json
{
  "tournament_name": "3. Otti Fun Cup - 2026",
  "logo_path": "data/vsvlogo.jpg",
  "tournament_start": "2026-04-25T09:00",
  "fields": 2,
  "sets_per_match": 2,
  "set_minutes": 9,
  "pause_between_sets": 1,
  "pause_between_matches": 6,
  "lunch_break": {
    "start": "2026-04-25T12:15",
    "duration_minutes": 25
  },
  "result_entry_password": "turnier2026"
}
```

**Parameter:**
- `tournament_name`: **Name des Turniers** - Wird im Header aller PHP-Seiten angezeigt
- `logo_path`: **Logo-Pfad** (optional) - Pfad zum Logo-Bild, relativ zum Hauptverzeichnis (z.B. `"data/vsvlogo.jpg"`). Wird im Header aller PHP-Seiten angezeigt. Wenn nicht vorhanden/leer, wird nur der Turniername gezeigt.
- `sets_per_match`: **Sätze pro Match** (1 oder 2)
  - `2`: Klassisches Volleyball mit 2 Sätzen (Standard)
  - `1`: Schnelleres Turnier mit nur 1 Satz pro Match
  - Beeinflusst:
    - Ergebniseingabe-Formular (`result_entry.php`)
    - Match-Dauer-Berechnung (Zeitplanung)
    - Testdaten-Generierung (`fill_group_results.py`)
- `tournament_start`: Startzeit des Turniers (Format: ISO 8601)
- `fields`: Anzahl paralleler Felder (meist 2)
- `set_minutes`: Dauer eines Satzes in Minuten
- `pause_between_sets`: Pause zwischen den Sätzen in Minuten (wird bei `sets_per_match=1` ignoriert)
- `pause_between_matches`: Pause nach jedem Match in Minuten
- `lunch_break`: Automatische Mittagspause
  - `start`: Bevorzugte Startzeit
  - `duration_minutes`: Dauer in Minuten
- `result_entry_password`: Passwort für die Ergebniseingabe (optional, kann leer bleiben)

**Änderung anwenden:**
```bash
# Nur Zeitplanung neu berechnen (bei Änderung von Zeiten/Pausen):
python cli.py schedule

# Komplette Neuinitialisierung (bei Änderung von sets_per_match oder tournament_name):
# ⚠️ WARNUNG: Löscht alle Ergebnisse!
python cli.py init
```

**Logo einrichten:**
1. Logo-Datei ins `data/` Verzeichnis kopieren (z.B. `data/vereinslogo.png`)
2. In `turnier_config.json` den Pfad eintragen: `"logo_path": "data/vereinslogo.png"`
3. Logo wird automatisch im Header aller PHP-Seiten angezeigt
4. Unterstützte Formate: PNG, JPG, GIF, SVG

---

### `final_config.json`
```json
{
  "matches": [
    { 
      "round": "Halbfinale 1",
      "match_key": "Halbfinale_1",
      "team1": "A_1", 
      "team2": "B_2"
    },
    { 
      "round": "Halbfinale 2",
      "match_key": "Halbfinale_2",
      "team1": "B_1", 
      "team2": "A_2"
    },
    { 
      "round": "Finale",
      "match_key": "Finale",
      "team1": "W_Halbfinale_1", 
      "team2": "W_Halbfinale_2"
    },
    { 
      "round": "Platz 3",
      "match_key": "Platz_3",
      "team1": "L_Halbfinale_1", 
      "team2": "L_Halbfinale_2"
    }
  ]
}
```

**Parameter:**
- `round`: Anzeigename des Matches (Halbfinale 1, Halbfinale 2, Finale, Platz 3)
- `match_key`: Eindeutiger Schlüssel für Referenzen (z.B. "Halbfinale_1")
- `team1`/`team2`: Team-Platzhalter
  - `A_1`, `A_2`: 1. und 2. Platz aus Gruppe A
  - `B_1`, `B_2`: 1. und 2. Platz aus Gruppe B
  - `W_Halbfinale_1`: Gewinner von Halbfinale 1
  - `L_Halbfinale_1`: Verlierer von Halbfinale 1

**Vorteile:**
- Funktioniert mit beliebiger Team-Anzahl (4, 6, 8, 10, 12, ...)
- Match-IDs werden automatisch vergeben
- Keine Anpassung bei Team-Anzahl-Änderung nötig

**⚠️ Änderung:** Nur VOR Turnierbeginn, erfordert `python main.py`

---

### `schema.sql`
Datenbankschema mit allen Tabellen. Wird von `db.py` verwendet.

---

## Workflows

### 🎬 **Vor dem Turnier: Erstmalige Einrichtung**

1. **Teams anpassen:**
   
   **Option A - Über team_config.json (empfohlen):**
   ```json
   // team_config.json bearbeiten
   {
     "teams": [
       {"id": 1, "name": "SV Musterhausen", "group": "A"},
       {"id": 2, "name": "FC Beispiel", "group": "A"},
       ...
     ]
   }
   ```

   **Option B - Direkt in seed.py:**
   ```python
   # In seed.py Zeile ~8:
   teams = [
       "Team A", "Team B", ...
   ]
   ```

2. **Konfiguration anpassen:**
   - `turnier_config.json` bearbeiten:
     - `tournament_name`: Turnierbezeichnung
     - `logo_path`: Pfad zum Vereins-/Turnier-Logo (optional)
     - `sets_per_match`: 1 oder 2 Sätze pro Match
     - `tournament_start`: Startzeit
     - Spielfeld-/Pausen-Zeiten
   - `final_config.json` prüfen (bereits optimal konfiguriert)

3. **Datenbank initialisieren:**
   
   **Interaktiver Modus (empfohlen):**
   ```bash
   python cli.py
   # Im Menü Option [1] wählen
   ```
   
   **Direkter Modus:**
   ```bash
   python cli.py init
   ```
   
   **Alternative:**
   ```bash
   python main.py
   ```

4. **Überprüfung:**
   - Öffne `index.php` → Spielplan prüfen
   - Öffne `bracket.php` → Endrunde prüfen
   - Zeitplan korrekt?

5. **Optional: Testdaten:**
   
   **Interaktiver Modus:**
   ```bash
   python cli.py
   # Im Menü Option [6] wählen
   ```
   
   **Direkter Modus:**
   ```bash
   python cli.py fill_results
   ```
   - Füllt automatisch alle Gruppenspiele mit Zufallsergebnissen
   - Web-Interface prüfen
   - Vor echtem Turnier: Menü Option [1] oder `python cli.py init` ausführen (löscht Testdaten)

---

### 🏐 **Während des Turniers: Ergebniseingabe**

1. **Ergebnisse eintragen:**
   - `result_entry.php` öffnen
   - Match suchen
   - "Eintragen" klicken
   - Satzergebnisse eingeben
   - Speichern → Gewinner wird automatisch berechnet

2. **Schiedsrichter zuweisen:**
   - Dropdown in der Tabelle
   - Team auswählen → speichert automatisch

3. **Tabellen überprüfen:**
   - `groups.php` → Gruppentabellen
   - `bracket.php` → Endrunde (Teams werden automatisch übernommen)

4. **Spielplan anzeigen:**
   - `index.php` → für Publikum/Teams
   - Farbcodierung zeigt fertige Matches

---

### 🔧 **Zeitplan anpassen (während Turnier möglich)**

**Szenario:** Turnier läuft später, oder Pause ändern

**Interaktiver Modus:**
```bash
python cli.py
# Option [2] wählen
```

**Direkter Modus:**
```bash
# 1. turnier_config.json bearbeiten
# 2. Neu berechnen:
python cli.py schedule
# 3. Seite aktualisieren
```

**✅ SICHER:** Ändert nur Zeiten, keine Ergebnisse!

---

### 👥 **Team ersetzen (während Turnier möglich)**

**Szenario:** Team kann nicht erscheinen, Ersatz-Team kommt

**Interaktiver Modus (empfohlen):**
```bash
python cli.py
# Option [4] wählen
# → [1] auswählen für einzelnes Team
# → Team-ID eingeben (z.B. 3)
# → Neuen Namen eingeben (z.B. "Springer-Team")
# → Bestätigen
```

**Direkter Modus:**

**Option 1 - Einzelnes Team umbenennen:****
```bash
python cli.py rename_team
# → [1] auswählen
# → Team-ID eingeben (z.B. 3)
# → Neuen Namen eingeben (z.B. "Springer-Team")
# → Bestätigen
```

**Option 2 - Mehrere Teams aus Config:**
```bash
# 1. team_config.json bearbeiten
# 2. Skript ausführen:
python cli.py rename_team
# → [2] auswählen
# → Alle geänderten Teams werden aktualisiert
```

**Was passiert:**
- ✅ Teamname wird überall geändert
- ✅ Alle bisherigen Ergebnisse bleiben erhalten
- ✅ Gruppenzuordnung bleibt gleich
- ✅ Match-Planung bleibt gleich
- ✅ Schiedsrichter-Zuordnungen bleiben

**Anzeige:**
- Spielplan zeigt neuen Namen
- Tabelle zeigt neuen Namen
- Turnierbaum zeigt neuen Namen
- Historie bleibt mit neuem Namen

---

### 🐛 **Nach Bugfix: Ergebnisse korrigieren**

Wenn die Gewinner-Logik gefixt wurde:

```bash
python fix_match_results.py
```

Zeigt alle korrigierten Matches und aktualisiert `winner_id`/`loser_id`.

---

## ⚠️ Sicherheitshinweise

### 🔴 **NIEMALS während eines Turniers:**

#### ❌ `python cli.py init` / `python main.py`
- **LÖSCHT ALLES!**
- Neue Datenbank
- Alle Ergebnisse weg
- Nur vor Turnierbeginn!

#### ❌ `python cli.py fill_results` / `python fill_group_results.py`
- Überschreibt/löscht Gruppenergebnisse
- Nur für Tests vor dem Turnier!

#### ❌ Datei `data/tournament.db` löschen oder ersetzen
- Alle Daten verloren
- Keine Wiederherstellung möglich

#### ❌ Schema ändern (schema.sql)
- Kann Datenbank korumpieren
- Nur mit Backup

---

### 🟢 **SICHER während Turnier:**

#### ✅ `python cli.py schedule`
- Nur Zeitplanung
- Ergebnisse bleiben erhalten

#### ✅ `python fix_match_results.py`
- Korrigiert nur winner_id basierend auf Sets
- Keine Set-Daten ändern

#### ✅ PHP Web-Interface nutzen:
- `result_entry.php` → Ergebnisse eintragen/bearbeiten/löschen
- `index.php`, `groups.php`, `bracket.php` → Nur Anzeige

#### ✅ `turnier_config.json` ändern + `cli.py schedule`
- Zeitplan anpassen
- Ergebnisse bleiben

---

### 💾 **Backup empfohlen:**

**Vor dem Turnier:**
```bash
cd data
copy tournament.db tournament_backup.db
```

**Wiederherstellen:**
```bash
cd data
copy tournament_backup.db tournament.db
```

**Backup während Turnier:**
- Regelmäßig `data/tournament.db` kopieren
- Bei kritischen Spielen (Halbfinale, Finale)

---

## Troubleshooting

### Problem: Zeiten stimmen nicht

**Lösung:**
1. `turnier_config.json` prüfen und anpassen
2. `python cli.py schedule` ausführen
3. Browser-Cache leeren (Strg+F5)

---

### Problem: Team muss während Turnier ersetzt werden

**Szenario:** Team 5 kann nicht erscheinen, "Springer-Team" ersetzt sie

**Lösung:**
```bash
python cli.py rename_team
```
- Wähle `[1]` für interaktive Umbenennung
- Gib Team-ID `5` ein
- Gib neuen Namen `Springer-Team` ein
- Bestätige mit `ja`

**Ergebnis:**
- Alle bisherigen Ergebnisse von Team 5 erscheinen jetzt unter "Springer-Team"
- Zukünftige Matches zeigen "Springer-Team"
- Gruppenzuordnung bleibt gleich
- Kein Datenverlust

---

### Problem: Mehrere Teamnamen falsch geschrieben

**Lösung:**
1. `team_config.json` bearbeiten und korrekte Namen eintragen:
   ```json
   {"id": 3, "name": "SV Musterhausen", "group": "A"}
   ```

2. Batch-Update ausführen:
   ```bash
   python cli.py rename_team
   # → [2] auswählen
   ```

**Ergebnis:** Alle Teams aus der Config werden auf einmal aktualisiert

---

### Problem: Falscher Gewinner angezeigt

**Ursache:** Alte Gewinner-Logik hatte Bug (1:1 Satzstand)

**Lösung:**
```bash
python fix_match_results.py
```

---

### Problem: Teams in Endrunde fehlen (TBD)

**Ursache:** Gruppenphase noch nicht abgeschlossen

**Lösung:**
- Alle Gruppenspiele eintragen
- `bracket.php` aktualisieren
- Teams werden automatisch übernommen

---

### Problem: Schiedsrichter-Dropdown leer

**Ursache:** Keine Teams in Datenbank

**Lösung:**
- `python main.py` ausführen (wenn Turnier noch nicht gestartet)
- Oder Datenbank prüfen: `sqlite3 data/tournament.db` → `SELECT * FROM teams;`

---

### Problem: PHP Fehler "no such table"

**Ursache:** Datenbank nicht initialisiert

**Lösung:**
```bash
python cli.py init
```

---

### Problem: Ergebnis lässt sich nicht eintragen

**Prüfen:**
- Sind beide Teams bekannt? (nicht TBD)
- JavaScript-Fehler in Browser-Konsole?
- PHP-Fehler in Server-Logs?

**Lösung:**
- Browser-Konsole öffnen (F12)
- POST-Request in Network-Tab prüfen
- `result_entry.php` Zeile ~40-90 prüfen

---

### Problem: Unentschieden in Playoffs

**Regel:** Bei 1:1 Satzpunkten entscheidet Punktdifferenz

**Beispiel:**
- Satz 1: 10:8 (Team A +2)
- Satz 2: 10:11 (Team B +1)
- **Gesamt:** 20:19 → **Team A gewinnt** (+1 Differenz)

Wird automatisch berechnet in `result_entry.php`.

---

### Problem: Mittagspause falsch geplant

**Anpassen:**
1. `turnier_config.json`:
   ```json
   "lunch_break": {
     "start": "2026-02-09T12:30:00",  // Neue Zeit
     "duration_minutes": 45             // Neue Dauer
   }
   ```
2. `python cli.py schedule`

**Regel:** Pause wird eingeplant wenn:
- Alle Felder gleichzeitig frei
- Bevorzugte Zeit erreicht/überschritten

---

## 📊 Datenbank-Struktur

### Wichtige Tabellen:

**teams**
- `id`, `name`

**groups**
- `id`, `name` (A, B)

**group_teams**
- `group_id`, `team_id`

**matches**
- `id`, `round`, `phase`, `group_id`
- `team1_id`, `team2_id`
- `winner_id`, `loser_id`, `finished`
- `start_time`, `field_number`
- `referee_team_id`

**sets**
- `match_id`, `set_number`
- `team1_points`, `team2_points`

---

## 🎯 Gewinner-Ermittlung

### Gruppenphase:
**Mit 2 Sätzen (sets_per_match=2):**
- Satzstand 2:0 → klarer Sieger
- Satzstand 1:1 → **Unentschieden** (beide Teams bekommen je 1 Satzpunkt)
- Satzstand 0:0 (beide Sätze unentschieden) → Unentschieden

**Mit 1 Satz (sets_per_match=1):**
- Höhere Punktzahl = Sieger
- Gleiche Punktzahl → Unentschieden

### Playoffs (Halbfinale, Finale, Platz 3):
**Mit 2 Sätzen (sets_per_match=2):**
- Satzstand 2:0 oder 0:2 → klarer Sieger
- Satzstand 1:1 → **Punktdifferenz entscheidet**
  - Gesamt-Punkte über beide Sätze
  - Höhere Punktzahl = Gewinner

**Mit 1 Satz (sets_per_match=1):**
- Höhere Punktzahl = Sieger
- Bei Gleichstand: Geringeres Team-ID gewinnt (Fallback)

### Tabellen-Sortierung:
1. **Satzpunkte** (2 pro Satz-Sieg, 1 pro Unentschieden, 0 bei Niederlage)
2. **Direkter Vergleich** (bei Gleichstand)
3. **Punktdifferenz** (Punkte geschossen - Punkte kassiert)

---

## 📞 Support

Bei Problemen:
1. Diese Anleitung prüfen
2. `fix_match_results.py` zur Datenprüfung
3. Backup einspielen falls nötig
4. Entwickler kontaktieren

---

## ✅ Checkliste für Turniertag

**Vorbereitung:**
- [ ] Backup von `data/tournament.db` erstellen
- [ ] Turnier initialisiert (`python cli.py` → Option [1])
- [ ] Zeitplan geprüft (Option [2] falls Anpassung nötig)
- [ ] Konfiguration validiert (Option [5])

**Equipment:**
- [ ] `index.php` auf Beamer/Monitor (für Publikum)
- [ ] `result_entry.php` auf Eingabe-PC
- [ ] `groups.php` und `bracket.php` bereithalten
- [ ] Laptop mit `python cli.py` bereit (für Zeitanpassungen, Option [2])
- [ ] Diese Anleitung ausdrucken/griffbereit

**Während des Turniers:**

**Viel Erfolg beim Turnier! 🏐🏆**

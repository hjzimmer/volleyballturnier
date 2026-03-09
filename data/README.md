# Data-Verzeichnis

Dieses Verzeichnis enthält die Turnier-Datenbank.

## Inhalt

- `tournament.db` - SQLite-Datenbank mit allen Turnierdaten
  - Teams und Gruppen
  - Matches (Gruppenphase und Finalrunde)
  - Ergebnisse (Sets und Punkte)
  - Schiedsrichterzuordnungen
  - Zeitplanung

## Erstellen der Datenbank

Die Datenbank wird automatisch beim ersten Ausführen von `main.py` erstellt:

```bash
python main.py
```

## Backup

**⚠️ Wichtig:** Erstelle regelmäßig Backups während des Turniers!

```bash
# Windows
copy tournament.db tournament_backup.db

# Oder mit Zeitstempel
copy tournament.db tournament_backup_%date:~-4,4%%date:~-7,2%%date:~-10,2%.db
```

## Wiederherstellung

Falls etwas schiefgeht:

```bash
copy tournament_backup.db tournament.db
```

## Sicherheit

- Dieses Verzeichnis enthält alle Turnierdaten
- Nicht in die Versionskontrolle einchecken (siehe `.gitignore`)
- Regelmäßig sichern!

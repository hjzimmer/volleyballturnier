# Volleyball Turnierplaner – Docker Umgebung

Dieses Projekt stellt eine Docker-Umgebung für den Volleyball-Turnierplaner bereit. Die Anwendung läuft mit PHP und SQLite und ist für lokale Entwicklung und Deployment gedacht.

## Verzeichnisstruktur

- `Docker/` – Dockerfile und docker-compose.yml
- `php/` – PHP-Webanwendung (Webroot)
- `data/` – Datenverzeichnis (z.B. SQLite-Datenbank)
- `.` – Python-Skripte, CLI-Tools, Konfigurationen

## Schnellstart

1. **Voraussetzungen:**
   - Docker und Docker Compose installiert

2. **Container bauen und starten:**
   ```sh
   cd Docker
   docker-compose down
   docker-compose up --build
   ```

3. **Zugriff auf die Anwendung:**
   - Im Browser öffnen: [http://localhost:8000](http://localhost:8000)
   - Das Webroot ist das Verzeichnis `php` im Container.
   - Die Timer Seite aufrufen mit: [http://localhost:8000/timer](http://localhost:8000/timer)

4. **Container stoppen:**
   ```sh
   docker-compose down
   ```

## Nützliche Kommandos

- **Bash im laufenden Container öffnen:**
  ```sh
  docker exec -it turnierplaner bash
  ```

- **Manuelles Image-Build (falls nötig):**
  ```sh
  docker build -t turnierplaner -f Docker/Dockerfile ..
  ```

## Hinweise

- Alle Datei einschließlich Datenbank sind im Dockercontainer intern. Jedes stoppen bewirkt den Verlust der Spielstände.
- Für produktiven Einsatz das docker-compose.yml anpassen und die Daten aus dem Host System einbinden.
- Änderungen an den Quellverzeichnissen werden dann durch Docker-Volumes direkt im Container sichtbar.
- Die URL `/timer` kann per Apache-Alias auf das Timer-Verzeichnis gemappt werden (siehe Dockerfile).
- Die Datei `index.php` kann zur Weiterleitung auf andere PHP-Dateien genutzt werden:
  ```php
  <?php
  header("Location: countdown.php");
  exit;
  ```

## Support

Bei Problemen oder Fragen bitte im Projekt nachsehen oder ein Issue eröffnen.
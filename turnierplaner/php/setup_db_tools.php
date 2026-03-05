<?php

// Initialisiert die Datenbankstruktur (Tabellen anlegen, wie Python-Version)
function init_db(&$db) {

    // Standardpfad wie in Python
    $dbDir = __DIR__ . '\..\data';
    $dbFile = $dbPath ?? ($dbDir . '\tournament.db');

    // data-Verzeichnis anlegen, falls nicht vorhanden
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0777, true);
    }
    
    // Datenbankdatei verwerfen und komplett neu anlegen
    $dbDir = __DIR__ . '/../data';
    $dbFile = $dbDir . '/tournament.db';
    if (file_exists($dbFile)) {
        // Verbindung schließen
        $db = null;
        gc_collect_cycles();
        unlink($dbFile);
    }
    // Neue DB-Verbindung
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = file_get_contents(__DIR__ . '/../schema.sql');
    $db->exec($sql);
    // Optional: DB schließen (PDO macht das automatisch beim Garbage Collection)
    return $db;
}

// Teams in die Datenbank einfügen (Seed, wie seed.py)
function seed_teams($db) {

    $configFile = __DIR__ . '/../team_config.json';
    $teams = [];
    if (file_exists($configFile)) {
        try {
            $config = json_decode(file_get_contents($configFile), true);
            if (isset($config['teams'])) {
                foreach ($config['teams'] as $team) {
                    $teams[] = [$team['id'], $team['name']];
                }
            }
            // Optional: echo "[OK] Lade ".count($teams)." Teams aus team_config.json\n";
        } catch (Exception $e) {
            // Optional: echo "[WARNUNG] Fehler beim Laden von team_config.json: ".$e->getMessage()."\n";
            // Optional: echo "[OK] Verwende Standard-Teams\n";
            $teams = [];
        }
    }
    if (empty($teams)) {
        // Optional: echo "[WARNUNG] team_config.json nicht gefunden oder ungültig\n";
        // Optional: echo "[OK] Verwende Standard-Teams\n";
        for ($i = 1; $i <= 3; $i++) {
            $teams[] = [$i, "Team $i"];
        }
    }

    $db->exec("DELETE FROM teams");
    $stmt = $db->prepare("INSERT INTO teams (id, name) VALUES (?, ?)");
    foreach ($teams as $team) {
        $stmt->execute($team);
    }
    // Optional: echo "Teams initialisiert.\n";
}

// Gruppen und Gruppenzuordnung in die Datenbank einfügen (Seed, wie seed.py)
function seed_groups($db) {
    $configFile = __DIR__ . '/../turnier_config.json';
    $db->exec("DELETE FROM groups");
    $db->exec("DELETE FROM group_teams");

    if (file_exists($configFile)) {
        try {
            $config = json_decode(file_get_contents($configFile), true);
            $phases = $config['phases'] ?? [];
            $startphase = null;
            // Finde Startphase (erste Phase mit nur Integer-Teamzuordnung)
            foreach ($phases as $phase) {
                $allInt = true;
                foreach ($phase['groups'] ?? [] as $group) {
                    foreach ($group['teams'] ?? [] as $team) {
                        if (!is_int($team)) {
                            $allInt = false;
                            break 2;
                        }
                    }
                }
                if ($allInt) {
                    $startphase = $phase;
                    break;
                }
            }
            // Lege alle Gruppen aus allen Phasen an (IDs aus Config)
            $group_ids = [];
            foreach ($phases as $phase) {
                foreach ($phase['groups'] ?? [] as $group) {
                    $group_id = $group['id'];
                    if (in_array($group_id, $group_ids)) continue;
                    $group_ids[] = $group_id;
                    $stmt = $db->prepare("INSERT INTO groups (id, name, phase_name) VALUES (?, ?, ?)");
                    $stmt->execute([$group_id, $group['name'], $phase['name']]);
                    // Teams für Startphase zuordnen
                    if ($startphase && in_array($group, $startphase['groups'])) {
                        foreach ($group['teams'] ?? [] as $team_id) {
                            $stmt2 = $db->prepare("INSERT INTO group_teams (group_id, team_id) VALUES (?, ?)");
                            $stmt2->execute([$group_id, $team_id]);
                        }
                    }
                }
            }
            // Optional: echo "[OK] ".count($group_ids)." Gruppen angelegt. Teams für Startphase zugeordnet.\n";
        } catch (Exception $e) {
            // Optional: echo "[WARNUNG] Fehler beim Laden der Gruppen: ".$e->getMessage()."\n";
        }
    } else {
        // Optional: echo "[WARNUNG] turnier_config.json nicht gefunden. Keine Gruppen importiert.\n";
    }
    // Optional: echo "Gruppen initialisiert.\n";
}

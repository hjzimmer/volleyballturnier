<?php
require_once 'setup_group_stage_tools.php';
require_once 'helpFunctions.php';

// Finalspiele anlegen (analog zu create_final_matches aus finals.py)
function create_final_matches($db, $config_path, $group_tables = null) {
    // Config laden
    $config = json_decode(file_get_contents($config_path), true);

    // Nächste freie Match-ID nach Gruppenphase
    $stmt = $db->query("SELECT MAX(id) as max_id FROM matches WHERE phase = 'group'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $next_id = $row && $row['max_id'] ? ((int)$row['max_id']) + 1 : 1;

    $match_id_map = [];
    $phases = isset($config['phases']) ? $config['phases'] : [];
    $all_matches = [];

    // Sammle alle Matches aus allen Phasen mit einem "matches"-Array
    foreach ($phases as $phase) {
        if (isset($phase['matches'])) {
            foreach ($phase['matches'] as $m) {
                $all_matches[] = [
                    'phase_name' => $phase['name'],
                    'match' => $m
                ];
            }
        }
    }

    foreach ($all_matches as $idx => $item) {
        $m = $item['match'];
        $team1 = isset($m['team1']) ? $m['team1'] : null;
        $team2 = isset($m['team2']) ? $m['team2'] : null;
        $is_real_match = ($team1 !== null || $team2 !== null);
        $match_key = isset($m['id']) ? $m['id'] : (isset($m['name']) ? $m['name'] : ("Match_" . $idx));
        if (!$is_real_match) {
            $match_id_map[$match_key] = null;
            continue;
        }
        $match_id = $next_id;
        $next_id++;
        $match_id_map[$match_key] = $match_id;
        $winner_placement = isset($m['winner_placement']) ? $m['winner_placement'] : null;
        $loser_placement = isset($m['loser_placement']) ? $m['loser_placement'] : null;

        // Team-Referenzen und IDs
        if (is_array($team1)) {
            $team1_ref = json_encode($team1, JSON_UNESCAPED_UNICODE);
            $team1_id = -1;
        } elseif (is_int($team1)) {
            $team1_ref = null;
            $team1_id = $team1;
        } else {
            $team1_ref = is_string($team1) ? $team1 : null;
            $team1_id = -1;
        }
        if (is_array($team2)) {
            $team2_ref = json_encode($team2, JSON_UNESCAPED_UNICODE);
            $team2_id = -1;
        } elseif (is_int($team2)) {
            $team2_ref = null;
            $team2_id = $team2;
        } else {
            $team2_ref = is_string($team2) ? $team2 : null;
            $team2_id = -1;
        }
        $stmt = $db->prepare('INSERT INTO matches (id, phase, group_id, round, team1_id, team2_id, team1_ref, team2_ref, referee_team_id, winner_placement, loser_placement) VALUES (?, "final", ?, ?, ?, ?, ?, ?, 0, ?, ?)');
        $stmt->execute([
            $match_id,
            isset($m['id']) ? $m['id'] : null,
            isset($m['name']) ? $m['name'] : 'Unbenannt',
            $team1_id,
            $team2_id,
            $team1_ref,
            $team2_ref,
            $winner_placement,
            $loser_placement
        ]);
    }
    return $match_id_map;
}

function prepare_all_group_matches_and_tables($db, $configPath) {
    // Konfiguration laden
    $config = json_decode(file_get_contents($configPath), true);
    $phases = $config['phases'] ?? [];

    // Für jede Phase mit Gruppen: Gruppenspiele anlegen
    foreach ($phases as $phase) {
        if (isset($phase['groups'])) {
            // echo "Erzeuge Gruppenspiele für Phase: " . $phase['name'] . "\\n";
            generate_interleaved_group_matches_for_phase($db, $phase);
        }
    }

    // Dynamisch alle Gruppen-Tabellen berechnen
    $group_tables = [];
    foreach ($phases as $phase) {
        if (isset($phase['groups'])) {
            foreach ($phase['groups'] as $group) {
                $group_id = $group['id'];
                $standings = calculateGroupStandings($db, $group_id);
                $group_tables[$group_id] = $standings;
            }
        }
    }
    // $db->close(); // PDO schließt automatisch
    return $group_tables;
}

// Zeitliche Einplanung aller Matches (analog zu schedule_all_matches aus scheduling.py)
function schedule_all_matches($db, $config_path) {
    $cfg = json_decode(file_get_contents($config_path), true);
    $start_time = new DateTime($cfg["tournament_start"]);
    $fields = $cfg["fields"];
    $sets_per_match = isset($cfg["sets_per_match"]) ? $cfg["sets_per_match"] : 2;
    $set_time = new DateInterval('PT' . $cfg["set_minutes"] . 'M');
    $set_pause = new DateInterval('PT' . $cfg["pause_between_sets"] . 'M');
    $match_pause = new DateInterval('PT' . $cfg["pause_between_matches"] . 'M');
    $match_duration = clone $set_time;
    $match_duration->i = $set_time->i * $sets_per_match + $set_pause->i * ($sets_per_match - 1) + $match_pause->i;
    $lunch_start = new DateTime($cfg["lunch_break"]["start"]);
    $lunch_duration = new DateInterval('PT' . $cfg["lunch_break"]["duration_minutes"] . 'M');

    $matches = $db->query("SELECT id FROM matches ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $field_available_at = array_fill(0, $fields, clone $start_time);
    $lunch_taken = false;

    foreach ($matches as $match) {
        // Feld mit frühestem Zeitpunkt wählen
        $field = array_keys($field_available_at, min($field_available_at))[0];
        $start = $field_available_at[$field];

        // Lunch-Break prüfen
        $earliest = min($field_available_at);
        $latest = max($field_available_at);
        $all_fields_free = ($latest->getTimestamp() - $earliest->getTimestamp()) < 60;
        $past_lunch_time = $earliest >= $lunch_start;
        if (!$lunch_taken && $all_fields_free && $past_lunch_time) {
            $lunch_end = (clone $earliest)->add($lunch_duration);
            foreach ($field_available_at as &$fa) $fa = clone $lunch_end;
            unset($fa);
            $start = $lunch_end;
            $lunch_taken = true;
            // echo "Lunch-Break eingeplant: {$earliest->format('H:i')} - {$lunch_end->format('H:i')}\n";
        }

        $db->prepare("UPDATE matches SET start_time = ?, field_number = ? WHERE id = ?")
            ->execute([$start->format('Y-m-d H:i:s'), $field + 1, $match["id"]]);
        $field_available_at[$field] = (clone $start)->add(new DateInterval('PT' . ($sets_per_match * $cfg["set_minutes"] + ($sets_per_match - 1) * $cfg["pause_between_sets"] + $cfg["pause_between_matches"]) . 'M'));
    }
    // $db->commit();
    // $db->close();
    // echo count($matches) . " Matches zeitlich eingeplant.\n";
}

// Weist Schiedsrichter für Gruppenspiele zu (analog zu assign_group_referees aus referees.py)
function assign_group_referees($db) {
    // Hole alle Gruppenspiele sortiert nach Startzeit und ID
    $matches = $db->query("SELECT id, group_id, team1_id, team2_id, start_time FROM matches WHERE phase = 'group' ORDER BY start_time, id")->fetchAll(PDO::FETCH_ASSOC);
    // Hole alle Teams pro Gruppe
    $teams_by_group = [];
    $group_rows = $db->query("SELECT id FROM groups")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($group_rows as $group_row) {
        $group_id = $group_row['id'];
        $teams = $db->prepare("SELECT team_id FROM group_teams WHERE group_id = ? ORDER BY team_id");
        $teams->execute([$group_id]);
        $teams_by_group[$group_id] = array_map(function($t) { return $t['team_id']; }, $teams->fetchAll(PDO::FETCH_ASSOC));
    }
    // Zähler für Schiedsrichter-Einsätze pro Team
    $referee_count = [];
    foreach ($teams_by_group as $group_teams) {
        foreach ($group_teams as $team) {
            $referee_count[$team] = 0;
        }
    }
    foreach ($matches as $match) {
        $group_id = $match['group_id'];
        $start_time = $match['start_time'];
        $playing_teams = [$match['team1_id'], $match['team2_id']];
        // Finde alle Teams, die zur gleichen Zeit spielen (auch in anderen Gruppen)
        $stmt = $db->prepare("SELECT team1_id, team2_id FROM matches WHERE start_time = ? AND id != ? AND team1_id IS NOT NULL AND team2_id IS NOT NULL");
        $stmt->execute([$start_time, $match['id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $concurrent_match) {
            $playing_teams[] = $concurrent_match['team1_id'];
            $playing_teams[] = $concurrent_match['team2_id'];
        }
        // Finde Teams, die bereits als Schiedsrichter für Spiele zur gleichen Zeit zugewiesen sind
        $stmt2 = $db->prepare("SELECT referee_team_id FROM matches WHERE start_time = ? AND id != ? AND referee_team_id IS NOT NULL");
        $stmt2->execute([$start_time, $match['id']]);
        foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $ref_match) {
            if ($ref_match['referee_team_id']) {
                $playing_teams[] = $ref_match['referee_team_id'];
            }
        }
        // Finde den nächsten Zeitslot
        $stmt3 = $db->prepare("SELECT MIN(start_time) as next_time FROM matches WHERE start_time > ?");
        $stmt3->execute([$start_time]);
        $next_time_row = $stmt3->fetch(PDO::FETCH_ASSOC);
        if ($next_time_row && $next_time_row['next_time']) {
            $next_time = $next_time_row['next_time'];
            $stmt4 = $db->prepare("SELECT team1_id, team2_id FROM matches WHERE start_time = ? AND team1_id IS NOT NULL AND team2_id IS NOT NULL");
            $stmt4->execute([$next_time]);
            foreach ($stmt4->fetchAll(PDO::FETCH_ASSOC) as $next_match) {
                if ($next_match['team1_id']) $playing_teams[] = $next_match['team1_id'];
                if ($next_match['team2_id']) $playing_teams[] = $next_match['team2_id'];
            }
        }
        // Wenn Teams noch nicht feststehen (Platzhalter), setze Schiedsrichter auf NULL (TBD)
        if ($match['team1_id'] == -1 || $match['team2_id'] == -1) {
            $db->prepare("UPDATE matches SET referee_team_id = NULL WHERE id = ?")->execute([$match['id']]);
            // echo "Match {$match['id']}: Schiedsrichter auf TBD gesetzt (Teams noch nicht fest)\n";
        } else {
            // Wähle verfügbares Team
            $available_teams = array_filter($teams_by_group[$group_id], function($team) use ($playing_teams) {
                return !in_array($team, $playing_teams);
            });
            if (!empty($available_teams)) {
                $referee = array_reduce($available_teams, function($carry, $item) use ($referee_count) {
                    if ($carry === null) return $item;
                    return ($referee_count[$item] < $referee_count[$carry]) ? $item : $carry;
                }, null);
                $referee_count[$referee]++;
                $db->prepare("UPDATE matches SET referee_team_id = ? WHERE id = ?")->execute([$referee, $match['id']]);
                // echo "Match {$match['id']}: Team $referee als Schiedsrichter zugewiesen\n";
            } else {
                // echo "Match {$match['id']}: Kein verfügbares Schiedsrichter-Team gefunden\n";
            }
        }
    }
    $db = null;
    // echo "Schiedsrichter für Gruppenphase zugewiesen.\n";
}
<?php

function schedule_group_matches_optimized($matches) {
    // Ordnet Matches so an, dass Teams möglichst nicht direkt nacheinander spielen (Greedy-Algorithmus)
    $scheduled = [];
    $remaining = $matches;
    $last_teams = [];

    // Hilfsfunktion für Hashbarkeit
    $team_hashable = function($team) {
        if (is_array($team)) return json_encode($team);
        return $team;
    };

    while (count($remaining) > 0) {
        $best_match = null;
        $best_score = -1;
        foreach ($remaining as $idx => $match) {
            $score = 0;
            foreach ($match as $team) {
                if (!in_array($team_hashable($team), $last_teams)) {
                    $score++;
                }
            }
            if ($score > $best_score) {
                $best_score = $score;
                $best_match = $idx;
            }
        }
        $match = $remaining[$best_match];
        $scheduled[] = $match;
        array_splice($remaining, $best_match, 1);
        $last_teams = array_map($team_hashable, $match);
    }
    return $scheduled;
}

function generate_interleaved_group_matches_for_phase($db, $phase) {
    // Erzeugt alle Gruppenspiele für eine beliebige Phase
    $group_matches = [];
    foreach ($phase['groups'] as $group) {
        $teams = $group['teams'] ?? [];
        if (count($teams) < 2) continue;
        // Alle Paarungen erzeugen
        $matches = [];
        for ($i = 0; $i < count($teams); $i++) {
            for ($j = $i + 1; $j < count($teams); $j++) {
                $matches[] = [$teams[$i], $teams[$j]];
            }
        }
        $scheduled = schedule_group_matches_optimized($matches);
        $group_matches[] = [
            'group_id' => $group['id'],
            'group_name' => $group['name'],
            'matches' => $scheduled
        ];
    }
    // Ermittle aktuelle höchste Match-ID
    $stmt = $db->query("SELECT MAX(id) as max_id FROM matches");
    $row = $stmt->fetch();
    $match_id = ($row && $row['max_id']) ? $row['max_id'] + 1 : 1;
    $max_len = 0;
    foreach ($group_matches as $g) {
        $max_len = max($max_len, count($g['matches']));
    }
    for ($i = 0; $i < $max_len; $i++) {
        foreach ($group_matches as $g) {
            if ($i < count($g['matches'])) {
                list($t1, $t2) = $g['matches'][$i];
                $team1_id = is_int($t1) ? $t1 : -1;
                $team2_id = is_int($t2) ? $t2 : -1;
                $team1_ref = is_array($t1) ? json_encode($t1, JSON_UNESCAPED_UNICODE) : (is_string($t1) ? $t1 : null);
                $team2_ref = is_array($t2) ? json_encode($t2, JSON_UNESCAPED_UNICODE) : (is_string($t2) ? $t2 : null);
                $stmt = $db->prepare("INSERT INTO matches (id, phase, group_id, round, team1_id, team2_id, referee_team_id, team1_ref, team2_ref) VALUES (?, 'group', ?, ?, ?, ?, 0, ?, ?)");
                $stmt->execute([$match_id, $g['group_id'], $g['group_name'], $team1_id, $team2_id, $team1_ref, $team2_ref]);
                $match_id++;
            }
        }
    }
    // $db->commit(); // PDO autocommit
    // $db = null; // Schließen
}


// Ersetzt Platzhalter-Teams (team1_ref/team2_ref) in matches durch die korrekten Team-IDs
function resolve_placeholder_teams_in_matches($phase_results, $match_results = null) {
    $db = get_db_connection();
    $stmt = $db->query("SELECT id, team1_ref, team2_ref FROM matches WHERE (team1_id = -1 OR team2_id = -1) AND (team1_ref IS NOT NULL OR team2_ref IS NOT NULL)");
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($matches as $match) {
        $team1_id = null;
        $team2_id = null;
        // team1_ref prüfen
        if (!empty($match['team1_ref'])) {
            $team1_ref = json_decode($match['team1_ref'], true);
            if (isset($team1_ref['type']) && $team1_ref['type'] === 'group_place') {
                $key = $team1_ref['group'] . '_' . $team1_ref['place'];
                $team1_id = $phase_results[$key] ?? -1;
            } elseif (isset($team1_ref['type']) && $team1_ref['type'] === 'match_winner') {
                $key = 'M' . $team1_ref['match_id'] . ($team1_ref['winner'] ? '_Winner' : '_Loser');
                if ($match_results) {
                    $team1_id = $match_results[$key] ?? -1;
                }
            }
        }
        // team2_ref prüfen
        if (!empty($match['team2_ref'])) {
            $team2_ref = json_decode($match['team2_ref'], true);
            if (isset($team2_ref['type']) && $team2_ref['type'] === 'group_place') {
                $key = $team2_ref['group'] . '_' . $team2_ref['place'];
                $team2_id = $phase_results[$key] ?? -1;
            } elseif (isset($team2_ref['type']) && $team2_ref['type'] === 'match_winner') {
                $key = 'M' . $team2_ref['match_id'] . ($team2_ref['winner'] ? '_Winner' : '_Loser');
                if ($match_results) {
                    $team2_id = $match_results[$key] ?? -1;
                }
            }
        }
        if (($team1_id !== null && $team1_id != -1) || ($team2_id !== null && $team2_id != -1)) {
            $stmt2 = $db->prepare("UPDATE matches SET team1_id = COALESCE(NULLIF(?, -1), team1_id), team2_id = COALESCE(NULLIF(?, -1), team2_id) WHERE id = ?");
            $stmt2->execute([$team1_id, $team2_id, $match['id']]);
        }
    }
}

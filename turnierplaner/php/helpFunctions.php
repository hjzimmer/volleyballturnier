<?php
// Funktion zur Berechnung der Gruppenstatistiken (vereinfacht)
function calculateGroupStandings($db, $groupId) {
    // Prüfe, ob Gruppe dynamisch zusammengesetzt ist (group_place)
    $configPath = __DIR__ . '/../turnier_config.json';
    $config = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : null;
    $teams = [];
    $foundConfig = false;
    if ($config && isset($config['phases'])) {
        foreach ($config['phases'] as $phase) {
            if (isset($phase['groups'])) {
                foreach ($phase['groups'] as $groupConf) {
                    if (isset($groupConf['id']) && $groupConf['id'] == $groupId && isset($groupConf['teams'])) {
                        $foundConfig = true;
                        foreach ($groupConf['teams'] as $teamConf) {
                            if (is_array($teamConf) && isset($teamConf['type']) && $teamConf['type'] == 'group_place') {
                                // Hole Team anhand Platzierung aus referenzierter Gruppe
                                $refGroup = $teamConf['group'];
                                $refPlace = $teamConf['place'];
                                $refStandings = calculateGroupStandings($db, $refGroup);
                                $refTeam = isset($refStandings[$refPlace-1]['id']) ? $refStandings[$refPlace-1]['id'] : null;
                                if ($refTeam) {
                                    $stmt = $db->prepare("SELECT id, name FROM teams WHERE id = ?");
                                    $stmt->execute([$refTeam]);
                                    $team = $stmt->fetch();
                                    if ($team) {
                                        $teams[] = $team;
                                    }
                                }
                            } elseif (is_int($teamConf)) {
                                $stmt = $db->prepare("SELECT id, name FROM teams WHERE id = ?");
                                $stmt->execute([$teamConf]);
                                $team = $stmt->fetch();
                                if ($team) {
                                    $teams[] = $team;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    if (!$foundConfig) {
        // Fallback: Feste Teams aus group_teams
        $stmt = $db->prepare("
            SELECT t.id, t.name 
            FROM teams t
            JOIN group_teams gt ON gt.team_id = t.id
            WHERE gt.group_id = ?
            ORDER BY t.id
        ");
        $stmt->execute([$groupId]);
        $teams = $stmt->fetchAll();
    }
    $standings = [];
    foreach ($teams as $team) {
        $standings[$team['id']] = [
            'id' => $team['id'],
            'name' => $team['name'],
            'matchCnt' => 0,
            'points' => 0,
            'sets_won' => 0,
            'sets_lost' => 0,
            'sets_draw' => 0,
            'points_scored' => 0,
            'points_conceded' => 0,
            'point_diff' => 0,
            'matches' => []
        ];
    }

    $stmt = $db->prepare("
        SELECT m.id as match_id, m.team1_id, m.team2_id,
               s.set_number, s.team1_points, s.team2_points
        FROM matches m
        JOIN sets s ON s.match_id = m.id
        WHERE m.group_id = ? AND m.phase = 'group' AND m.finished = 1
        ORDER BY m.id, s.set_number
    ");
    $stmt->execute([$groupId]);
    $sets = $stmt->fetchAll();

    foreach ($sets as $set) {
        $t1 = $set['team1_id'];
        $t2 = $set['team2_id'];
        $p1 = $set['team1_points'];
        $p2 = $set['team2_points'];
        
        // Punkte zählen
        if (!isset($standings[$t1])) {
            $standings[$t1] = [
                'id' => $t1,
                'name' => '',
                'points' => 0,
                'sets_won' => 0,
                'sets_lost' => 0,
                'sets_draw' => 0,
                'points_scored' => 0,
                'points_conceded' => 0,
                'point_diff' => 0,
                'matches' => []
            ];
        }
        if (!isset($standings[$t2])) {
            $standings[$t2] = [
                'id' => $t2,
                'name' => '',
                'points' => 0,
                'sets_won' => 0,
                'sets_lost' => 0,
                'sets_draw' => 0,
                'points_scored' => 0,
                'points_conceded' => 0,
                'point_diff' => 0,
                'matches' => []
            ];
        }
       
        $standings[$t1]['points_scored'] += $p1;
        $standings[$t1]['points_conceded'] += $p2;
        $standings[$t2]['points_scored'] += $p2;
        $standings[$t2]['points_conceded'] += $p1;
        if ($p1 > $p2) {
            $standings[$t1]['points'] += 2;
            $standings[$t1]['sets_won']++;
            $standings[$t2]['sets_lost']++;
            if (!isset($standings[$t1]['matches'][$t2])) {
                $standings[$t1]['matches'][$t2] = ['points' => 0, 'sets_won' => 0, 'point_diff' => 0];
            }
            if (!isset($standings[$t2]['matches'][$t1])) {
                $standings[$t2]['matches'][$t1] = ['points' => 0, 'sets_won' => 0, 'point_diff' => 0];
            }
            $standings[$t1]['matches'][$t2]['points'] += 2;
            $standings[$t1]['matches'][$t2]['sets_won']++;
            $standings[$t1]['matches'][$t2]['point_diff'] += ($p1 - $p2);
            $standings[$t2]['matches'][$t1]['point_diff'] -= ($p1 - $p2);
        } elseif ($p2 > $p1) {
            $standings[$t2]['points'] += 2;
            $standings[$t2]['sets_won']++;
            $standings[$t1]['sets_lost']++;
            if (!isset($standings[$t1]['matches'][$t2])) {
                $standings[$t1]['matches'][$t2] = ['points' => 0, 'sets_won' => 0, 'point_diff' => 0];
            }
            if (!isset($standings[$t2]['matches'][$t1])) {
                $standings[$t2]['matches'][$t1] = ['points' => 0, 'sets_won' => 0, 'point_diff' => 0];
            }
            $standings[$t2]['matches'][$t1]['points'] += 2;
            $standings[$t2]['matches'][$t1]['sets_won']++;
            $standings[$t2]['matches'][$t1]['point_diff'] += ($p2 - $p1);
            $standings[$t1]['matches'][$t2]['point_diff'] -= ($p2 - $p1);
        } else {
            $standings[$t1]['points'] += 1;
            $standings[$t2]['points'] += 1;
            $standings[$t1]['sets_draw']++;
            $standings[$t2]['sets_draw']++;
            if (!isset($standings[$t1]['matches'][$t2])) {
                $standings[$t1]['matches'][$t2] = ['points' => 0, 'sets_won' => 0, 'point_diff' => 0];
            }
            if (!isset($standings[$t2]['matches'][$t1])) {
                $standings[$t2]['matches'][$t1] = ['points' => 0, 'sets_won' => 0, 'point_diff' => 0];
            }
            $standings[$t1]['matches'][$t2]['points'] += 1;
            $standings[$t2]['matches'][$t1]['points'] += 1;
        }
        
        $standings[$t1]['point_diff'] += ($p1 - $p2);
        $standings[$t2]['point_diff'] += ($p2 - $p1);

        $standings[$t1]['matchCnt'] = isset($standings[$t1]['matches']) ? count($standings[$t1]['matches']) : 0;
        $standings[$t2]['matchCnt'] = isset($standings[$t2]['matches']) ? count($standings[$t2]['matches']) : 0;
    }
    
    // Sortieren: 1. Satzpunkte, 2. Gewonnene Sätze, 3. Punktdifferenz
    usort($standings, function($a, $b) {
        // 1. Nach Satzpunkten
        if ($a['points'] != $b['points']) {
            return $b['points'] - $a['points'];
        }
        
        // 2. Nach gewonnenen Sätzen
        if ($a['sets_won'] != $b['sets_won']) {
            return $b['sets_won'] - $a['sets_won'];
        }
        
        // 3. Nach Punktdifferenz
        if ($a['point_diff'] != $b['point_diff']) {
            return $b['point_diff'] - $a['point_diff'];
        }
        
        // 4. Direkter Vergleich (nur wenn sie gegeneinander gespielt haben)
        if (isset($a['matches'][$b['id']]) && isset($b['matches'][$a['id']])) {
            $directA = $a['matches'][$b['id']]['points'];
            $directB = $b['matches'][$a['id']]['points'];
            if ($directA != $directB) {
                return $directB - $directA;
            }
            
            // Bei gleichem Punktestand: Gewonnene Sätze im direkten Vergleich
            $directSetsA = $a['matches'][$b['id']]['sets_won'];
            $directSetsB = $b['matches'][$a['id']]['sets_won'];
            if ($directSetsA != $directSetsB) {
                return $directSetsB - $directSetsA;
            }
            
            // Bei gleichen gewonnenen Sätzen: Punktdifferenz im direkten Vergleich
            $directDiffA = $a['matches'][$b['id']]['point_diff'];
            $directDiffB = $b['matches'][$a['id']]['point_diff'];
            if ($directDiffA != $directDiffB) {
                return $directDiffB - $directDiffA;
            }
        }
        
        return 0;
    });
    return $standings;
}

// Funktion zum Ermitteln der Anzahl gespielter Matches pro Team
function getMatchesPerTeam($db, $standings) {
    $matchCountProTeam = array_map(function($entry) {
        $anzahl = isset($entry['matches']) ? count($entry['matches']) : 0;
        return $anzahl;
    }, $standings);
    return $matchCountProTeam;
}

// Funktion zum Ermitteln, ob alle Spiele einer Gruppe beendet sind
function isGroupFinished($standings) {
    $matchCountProTeam = [];
    for ($i = 0; $i < count($standings); $i++) {
        if (isset($standings[$i]['matchCnt'])) {
            $matchCountProTeam[] = $standings[$i]['matchCnt'];
        }
    }
logge("anzahl gespielter matches pro team: " . json_encode($matchCountProTeam));    
    $anzahlUnterschiedlich = count(array_unique($matchCountProTeam));
    return $anzahlUnterschiedlich>1 ? false : true;
}

// Funktion zum Auflösen von Team-Referenzen
function resolveTeamToName($db, $teamId, $teamRef) {
    if ($teamId && $teamId != -1) {
        $stmt = $db->prepare("SELECT name FROM teams WHERE id = ?");
        $stmt->execute([$teamId]);
        return $stmt->fetchColumn();
    }
    else if ( $teamRef) {
        $refObj = json_decode($teamRef, true);
        if (is_array($refObj) && isset($refObj['type'])) {
            if ($refObj['type'] === 'group_place') {
                $g = htmlspecialchars($refObj['group']);
                $p = (int)$refObj['place'];
                $stmt = $db->prepare("SELECT name FROM groups WHERE id = ?");
                $stmt->execute([$g]);
                $groupName = $stmt->fetchColumn();
                if (!$groupName) $groupName = $g;
                return $p . ". " . htmlspecialchars($groupName);
            }
            if ($refObj['type'] === 'match_winner') {
                $mid = $refObj['match_id'];
                $stmt = $db->prepare("SELECT round FROM matches WHERE group_id = ?");
                $stmt->execute([$mid]);
                $matchName = $stmt->fetchColumn();
                if (!$matchName) $matchName = "Match " . htmlspecialchars($mid);
                else $matchName = htmlspecialchars($matchName);
                return $refObj['winner'] ? "Sieger von $matchName" : "Verlierer von $matchName";
            }
        }
        return htmlspecialchars($teamRef);
    }
    return "TBD";
}

function logge($msg, $farbe = 'black') {
    // Style abhängig von $farbe
    switch ($farbe) {
        case 'red':
            $colorStyle = 'color: red; font-weight: bold;';
            break;
        case 'green':
            $colorStyle = 'color: green; font-weight: bold;';
            break;
        case "blue":
            $colorStyle = 'color: blue; font-weight: bold;';
            break;
        default:
            $colorStyle = 'color: black;';
    }

    // Sichere JS-String-Repräsentation der Message
    $jsMsg = json_encode($msg,
        JSON_PRETTY_PRINT               // Zeilenumbrüche + Einrückung        
    );

    echo '<script>console.log("%cD:%c " + ' . $jsMsg . ', "' . $colorStyle . '", "color: black;");</script>';
}

?>
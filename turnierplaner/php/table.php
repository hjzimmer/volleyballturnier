<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Gesamttabelle</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Mobile Optimierung */
    @media (max-width: 768px) {
        body.container {
            padding-left: 10px;
            padding-right: 10px;
        }
        
        .nav-tabs {
            font-size: 0.85rem;
        }
        
        .nav-link {
            padding: 0.4rem 0.6rem;
        }
        
        h2, h3, h4, h5 {
            font-size: 1.2rem !important;
        }
        
        .card-header h5 {
            font-size: 1.1rem !important;
        }
        
        .table {
            font-size: 0.75rem;
        }
        
        .table td, .table th {
            padding: 0.4rem 0.3rem;
        }
        
        .badge {
            font-size: 0.65rem;
        }
        
        .card {
            margin-bottom: 15px;
        }
    }
    
    @media (max-width: 576px) {
        .table {
            font-size: 0.7rem;
        }
        
        .table td, .table th {
            padding: 0.3rem 0.2rem;
        }
        
        h2, h3, h4, h5 {
            font-size: 1rem !important;
        }
    }
  </style>
</head>
<body class="container py-4">

<?php include 'header.php'; ?>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="index.php">Spielplan</a></li>
  <li class="nav-item"><a class="nav-link" href="groups.php">Gruppen</a></li>
  <li class="nav-item"><a class="nav-link active" href="table.php">Gesamt</a></li>
  <li class="nav-item"><a class="nav-link" href="bracket.php">Turnierbaum</a></li>
  <li class="nav-item"><a class="nav-link" href="result_entry.php">Ergebnisse</a></li>
</ul>

<?php
require 'db.php';

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

// Funktion zum Auflösen von Team-Referenzen
function resolveTeam($db, $teamId, $teamRef) {
    if ($teamId) {
        $stmt = $db->prepare("SELECT name FROM teams WHERE id = ?");
        $stmt->execute([$teamId]);
        return $stmt->fetchColumn();
    }
    
    if ($teamRef) {
        // Gruppenplatzierung
        if (strpos($teamRef, '_') !== false && in_array($teamRef[0], ['A', 'B'])) {
            return $teamRef;
        }
        
        // Gewinner/Verlierer-Referenz (W_xxx oder L_xxx)
        if (strpos($teamRef, 'W_') === 0 || strpos($teamRef, 'L_') === 0) {
            $matchKey = substr($teamRef, 2);
            $field = strpos($teamRef, 'W_') === 0 ? 'winner_id' : 'loser_id';
            
            // Prüfe ob numerische Referenz (alte Methode: W_21)
            if (is_numeric($matchKey)) {
                $stmt = $db->prepare("SELECT $field FROM matches WHERE id = ?");
                $stmt->execute([$matchKey]);
                $winnerId = $stmt->fetchColumn();
                if ($winnerId) {
                    $stmt = $db->prepare("SELECT name FROM teams WHERE id = ?");
                    $stmt->execute([$winnerId]);
                    return $stmt->fetchColumn();
                }
            } else {
                // Match-Key-Referenz (neue Methode: W_Halbfinale_1)
                $roundName = str_replace('_', ' ', $matchKey);
                $stmt = $db->prepare("SELECT $field FROM matches WHERE phase = 'final' AND round = ?");
                $stmt->execute([$roundName]);
                $winnerId = $stmt->fetchColumn();
                if ($winnerId) {
                    $stmt = $db->prepare("SELECT name FROM teams WHERE id = ?");
                    $stmt->execute([$winnerId]);
                    return $stmt->fetchColumn();
                }
            }
            return $teamRef;
        }
    }
    return null;
}

// Funktion zur Berechnung der finalen Platzierungen
function calculateFinalStandings($db) {
    $placements = [];
    // 1. Hole Platzierungen aus allen Finalspielen (beendet und offen)
    $finalMatches = $db->query("
        SELECT id, winner_id, loser_id, winner_placement, loser_placement, finished, team1_ref, team2_ref, round, group_id
        FROM matches
        WHERE phase = 'final'
        ORDER BY id
    ")->fetchAll();
    
    foreach ($finalMatches as $match) {
        // Winner
        if ($match['winner_placement'] !== null) {
            $placement = $match['winner_placement'];
            // Prüfe, ob winner_placement ein JSON-Objekt mit final_placement ist
            if (is_string($placement) && ($placementObj = json_decode($placement, true)) && isset($placementObj['final_placement'])) {
                $finalPl = $placementObj['final_placement'];
            } else {
                $finalPl = $placement;
            }
            if ($match['finished'] && $match['winner_id']) {
                $placements[$match['winner_id']] = [
                    'placement' => $finalPl,
                    'round' => $match['round'],
                    'group_id' => $match['group_id'],
                    'match_place' => 'winner'
                ];
            } else {
                // Noch nicht beendet: Referenz anzeigen
                $ref = $match['team1_ref'] ?? null;
                if ($ref) {
                    $placements[$ref] = [
                        'placement' => $finalPl,
                        'round' => $match['round'],
                        'group_id' => $match['group_id'],
                        'match_place' => 'winner'
                    ];
                }
            }
        }
        // Loser
        if ($match['loser_placement'] !== null) {
            $placement = $match['loser_placement'];
            if (is_string($placement) && ($placementObj = json_decode($placement, true)) && isset($placementObj['final_placement'])) {
                $finalPl = $placementObj['final_placement'];
            } else {
                $finalPl = $placement;
            }
            if ($match['finished'] && $match['loser_id']) {
                $placements[$match['loser_id']] = [
                    'placement' => $finalPl,
                    'round' => $match['round'],
                    'group_id' => $match['group_id'],
                    'match_place' => 'looser'
                ];
            } else {
                $ref = $match['team2_ref'] ?? null;
                if ($ref) {
                    $placements[$ref] = [
                        'placement' => $finalPl,
                        'round' => $match['round'],
                        'group_id' => $match['group_id'],
                        'match_place' => 'looser'
                    ];
                }
            }
        }
    }
#logge("Placements nach DB Query: " . json_encode($placements), "green");
    
    // 2. Lade turnier_config für Gruppenplatzierungen
    $configPath = __DIR__ . '/../turnier_config.json';
    $groupPlacementRules = [];
    if (file_exists($configPath)) {
        $config = json_decode(file_get_contents($configPath), true);
        if (isset($config['group_placements'])) {
            $groupPlacementRules = $config['group_placements'];
        }
    }
    
    // 3. Berechne Gruppenplatzierungen für Teams, die nicht in Finals sind
    $groups = $db->query("SELECT id, name FROM groups ORDER BY id")->fetchAll();
    
    foreach ($groups as $group) {
        $standings = calculateGroupStandings($db, $group['id']);
        
        $rank = 1;
        foreach ($standings as $standing) {
            $teamId = $standing['id'];
            
            // Wenn Team noch keine Platzierung aus Finals hat
            if (!isset($placements[$teamId])) {
                // Berechne Platzierung basierend auf Gruppenrang
                if (isset($groupPlacementRules[(string)$rank])) {
                    $basePlacement = $groupPlacementRules[(string)$rank];
                    // Gruppe A bekommt ungerade, Gruppe B gerade Platzierungen
                    $placements[$teamId] = $group['name'] === 'A' ? $basePlacement : $basePlacement + 1;
                }
            }
            $rank++;
        }
    }
    
    // 4. Erstelle finale Liste mit Team-Namen oder Referenz
    $result = [];

#logge("DEBUG: Berechnete Platzierungen vor Namensauflösung: " . json_encode($placements), "blue");    
    foreach ($placements as $teamId => $placement) {
        $teamName = null;
        // Standard: Teamname, falls vorhanden
        if (is_numeric($teamId)) {
            $stmt = $db->prepare("SELECT name FROM teams WHERE id = ?");
            $stmt->execute([$teamId]);
            $teamName = $stmt->fetchColumn();
        } else {
            $decoded = null;
            if (is_string($teamId)) {
                $decoded = json_decode($teamId, true);
            }
            if (is_array($decoded) && isset($decoded['type'])) {
                if (($decoded['type'] === 'group_place') || ($decoded['type'] === 'match_winner')) {
                    $matchId = $decoded['match_id'] ?? '';
                    $winnerFlag = $placement['match_place'] == "winner" ? true : false;
                    $matchName = $placement['round'];
                    $matchLabel = $matchName ? $matchName : ("Match #$matchId");
                    $label = $winnerFlag ? 'Gewinner' : 'Verlierer';
                    $teamName = "(noch offen) <span class=\"badge bg-secondary ms-2\">($label $matchLabel)</span>";
                } else {
                    $teamName = '(noch offen)';
                }
            }
        }

        // NEU: Zeige für alle Finalplatzierungen (direkte Zahl) ein Badge mit Matchbezug
        if (is_numeric($placement)) {
            // Suche das zugehörige Match aus der Config (Finalrunde)
            $configPath = __DIR__ . '/../turnier_config.json';
            $config = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : null;
            $finalMatchBadge = '';
            if ($config && isset($config['phases'])) {
                foreach ($config['phases'] as $phase) {
                    if (($phase['id'] ?? '') === 'finale' && isset($phase['matches'])) {
                        foreach ($phase['matches'] as $match) {
                            // winner_placement
                            if (isset($match['winner_placement']) && $match['winner_placement'] == $placement) {
                                $finalMatchBadge = '<span class="badge bg-secondary ms-2">(Gewinner ' . htmlspecialchars($match['name']) . ')</span>';
                                break 2;
                            }
                            // loser_placement
                            if (isset($match['loser_placement']) && $match['loser_placement'] == $placement) {
                                $finalMatchBadge = '<span class="badge bg-secondary ms-2">(Verlierer ' . htmlspecialchars($match['name']) . ')</span>';
                                break 2;
                            }
                        }
                    }
                }
            }
            if ($finalMatchBadge) {
                if ($teamName && strpos($teamName, 'badge bg-secondary') === false) {
                    $teamName .= ' ' . $finalMatchBadge;
                } elseif (!$teamName) {
                    $teamName = $finalMatchBadge;
                }
            }
        }
        $result[] = [
            'placement' => $placement,
            'team_id' => $teamId,
            'team_name' => $teamName
        ];
    }
    
    // Sortieren nach Platzierung
    usort($result, function($a, $b) {
        $pa = is_array($a['placement']) && isset($a['placement']['placement']) ? $a['placement']['placement'] : $a['placement'];
        $pb = is_array($b['placement']) && isset($b['placement']['placement']) ? $b['placement']['placement'] : $b['placement'];
        return $pa - $pb;
    });
    return $result;
}

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
logge("DEBUG: Berechnung der Gruppenplatzierungen für Gruppe $groupId mit Teams: " . json_encode($teams), "blue");

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
if ($groupId == "ZG1") {
#    logge("DEBUG: t1:" . $t1 . ", t2:" . $t2 . ", p1:" . $p1 . ", p2:" . $p2, "red");
#    logge("DEBUG: standings[t1]:" . json_encode($standings[$t1]), "red");
#    logge("DEBUG: standings[t2]:" . json_encode($standings[$t2]), "red");
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
if ($groupId == "ZG1") {
#    logge("DEBUG: Nach Set " . $set['set_number'] . " in Match " . $set['match_id'] . " - Team $t1 vs Team $t2: " . json_encode($standings), "blue");
}
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

// Turnier-Statistiken
$totalMatches = $db->query("SELECT COUNT(*) FROM matches")->fetchColumn();
$finishedMatches = $db->query("SELECT COUNT(*) FROM matches WHERE finished = 1")->fetchColumn();
$groupMatches = $db->query("SELECT COUNT(*) FROM matches WHERE phase = 'group'")->fetchColumn();
$finishedGroupMatches = $db->query("SELECT COUNT(*) FROM matches WHERE phase = 'group' AND finished = 1")->fetchColumn();
$finalMatches = $db->query("SELECT COUNT(*) FROM matches WHERE phase = 'final'")->fetchColumn();

// Prüfen ob alle Gruppenspiele beendet sind
$allGroupMatchesFinished = ($groupMatches > 0 && $finishedGroupMatches == $groupMatches);

// Letzte 4 beendete Matches
$lastMatchesQuery = $db->query("
    SELECT m.id, m.start_time, m.round, m.field_number,
           m.team1_id, m.team2_id,
           m.team1_ref, m.team2_ref,
           m.winner_id, m.loser_id,
           r.name AS referee
    FROM matches m
    LEFT JOIN teams r ON r.id = m.referee_team_id
    WHERE m.finished = 1
    ORDER BY m.id DESC
    LIMIT 4
")->fetchAll();
// Reihenfolge umkehren, damit älteste zuerst angezeigt wird
$lastMatches = array_reverse($lastMatchesQuery);

// Funktion zum Holen der Satzergebnisse
function getSetResults($db, $matchId) {
    $stmt = $db->prepare("
        SELECT set_number, team1_points, team2_points 
        FROM sets 
        WHERE match_id = ? 
        ORDER BY set_number
    ");
    $stmt->execute([$matchId]);
    return $stmt->fetchAll();
}

// Nächste Matches (mindestens 4)
$nextMatches = $db->query("
    SELECT m.id, m.start_time, m.round, m.field_number,
           m.team1_id, m.team2_id,
           m.team1_ref, m.team2_ref,
           r.name AS referee
    FROM matches m
    LEFT JOIN teams r ON r.id = m.referee_team_id
    WHERE m.finished = 0
    ORDER BY m.start_time, m.id
    LIMIT 4
")->fetchAll();
?>

<div class="card mb-4">
    <div class="card-header"><strong>📊 Turnier-Status</strong></div>
    <div class="card-body">
        <table class="table table-sm mb-0">
            <tr>
                <td>Gesamt Matches:</td>
                <td><strong><?= $totalMatches ?></strong></td>
            </tr>
            <tr>
                <td>Gruppenphase:</td>
                <td><?= $groupMatches ?> Matches</td>
            </tr>
            <tr>
                <td>Finalrunde:</td>
                <td><?= $finalMatches ?> Matches</td>
            </tr>
            <tr class="table-success">
                <td>Beendet:</td>
                <td><strong><?= $finishedMatches ?> / <?= $totalMatches ?></strong></td>
            </tr>
        </table>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4 border-success">
            <div class="card-header bg-success text-white"><strong>✅ Letzte Spiele</strong></div>
            <div class="card-body">
                <?php if (count($lastMatches) > 0): ?>
                    <div class="list-group list-group-flush">
                    <?php foreach ($lastMatches as $match): ?>
                        <?php 
                        $team1 = resolveTeam($db, $match['team1_id'], $match['team1_ref']);
                        $team2 = resolveTeam($db, $match['team2_id'], $match['team2_ref']);
                        $field = $match['field_number'] ? 'Feld ' . $match['field_number'] : '-';
                        $sets = getSetResults($db, $match['id']);
                        $resultDisplay = "-";
                        if (count($sets) > 0) {
                            $setScores = [];
                            foreach ($sets as $set) {
                                $setScores[] = $set['team1_points'] . ":" . $set['team2_points'];
                            }
                            $resultDisplay = implode(' | ', $setScores);
                        }
                        $team1Class = '';
                        $team2Class = '';
                        if ($match['winner_id'] == $match['team1_id']) {
                            $team1Class = 'text-success fw-bold';
                            $team2Class = 'text-danger';
                        } elseif ($match['winner_id'] == $match['team2_id']) {
                            $team2Class = 'text-success fw-bold';
                            $team1Class = 'text-danger';
                        }
                        ?>
                        <div class="list-group-item px-0">
                            <div>
                                <strong>Match #<?= $match['id'] ?></strong>
                                <span class="badge bg-secondary ms-2"><?= htmlspecialchars($match['round']) ?></span>
                                <span class="badge bg-info ms-1"><?= $field ?></span>
                                <div class="mt-1">
                                    <span class="<?= $team1Class ?>"><?= htmlspecialchars($team1) ?></span>
                                    <strong> - </strong>
                                    <span class="<?= $team2Class ?>"><?= htmlspecialchars($team2) ?></span>
                                    <span class="badge bg-success ms-2"><?= $resultDisplay ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white"><strong>⏭️ Nächste Matches</strong></div>
            <div class="card-body">
                <?php if (count($nextMatches) > 0): ?>
                    <div class="list-group list-group-flush">
                    <?php foreach ($nextMatches as $match): ?>
                        <?php 
                        $team1 = resolveTeam($db, $match['team1_id'], $match['team1_ref']);
                        $team2 = resolveTeam($db, $match['team2_id'], $match['team2_ref']);
                        $time = $match['start_time'] ? date('H:i', strtotime($match['start_time'])) : 'TBD';
                        $field = $match['field_number'] ? 'Feld ' . $match['field_number'] : '-';
                        ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>Match #<?= $match['id'] ?></strong>
                                    <span class="badge bg-secondary ms-2"><?= htmlspecialchars($match['round']) ?></span>
                                    <span class="badge bg-info ms-1"><?= $field ?></span>
                                    <div class="mt-1">
                                        <?= htmlspecialchars($team1) ?> <strong>-</strong> <?= htmlspecialchars($team2) ?>
                                    </div>
                                    <?php if ($match['referee']): ?>
                                    <small class="text-muted">Schiri: <?= htmlspecialchars($match['referee']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <div class="badge bg-info text-dark">⏰ <?= $time ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Alle Matches beendet! 🎉</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h3 class="mb-0">🏆 Platzierungen</h3>
    </div>
    <div class="card-body">
        <?php 
        // Immer finale Platzierungen anzeigen, auch wenn noch nicht alle Gruppenspiele beendet sind
        $finalStandings = calculateFinalStandings($db);
#logge("Finale Platzierungen: " . json_encode($finalStandings), "blue");

        // Dummy-Matches (Platzierungszuweisungen ohne echtes Spiel) aus der Config holen
        $configPath = __DIR__ . '/../turnier_config.json';
        $config = json_decode(file_get_contents($configPath), true);
        $dummyMatches = [];
        foreach ($config['phases'] as $phase) {
            if (isset($phase['matches'])) {
                foreach ($phase['matches'] as $match) {
                    $isDummy = (!isset($match['team1']) && !isset($match['team2'])) || ($match['team1'] === null && $match['team2'] === null);
                    if ($isDummy) {
                        $dummyMatches[] = $match;
                    }
                }
            }
        }
#logge("Dummy-Matches: " . json_encode($dummyMatches), "blue");
        ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 80px;">Platz</th>
                        <th>Team</th>
                        <th class="text-center">Quelle</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($finalStandings as $standing): ?>
                    <?php 
                    $placementVal = is_array($standing['placement']) && isset($standing['placement']['placement']) ? $standing['placement']['placement'] : $standing['placement'];
                    $medal = '';
                    if ($placementVal == 1) {
                        $medal = '<span class="fs-4">🥇</span>';
                    } elseif ($placementVal == 2) {
                        $medal = '<span class="fs-4">🥈</span>';
                    } elseif ($placementVal == 3) {
                        $medal = '<span class="fs-4">🥉</span>';
                    }
                    $rowClass = '';
                    if ($standing['placement'] == 1) {
                        $rowClass = 'table-warning';
                    } elseif ($standing['placement'] == 2) {
                        $rowClass = 'table-light';
                    } elseif ($standing['placement'] == 3) {
                        $rowClass = 'table-info';
                    }
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="text-center fw-bold fs-5">
                            <?= $medal ?> <?= $placementVal ?>.
                        </td>
                        <td class="fs-5">
                            <?php if ($standing['team_name']): ?>
                                <?php if (strpos($standing['team_name'], 'badge bg-secondary') !== false): ?>
                                    <?= $standing['team_name'] ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($standing['team_name']) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">(noch offen)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">Finalrunde</td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($dummyMatches as $dummy):
                    // Zeige die Platzierungszuweisungen aus Dummy-Matches
                    foreach ([['key'=>'winner_placement','label'=>'Sieger'],['key'=>'loser_placement','label'=>'Verlierer']] as $entry) {
                        $placement = $dummy[$entry['key']] ?? null;
                        if (is_array($placement) && isset($placement['final_placement'])) {
                            $group = $placement['group'] ?? '';
                            $place = $placement['place'] ?? '';
                            $finalPl = $placement['final_placement'];
                            // Teamname aus Gruppenplatzierung holen
                            $teamName = '';
                            if ($group && $place) {
                                $standings = calculateGroupStandings($db, $group);
                                if (isset($standings[$place-1]['name'])) {
                                    $teamName = $standings[$place-1]['name'];
                                } else {
                                    $teamName = $group . ' Platz ' . $place;
                                }
                            } else {
                                $teamName = '(noch offen)';
                            }
                            ?>
                            <tr class="table-secondary">
                                <td class="text-center fw-bold fs-5"> <?= $finalPl ?>.</td>
                                <td class="fs-5"><?= htmlspecialchars($teamName) ?> <span class="badge bg-secondary ms-2">(<?= $group ?> Platz <?= $place ?>)</span></td>
                                <td class="text-center">Gruppenplatzierung</td>
                            </tr>
                            <?php
                        }
                    }
                endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="alert alert-info mt-3">
            <small>
                <strong>Hinweis:</strong> Die Platzierungen werden immer angezeigt. Falls Teams/Ergebnisse noch nicht feststehen, werden Referenzen angezeigt.
            </small>
        </div>
    </div>
        <div class="card-header"><strong>📊 Gruppentabellen</strong></div>
        <div class="card-body">
            <?php 
            // Phasen dynamisch aus turnier_config.json laden
            $configPath = __DIR__ . '/../turnier_config.json';
            $configJson = file_get_contents($configPath);
            $config = json_decode($configJson, true);
            $phases = [];
            if (isset($config['phases'])) {
                foreach ($config['phases'] as $phase) {
                    if (isset($phase['id']) && isset($phase['name'])) {
                        $phases[$phase['id']] = $phase['name'];
                    }
                }
            }
            $groups = $db->query("SELECT id, name, phase_name FROM groups ORDER BY id")->fetchAll();
            foreach ($phases as $phaseKey => $phaseLabel): 
            ?>
                <h4 class="mt-4"><?= $phaseLabel ?></h4>
                <div class="row">
                <?php foreach ($groups as $group): 
                    $standings = calculateGroupStandings($db, $group['id'], $phaseKey);
                    if (count($standings) == 0) continue;
                    if ($group['phase_name'] != $phaseLabel) continue;
                    ?>
                    <div class="col-md-6">
                        <h5>Gruppe <?= $group['name'] ?></h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center">Platz</th>
                                        <th>Team</th>
                                        <th class="text-center" title="Satzpunkte">Pkt</th>
                                        <th class="text-center" title="Gewonnene Sätze">S+</th>
                                        <th class="text-center" title="Unentschiedene Sätze">S=</th>
                                        <th class="text-center" title="Verlorene Sätze">S-</th>
                                        <th class="text-center" title="Satzpunkte">Satzpunkte</th>
                                        <th class="text-center" title="Punktdifferenz">Diff</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php 
                                $rank = 1;
                                foreach ($standings as $standing): ?>
                                    <tr>
                                        <td class="text-center fw-bold"><?= $rank ?></td>
                                        <td><?= htmlspecialchars($standing['name']) ?></td>
                                        <td class="text-center fw-bold"><?= $standing['points'] ?></td>
                                        <td class="text-center"><?= $standing['sets_won'] ?></td>
                                        <td class="text-center"><?= $standing['sets_draw'] ?></td>
                                        <td class="text-center"><?= $standing['sets_lost'] ?></td>
                                        <td class="text-center"><?= $standing['points_scored'] ?>:<?= $standing['points_conceded'] ?></td>
                                        <td class="text-center <?= $standing['point_diff'] > 0 ? 'text-success' : ($standing['point_diff'] < 0 ? 'text-danger' : '') ?>">
                                            <?= $standing['point_diff'] > 0 ? '+' : '' ?><?= $standing['point_diff'] ?>
                                        </td>
                                    </tr>
                                <?php 
                                    $rank++;
                                endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
</div>

</body>
</html>

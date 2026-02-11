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
            } 
            // Match-Key-Referenz (neue Methode: W_Halbfinale_1)
            else {
                // Konvertiere match_key zu round-Name
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
    
    return "TBD";
}

// Funktion zur Berechnung der finalen Platzierungen
function calculateFinalStandings($db) {
    $placements = [];
    
    // 1. Hole Platzierungen aus beendeten Finalspielen
    $finalMatches = $db->query("
        SELECT id, winner_id, loser_id, winner_placement, loser_placement, finished
        FROM matches
        WHERE phase = 'final' AND finished = 1
        ORDER BY id
    ")->fetchAll();
    
    foreach ($finalMatches as $match) {
        if ($match['winner_placement'] !== null && $match['winner_id']) {
            $placements[$match['winner_id']] = $match['winner_placement'];
        }
        if ($match['loser_placement'] !== null && $match['loser_id']) {
            $placements[$match['loser_id']] = $match['loser_placement'];
        }
    }
    
    // 2. Lade final_config für Gruppenplatzierungen
    $configPath = __DIR__ . '/../final_config.json';
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
    
    // 4. Erstelle finale Liste mit Team-Namen
    $result = [];
    foreach ($placements as $teamId => $placement) {
        $stmt = $db->prepare("SELECT name FROM teams WHERE id = ?");
        $stmt->execute([$teamId]);
        $teamName = $stmt->fetchColumn();
        
        $result[] = [
            'placement' => $placement,
            'team_id' => $teamId,
            'team_name' => $teamName
        ];
    }
    
    // Sortieren nach Platzierung
    usort($result, function($a, $b) {
        return $a['placement'] - $b['placement'];
    });
    
    return $result;
}

// Funktion zur Berechnung der Gruppenstatistiken (vereinfacht)
function calculateGroupStandings($db, $groupId) {
    $stmt = $db->prepare("
        SELECT t.id, t.name 
        FROM teams t
        JOIN group_teams gt ON gt.team_id = t.id
        WHERE gt.group_id = ?
        ORDER BY t.id
    ");
    $stmt->execute([$groupId]);
    $teams = $stmt->fetchAll();
    
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
                    <?php foreach ($lastMatches as $match): 
                        $team1 = resolveTeam($db, $match['team1_id'], $match['team1_ref']);
                        $team2 = resolveTeam($db, $match['team2_id'], $match['team2_ref']);
                        $field = $match['field_number'] ? 'Feld ' . $match['field_number'] : '-';
                        
                        // Hole Satzergebnisse
                        $sets = getSetResults($db, $match['id']);
                        $resultDisplay = "-";
                        
                        if (count($sets) > 0) {
                            $setScores = [];
                            foreach ($sets as $set) {
                                $setScores[] = $set['team1_points'] . ":" . $set['team2_points'];
                            }
                            $resultDisplay = implode(' | ', $setScores);
                        }
                        
                        // Bestimme Sieger und Verlierer aus Datenbank
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
                <?php else: ?>
                    <p class="text-muted mb-0">Noch keine beendeten Spiele</p>
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
                    <?php foreach ($nextMatches as $match): 
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
        <h3 class="mb-0"><?php if ($allGroupMatchesFinished): ?>🏆 Finale Platzierungen<?php else: ?>📊 Gruppentabellen<?php endif; ?></h3>
    </div>
    <div class="card-body">
        <?php if ($allGroupMatchesFinished): ?>
            <?php 
            $finalStandings = calculateFinalStandings($db);
            
            if (count($finalStandings) > 0):
            ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 80px;">Platz</th>
                                <th>Team</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        foreach ($finalStandings as $standing): 
                            // Medaillen für Top 3
                            $medal = '';
                            if ($standing['placement'] == 1) {
                                $medal = '<span class="fs-4">🥇</span>';
                            } elseif ($standing['placement'] == 2) {
                                $medal = '<span class="fs-4">🥈</span>';
                            } elseif ($standing['placement'] == 3) {
                                $medal = '<span class="fs-4">🥉</span>';
                            }
                            
                            // Hervorhebungen für Top 4
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
                                    <?= $medal ?> <?= $standing['placement'] ?>.
                                </td>
                                <td class="fs-5"><?= htmlspecialchars($standing['team_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="alert alert-info mt-3">
                    <small>
                        <strong>Hinweis:</strong> Die Platzierungen werden automatisch aktualisiert, sobald die Finalspiele beendet sind. 
                        Gruppenplatzierungen verwenden die Ränge aus der Vorrunde kombiniert mit den konfigurierten Platzierungen 
                        aus <code>final_config.json</code>.
                    </small>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary">
                    <p class="mb-0">
                        <strong>Noch keine Platzierungen verfügbar.</strong><br>
                        Die finale Tabelle wird angezeigt, sobald die ersten Ergebnisse eingetragen wurden.
                    </p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php 
            // Zeige Gruppentabellen
            $groups = $db->query("SELECT id, name FROM groups ORDER BY id")->fetchAll();
            ?>
            <div class="row">
                <?php foreach ($groups as $group): 
                    $standings = calculateGroupStandings($db, $group['id']);
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
                                foreach ($standings as $standing): 
                                ?>
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
                                endforeach; 
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

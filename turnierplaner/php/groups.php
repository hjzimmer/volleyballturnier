<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Gruppen</title>
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
        
        .card-header h3 {
            font-size: 1.3rem;
        }
        
        .col-md-6 h5 {
            font-size: 1.1rem;
            margin-bottom: 10px;
        }
        
        /* Tabellen kompakter */
        .table-sm {
            font-size: 0.75rem;
        }
        
        .table-sm td, .table-sm th {
            padding: 0.4rem 0.3rem;
        }
        
        /* Badge kleiner */
        .badge {
            font-size: 0.65rem;
        }
        
        /* Spalten auf mobil untereinander */
        .row > .col-md-6 {
            margin-bottom: 20px;
        }
    }
    
    @media (max-width: 576px) {
        .table-sm {
            font-size: 0.7rem;
        }
        
        .card-header h3 {
            font-size: 1.1rem;
        }
        
        /* Sehr kleine Screens: noch kompaktere Tabelle */
        .table-sm td, .table-sm th {
            padding: 0.3rem 0.2rem;
        }
    }
  </style>
</head>
<body class="container py-4">

<?php include 'header.php'; ?>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="index.php">Spielplan</a></li>
  <li class="nav-item"><a class="nav-link active" href="groups.php">Gruppen</a></li>
  <li class="nav-item"><a class="nav-link" href="table.php">Gesamt</a></li>
  <li class="nav-item"><a class="nav-link" href="bracket.php">Turnierbaum</a></li>
  <li class="nav-item"><a class="nav-link" href="result_entry.php">Ergebnisse</a></li>
</ul>

<?php
require 'db.php';

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

// Funktion zur Berechnung der Gruppenstatistiken
function calculateGroupStandings($db, $groupId) {
    // Hole alle Teams der Gruppe
    $stmt = $db->prepare("
        SELECT t.id, t.name 
        FROM teams t
        JOIN group_teams gt ON gt.team_id = t.id
        WHERE gt.group_id = ?
        ORDER BY t.id
    ");
    $stmt->execute([$groupId]);
    $teams = $stmt->fetchAll();
    
    // Initialisiere Statistiken für jedes Team
    $standings = [];
    foreach ($teams as $team) {
        $standings[$team['id']] = [
            'id' => $team['id'],
            'name' => $team['name'],
            'points' => 0,           // Satzpunkte (2 für Sieg, 1 für Unentschieden, 0 für Niederlage)
            'sets_won' => 0,         // Gewonnene Sätze
            'sets_lost' => 0,        // Verlorene Sätze
            'sets_draw' => 0,        // Unentschiedene Sätze
            'points_scored' => 0,    // Erzielte Punkte
            'points_conceded' => 0,  // Abgegebene Punkte
            'point_diff' => 0,       // Punktdifferenz
            'matches' => []          // Für direkten Vergleich
        ];
    }
    
    // Hole alle Sätze der beendeten Gruppenspiele
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
        
        // Punkte und Statistiken aktualisieren
        $standings[$t1]['points_scored'] += $p1;
        $standings[$t1]['points_conceded'] += $p2;
        $standings[$t2]['points_scored'] += $p2;
        $standings[$t2]['points_conceded'] += $p1;
        
        // Satzpunkte vergeben
        if ($p1 > $p2) {
            $standings[$t1]['points'] += 2;
            $standings[$t1]['sets_won']++;
            $standings[$t2]['sets_lost']++;
            
            // Für direkten Vergleich
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
            
            // Für direkten Vergleich
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
            
            // Für direkten Vergleich
            if (!isset($standings[$t1]['matches'][$t2])) {
                $standings[$t1]['matches'][$t2] = ['points' => 0, 'sets_won' => 0, 'point_diff' => 0];
            }
            if (!isset($standings[$t2]['matches'][$t1])) {
                $standings[$t2]['matches'][$t1] = ['points' => 0, 'sets_won' => 0, 'point_diff' => 0];
            }
            $standings[$t1]['matches'][$t2]['points'] += 1;
            $standings[$t2]['matches'][$t1]['points'] += 1;
        }
    }
    
    // Punktdifferenz berechnen
    foreach ($standings as $id => $data) {
        $standings[$id]['point_diff'] = $data['points_scored'] - $data['points_conceded'];
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

// Hole alle Gruppen
$groups = $db->query("SELECT id, name FROM groups ORDER BY id");

foreach ($groups as $group):
?>
<div class="card mb-4">
    <div class="card-header">
        <h3 class="mb-0">Gruppe <?= $group['name'] ?></h3>
    </div>
    <div class="card-body">
        <?php
        $standings = calculateGroupStandings($db, $group['id']);
        
        // Prüfen, ob schon Spiele stattgefunden haben
        $hasResults = false;
        foreach ($standings as $standing) {
            if ($standing['points'] > 0 || $standing['sets_won'] > 0 || $standing['sets_lost'] > 0 || $standing['sets_draw'] > 0) {
                $hasResults = true;
                break;
            }
        }
        ?>
        
        <div class="row">
            <!-- Linke Spalte: Spiele der Gruppe -->
            <div class="col-md-6 col-12">
                <h5>Spiele der Gruppe</h5>
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Zeit</th>
                            <th>Feld</th>
                            <th>Match</th>
                            <th>Ergebnis</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $matchesStmt = $db->prepare("
                        SELECT m.id, m.start_time, m.field_number, m.finished,
                               t1.name AS team1, t2.name AS team2
                        FROM matches m
                        JOIN teams t1 ON t1.id = m.team1_id
                        JOIN teams t2 ON t2.id = m.team2_id
                        WHERE m.group_id = ? AND m.phase = 'group'
                        ORDER BY m.start_time, m.id
                    ");
                    $matchesStmt->execute([$group['id']]);
                    $matches = $matchesStmt->fetchAll();
                    
                    if (count($matches) > 0):
                        foreach ($matches as $match):
                            $time = $match['start_time'] ? date('H:i', strtotime($match['start_time'])) : '-';
                            $field = $match['field_number'] ? 'Feld ' . $match['field_number'] : '-';
                            
                            // Hole Satzergebnisse wenn Match beendet
                            $resultDisplay = "-";
                            if ($match["finished"]) {
                                $sets = getSetResults($db, $match["id"]);
                                if (count($sets) > 0) {
                                    $setScores = [];
                                    foreach ($sets as $set) {
                                        $setScores[] = $set['team1_points'] . ":" . $set['team2_points'];
                                    }
                                    $resultDisplay = '<span class="badge bg-success">' . implode(' | ', $setScores) . '</span>';
                                }
                            }
                    ?>
                        <tr>
                            <td><?= $time ?></td>
                            <td><span class="badge bg-info"><?= $field ?></span></td>
                            <td><?= htmlspecialchars($match['team1']) ?> - <?= htmlspecialchars($match['team2']) ?></td>
                            <td><?= $resultDisplay ?></td>
                        </tr>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <tr>
                            <td colspan="4" class="text-muted text-center">Keine Matches geplant</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Rechte Spalte: Tabelle -->
            <div class="col-md-6 col-12">
                <h5>Tabelle</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="min-width: 35px;">Pl.</th>
                                <th style="min-width: 80px;">Team</th>
                                <th class="text-center" title="Satzpunkte" style="min-width: 35px;">Pkt</th>
                                <th class="text-center d-none d-sm-table-cell" title="Gewonnene Sätze" style="min-width: 35px;">S+</th>
                                <th class="text-center d-none d-md-table-cell" title="Unentschiedene Sätze" style="min-width: 35px;">S=</th>
                                <th class="text-center d-none d-sm-table-cell" title="Verlorene Sätze" style="min-width: 35px;">S-</th>
                                <th class="text-center d-none d-lg-table-cell" title="Satzpunkte" style="min-width: 50px;">Satzpkt</th>
                                <th class="text-center" title="Punktdifferenz" style="min-width: 40px;">Diff</th>
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
                                <td class="text-center d-none d-sm-table-cell"><?= $standing['sets_won'] ?></td>
                                <td class="text-center d-none d-md-table-cell"><?= $standing['sets_draw'] ?></td>
                                <td class="text-center d-none d-sm-table-cell"><?= $standing['sets_lost'] ?></td>
                                <td class="text-center d-none d-lg-table-cell"><?= $standing['points_scored'] ?>:<?= $standing['points_conceded'] ?></td>
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
                
                <?php if (!$hasResults): ?>
                    <div class="alert alert-secondary">
                        <small><em>Die Rangliste wird aktualisiert, sobald Ergebnisse eingetragen wurden.</em></small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

</body>
</html>

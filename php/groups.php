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
    .team-loser {
        color: #f08080 !important;
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
require_once 'helpFunctions.php';

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

// Hole alle Gruppenphasen, sortiert nach Reihenfolge (z.B. group, zwischenrunde, platzierung, final)
$phases = $db->query("SELECT DISTINCT phase FROM matches ORDER BY CASE phase WHEN 'group' THEN 1 WHEN 'zwischenrunde' THEN 2 WHEN 'platzierung' THEN 3 WHEN 'final' THEN 4 ELSE 99 END")->fetchAll(PDO::FETCH_COLUMN);

foreach ($phases as $phase):
    // Hole alle Gruppen dieser Phase
    $groups = $db->prepare("SELECT id, name FROM groups WHERE id IN (SELECT DISTINCT group_id FROM matches WHERE phase = ?) ORDER BY id");
    $groups->execute([$phase]);
    $groups = $groups->fetchAll();
    if (count($groups) === 0) continue;
?>
<h2 class="mt-4 mb-3">
    <?php
        // Schöne Anzeige für Phasenname
        switch ($phase) {
            case 'group': echo 'Gruppenphase'; break;
            case 'zwischenrunde': echo 'Zwischenrunde'; break;
            case 'platzierung': echo 'Platzierungsrunde'; break;
            case 'final': echo 'Finalrunde'; break;
            default: echo ucfirst($phase);
        }
    ?>
</h2>
<?php foreach ($groups as $group): ?>
<div class="card mb-4">
    <div class="card-header">
        <h3 class="mb-0">Gruppe <?= $group['name'] ?></h3>
    </div>
    <div class="card-body">
        <?php
        $standings = calculateGroupStandings($db, $group['id'], $phase);

        // Prüfen, ob schon Spiele stattgefunden haben
        $hasResults = false;
        foreach ($standings as $standing) {
            if ($standing['matchCnt'] > 0 ) {
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
                               m.team1_id, m.team2_id, m.winner_id, m.loser_id,
                               t1.name AS team1, t2.name AS team2
                        FROM matches m
                        JOIN teams t1 ON t1.id = m.team1_id
                        JOIN teams t2 ON t2.id = m.team2_id
                        WHERE m.group_id = ? AND m.phase = ?
                        ORDER BY m.start_time, m.id
                    ");
                    $matchesStmt->execute([$group['id'], $phase]);
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
                            $isTeam1Winner = ($match['finished'] && $match['winner_id'] == $match['team1_id']);
                            $isTeam2Winner = ($match['finished'] && $match['winner_id'] == $match['team2_id']);
                            $isTeam1Loser  = ($match['finished'] && $match['loser_id']  == $match['team1_id']);
                            $isTeam2Loser  = ($match['finished'] && $match['loser_id']  == $match['team2_id']);
                    ?>
                        <tr>
                            <td><?= $time ?></td>
                            <td><span class="badge bg-info"><?= $field ?></span></td>
                            <td>
                                <span class="<?= $isTeam1Winner ? 'text-success fw-bold' : ($isTeam1Loser ? 'team-loser' : '') ?>"><?= htmlspecialchars($match['team1']) ?></span>
                                <strong class="text-primary"> - </strong>
                                <span class="<?= $isTeam2Winner ? 'text-success fw-bold' : ($isTeam2Loser ? 'team-loser' : '') ?>"><?= htmlspecialchars($match['team2']) ?></span>
                            </td>
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
                                <th class="text-center" title="Spiele" style="min-width: 35px;">Sp</th>
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
                                <td class="text-center fw-bold"><?= $standing['matchCnt'] ?></td>
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
<?php endforeach; ?>

</body>
</html>

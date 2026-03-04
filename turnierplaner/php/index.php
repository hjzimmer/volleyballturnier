<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Turnierplan</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">

<?php include 'header.php'; ?>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link active" href="index.php">Spielplan</a></li>
  <li class="nav-item"><a class="nav-link" href="groups.php">Gruppen</a></li>
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

$matches = $db->query("
SELECT m.id, m.start_time, m.phase, m.round, m.field_number, m.finished,
       m.team1_id, m.team2_id, m.group_id, m.winner_id, m.loser_id,
       m.team1_ref, m.team2_ref,
       m.referee_team_id,
       r.name AS referee,
       g.name AS group_name
FROM matches m
LEFT JOIN teams r ON r.id = m.referee_team_id
LEFT JOIN groups g ON g.id = m.group_id
ORDER BY m.start_time, m.field_number, m.id
")->fetchAll();

// Gruppiere Matches nach Zeit
$matchesByTime = [];
foreach ($matches as $m) {
    $time = $m['start_time'] ? date("H:i", strtotime($m['start_time'])) : "TBD";
    if (!isset($matchesByTime[$time])) {
        $matchesByTime[$time] = ['phase' => $m['phase']];
    }
    $field = $m['field_number'] ?: 1;
    $matchesByTime[$time][$field] = $m;
}
?>

<style>
.match-card {
    padding: 15px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: white;
    height: 100%;
    transition: all 0.2s;
}
.match-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.match-card.finished {
    border-left: 4px solid #d79c53;
    /*4px solid #28a745;*/
    background: #f8fff9;
}
.match-teams {
    font-size: 1rem;
    font-weight: 500;
    margin: 8px 0;
}
.match-info {
    font-size: 0.85rem;
    color: #6c757d;
}
.time-header {
    /*background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);*/
    background: linear-gradient(135deg, #f16818 0%, #c58d39 100%);
    color: white;
    font-weight: bold;
    font-size: 1.1rem;
}
.team-loser {
    color: #f08080 !important;
}

/* Mobile Optimierung */
@media (max-width: 768px) {
    /* Tabelle wird zu Cards-Layout */
    .mobile-time-block {
        margin-bottom: 20px;
        border: 2px solid #c58d39;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .mobile-time-header {
        background: linear-gradient(135deg, #f16818 0%, #c58d39 100%);
        color: white;
        padding: 10px 15px;
        font-weight: bold;
        font-size: 1.1rem;
    }
    
    .mobile-field-label {
        background: #f8f9fa;
        padding: 8px 15px;
        font-weight: 600;
        color: #495057;
        border-top: 1px solid #dee2e6;
    }
    
    .mobile-match-content {
        padding: 15px;
        border-top: 1px solid #dee2e6;
    }
    
    .match-card {
        padding: 12px;
        margin-bottom: 0;
    }
    
    .match-teams {
        font-size: 0.9rem;
        line-height: 1.4;
    }
    
    .match-info {
        font-size: 0.75rem;
    }
    
    /* Navigation kompakter */
    .nav-tabs {
        font-size: 0.85rem;
    }
    
    .nav-link {
        padding: 0.4rem 0.6rem;
    }
    
    /* Container padding reduzieren */
    body.container {
        padding-left: 10px;
        padding-right: 10px;
    }
}

@media (max-width: 576px) {
    .match-teams {
        font-size: 0.85rem;
    }
    
    .time-header, .mobile-time-header {
        font-size: 1rem;
    }
}</style>

<!-- Desktop: Tabellen-Layout -->
<div class="table-responsive d-none d-md-block">
<table class="table table-bordered align-middle">
<thead class="table-light">
<tr>
  <th style="width: 100px;">Zeit</th>
  <th>Feld 1</th>
  <th>Feld 2</th>
</tr>
</thead>
<tbody>
<?php 
$currentPhase = null;
foreach ($matchesByTime as $time => $timeData): 
    // Phasen-Überschrift einfügen
    if ($currentPhase !== $timeData['phase']) {
        $currentPhase = $timeData['phase'];
        $phaseLabel = $timeData['phase'] === 'group' ? '📋 Gruppenphase' : '🏆 Finalrunde';
?>
<tr class="table-secondary">
  <td colspan="3" class="fw-bold text-center"><?= $phaseLabel ?></td>
</tr>
<?php
    }
    
    // Bestimme Phasen-Namen für diese Zeitzeile aus der groups-Tabelle
    $phaseName = null;
    if (isset($timeData[1]) && isset($timeData[1]['group_id'])) {
        $stmt = $db->prepare("SELECT phase_name FROM groups WHERE id = ?");
        $stmt->execute([$timeData[1]['group_id']]);
        $phaseName = $stmt->fetchColumn();
    } elseif (isset($timeData[2]) && isset($timeData[2]['group_id'])) {
        $stmt = $db->prepare("SELECT phase_name FROM groups WHERE id = ?");
        $stmt->execute([$timeData[2]['group_id']]);
        $phaseName = $stmt->fetchColumn();
    }
    if (!$phaseName) {
        $phaseName = htmlspecialchars($currentPhase);
        $phaseName = htmlspecialchars('Finalerunde');
    }
?>
<tr>
  <td class="time-header text-center">
    <?= $time ?>
    <div class="mt-1">
        <span class="badge bg-primary" style="font-size: 0.75rem;">
            <?= $phaseName ?>
        </span>
    </div>
  </td>
  
  <?php for ($field = 1; $field <= 2; $field++): ?>
    <td>
      <?php if (isset($timeData[$field])): 
          $m = $timeData[$field];
          $team1 = resolveTeamToName($db, $m["team1_id"], $m["team1_ref"]);
          $team2 = resolveTeamToName($db, $m["team2_id"], $m["team2_ref"]);

          // Bestimme Gewinner/Verlierer für Farbmarkierung
          $isTeam1Winner = ($m['finished'] && $m['winner_id'] == $m['team1_id']);
          $isTeam2Winner = ($m['finished'] && $m['winner_id'] == $m['team2_id']);
          $isTeam1Loser = ($m['finished'] && $m['loser_id'] == $m['team1_id']);
          $isTeam2Loser = ($m['finished'] && $m['loser_id'] == $m['team2_id']);
          
          // Hole Satzergebnisse wenn Match beendet
          $resultDisplay = "";
          if ($m["finished"]) {
              $sets = getSetResults($db, $m["id"]);
              if (count($sets) > 0) {
                  $setScores = [];
                  foreach ($sets as $set) {
                      $setScores[] = $set['team1_points'] . ":" . $set['team2_points'];
                  }
                  $resultDisplay = '<div class="mt-2"><span class="badge bg-success">' . implode(' | ', $setScores) . '</span></div>';
              }
          }
      ?>
        <div class="match-card <?= $m['finished'] ? 'finished' : '' ?>">
          <div class="match-info mb-2">
            <span class="badge bg-secondary"><?= htmlspecialchars($m["round"]) ?></span>
            <span class="text-muted ms-2">#<?= $m["id"] ?></span>
          </div>
          
          <div class="match-teams">
            <span class="<?= $isTeam1Winner ? 'text-success fw-bold' : ($isTeam1Loser ? 'team-loser' : '') ?>">
              <?= htmlspecialchars($team1) ?>
            </span>
            <strong class="text-primary"> - </strong>
            <span class="<?= $isTeam2Winner ? 'text-success fw-bold' : ($isTeam2Loser ? 'team-loser' : '') ?>">
              <?= htmlspecialchars($team2) ?>
            </span>
          </div>
          
          <?= $resultDisplay ?>
          
          <div class="match-info mt-2">
            <small>
              <strong>Schiri:</strong> 
              <?= $m["referee"] ? htmlspecialchars($m["referee"]) : "<em class='text-muted'>TBD</em>" ?>
            </small>
          </div>
        </div>
      <?php else: ?>
        <div class="text-center text-muted py-3">-</div>
      <?php endif; ?>
    </td>
  <?php endfor; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- Mobile: Gestapeltes Card-Layout -->
<div class="d-block d-md-none">
<?php 
$currentPhase = null;
foreach ($matchesByTime as $time => $timeData): 
    // Phasen-Überschrift
    if ($currentPhase !== $timeData['phase']) {
        $currentPhase = $timeData['phase'];
        $phaseLabel = $timeData['phase'] === 'group' ? '📋 Gruppenphase' : '🏆 Finalrunde';
?>
<div class="alert alert-secondary fw-bold text-center mb-3"><?= $phaseLabel ?></div>
<?php
    }
    
    // Bestimme Phasen-Namen für diese Zeitzeile
    $phaseName = $currentPhase === 'group' ? 'Gruppenphase' : ($currentPhase === 'final' ? 'Finalrunde' : htmlspecialchars($currentPhase));
?>
<div class="mobile-time-block">
    <div class="mobile-time-header">
        <?= $time ?>
        <span class="badge bg-light text-dark ms-2" style="font-size: 0.75rem;"><?= $phaseName ?></span>
    </div>
    
    <?php for ($field = 1; $field <= 2; $field++): ?>
        <?php if (isset($timeData[$field])): 
            $m = $timeData[$field];
            $team1 = resolveTeamToName($db, $m["team1_id"], $m["team1_ref"]);
            $team2 = resolveTeamToName($db, $m["team2_id"], $m["team2_ref"]);
            
            $isTeam1Winner = ($m['finished'] && $m['winner_id'] == $m['team1_id']);
            $isTeam2Winner = ($m['finished'] && $m['winner_id'] == $m['team2_id']);
            $isTeam1Loser = ($m['finished'] && $m['loser_id'] == $m['team1_id']);
            $isTeam2Loser = ($m['finished'] && $m['loser_id'] == $m['team2_id']);
            
            $resultDisplay = "";
            if ($m["finished"]) {
                $sets = getSetResults($db, $m["id"]);
                if (count($sets) > 0) {
                    $setScores = [];
                    foreach ($sets as $set) {
                        $setScores[] = $set['team1_points'] . ":" . $set['team2_points'];
                    }
                    $resultDisplay = '<div class="mt-2"><span class="badge bg-success">' . implode(' | ', $setScores) . '</span></div>';
                }
            }
        ?>
        <div class="mobile-field-label">Feld <?= $field ?></div>
        <div class="mobile-match-content">
            <div class="match-card <?= $m['finished'] ? 'finished' : '' ?>">
                <div class="match-info mb-2">
                    <span class="badge bg-secondary"><?= htmlspecialchars($m["round"]) ?></span>
                    <span class="text-muted ms-2">#<?= $m["id"] ?></span>
                </div>
                
                <div class="match-teams">
                    <span class="<?= $isTeam1Winner ? 'text-success fw-bold' : ($isTeam1Loser ? 'team-loser' : '') ?>">
                        <?= htmlspecialchars($team1) ?>
                    </span>
                    <strong class="text-primary"> - </strong>
                    <span class="<?= $isTeam2Winner ? 'text-success fw-bold' : ($isTeam2Loser ? 'team-loser' : '') ?>">
                        <?= htmlspecialchars($team2) ?>
                    </span>
                </div>
                
                <?= $resultDisplay ?>
                
                <div class="match-info mt-2">
                    <small>
                        <strong>Schiri:</strong> 
                        <?= $m["referee"] ? htmlspecialchars($m["referee"]) : "<em class='text-muted'>TBD</em>" ?>
                    </small>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endfor; ?>
</div>
<?php endforeach; ?>
</div>

</body>
</html>

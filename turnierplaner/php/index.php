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
            return $teamRef; // Zeige Referenz, bis Gruppe gespielt ist
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
            
            return $teamRef; // Noch nicht gespielt
        }
    }
    
    return "TBD";
}

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
    border-left: 4px solid #28a745;
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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: bold;
    font-size: 1.1rem;
}
.team-loser {
    color: #f08080 !important;
}
</style>

<div class="table-responsive">
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
    
    // Bestimme Gruppe für diese Zeitzeile (vom ersten Match)
    $groupName = null;
    if (isset($timeData[1]) && $timeData[1]['group_name']) {
        $groupName = $timeData[1]['group_name'];
    } elseif (isset($timeData[2]) && $timeData[2]['group_name']) {
        $groupName = $timeData[2]['group_name'];
    }
?>
<tr>
  <td class="time-header text-center">
    <?= $time ?>
    <?php if ($groupName): ?>
      <div class="mt-1">
        <span class="badge bg-primary" style="font-size: 0.75rem;">Gruppe <?= htmlspecialchars($groupName) ?></span>
      </div>
    <?php endif; ?>
  </td>
  
  <?php for ($field = 1; $field <= 2; $field++): ?>
    <td>
      <?php if (isset($timeData[$field])): 
          $m = $timeData[$field];
          $team1 = resolveTeam($db, $m["team1_id"], $m["team1_ref"]);
          $team2 = resolveTeam($db, $m["team2_id"], $m["team2_ref"]);
          
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

</body>
</html>

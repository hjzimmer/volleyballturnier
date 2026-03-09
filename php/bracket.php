<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Turnierbaum</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .bracket-container {
      display: flex;
      justify-content: space-around;
      align-items: center;
      padding: 40px 20px;
      overflow-x: auto;
    }
    
    .bracket-round {
      display: flex;
      flex-direction: column;
      justify-content: space-around;
      min-width: 220px;
      margin: 0 15px;
    }
    
    .bracket-round-title {
      text-align: center;
      font-weight: bold;
      font-size: 1.1rem;
      margin-bottom: 20px;
      color: #495057;
    }
    
    .bracket-match {
      background: white;
      border: 2px solid #dee2e6;
      border-radius: 8px;
      margin: 20px 0;
      padding: 0;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      transition: all 0.3s;
    }
    
    .bracket-match:hover {
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
      transform: translateY(-2px);
    }
    
    .bracket-match.finished {
      border-color: #28a745;
    }
    
    /* Mobile Optimierung für Turnierbaum */
    @media (max-width: 768px) {
        body.container {
            padding-left: 5px;
            padding-right: 5px;
        }
        
        .nav-tabs {
            font-size: 0.85rem;
        }
        
        .nav-link {
            padding: 0.4rem 0.6rem;
        }
        
        .bracket-container {
            padding: 20px 10px;
            justify-content: flex-start;
        }
        
        .bracket-round {
            min-width: 180px;
            margin: 0 8px;
        }
        
        .bracket-round-title {
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .bracket-match {
            margin: 15px 0;
            font-size: 0.85rem;
        }
        
        .bracket-team {
            padding: 8px !important;
        }
        
        .bracket-match-info {
            padding: 5px !important;
            font-size: 0.7rem !important;
        }
    }
    
    @media (max-width: 576px) {
        .bracket-round {
            min-width: 150px;
            margin: 0 5px;
        }
        
        .bracket-round-title {
            font-size: 0.8rem;
        }
        
        .bracket-match {
            font-size: 0.75rem;
        }
        
        .bracket-team {
            padding: 6px !important;
        }
    }
    
    .match-header {
      background: #f8f9fa;
      padding: 8px 12px;
      border-bottom: 1px solid #dee2e6;
      font-size: 0.85rem;
      color: #6c757d;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .match-team {
      padding: 12px 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #e9ecef;
      transition: background 0.2s;
    }
    
    .match-team:last-child {
      border-bottom: none;
    }
    
    .match-team.winner {
      background: #d4edda;
      font-weight: bold;
    }
    
    .match-team.loser {
      color: #6c757d;
    }
    
    .team-name {
      flex: 1;
      font-size: 0.95rem;
    }
    
    .team-name.tbd {
      font-style: italic;
      color: #adb5bd;
    }
    
    .team-score {
      font-weight: bold;
      font-size: 1rem;
      margin-left: 10px;
      min-width: 60px;
      text-align: right;
      font-family: 'Courier New', monospace;
    }
    
    .set-scores {
      display: flex;
      gap: 8px;
      margin-left: 10px;
      align-items: center;
    }
    
    .set-score {
      font-weight: bold;
      font-size: 1rem;
      min-width: 28px;
      text-align: center;
      font-family: 'Courier New', monospace;
      padding: 2px 6px;
      background: #f8f9fa;
      border-radius: 4px;
    }
    
    .set-score.won {
      background: #d4edda;
      color: #155724;
    }
    
    .set-score.lost {
      background: #f8d7da;
      color: #721c24;
    }
    
    .set-score.draw {
      background: #fff3cd;
      color: #856404;
    }
    
    .connector {
      position: relative;
      height: 2px;
      background: #dee2e6;
      width: 30px;
      margin: 0 -15px;
    }
    
    .finals-group {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 60px;
    }
    
    .champion-display {
      text-align: center;
      padding: 20px;
      background: linear-gradient(135deg, #ffd700, #ffed4e);
      border-radius: 15px;
      box-shadow: 0 6px 12px rgba(255, 215, 0, 0.4);
      min-width: 250px;
      margin: 20px 0;
    }
    
    .champion-display h3 {
      margin: 0 0 10px 0;
      font-size: 1.2rem;
      color: #333;
    }
    
    .champion-display .champion-name {
      font-size: 1.8rem;
      font-weight: bold;
      color: #b8860b;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }
    
    .third-place-section {
      margin-top: 40px;
      padding-top: 40px;
      border-top: 3px dashed #dee2e6;
    }
    
    @media (max-width: 992px) {
      .bracket-container {
        flex-direction: column;
      }
      
      .bracket-round {
        min-width: 100%;
        margin: 20px 0;
      }
    }
  </style>
</head>
<body class="container-fluid py-4" style="background: #f5f7fa;">

<div class="container">
  <?php include 'header.php'; ?>

  <ul class="nav nav-tabs mb-4">
    <li class="nav-item"><a class="nav-link" href="index.php">Spielplan</a></li>
    <li class="nav-item"><a class="nav-link" href="groups.php">Gruppen</a></li>
    <li class="nav-item"><a class="nav-link" href="table.php">Gesamt</a></li>
    <li class="nav-item"><a class="nav-link active" href="bracket.php">Turnierbaum</a></li>
    <li class="nav-item"><a class="nav-link" href="result_entry.php">Ergebnisse</a></li>
  </ul>
</div>

<?php
require 'db.php';
require_once 'helpFunctions.php';

// Funktion zum Holen der Satzergebnisse
function getMatchSets($db, $matchId) {
    $stmt = $db->prepare("
        SELECT set_number, team1_points, team2_points 
        FROM sets 
        WHERE match_id = ? 
        ORDER BY set_number
    ");
    $stmt->execute([$matchId]);
    return $stmt->fetchAll();
}

// Funktion zum Formatieren der Ergebnisse für HTML
function formatMatchResultHTML($sets, $isTeam1) {
    if (empty($sets)) {
        return '';
    }
    
    $html = '<div class="set-scores">';
    
    foreach ($sets as $set) {
        $team1Points = $set['team1_points'];
        $team2Points = $set['team2_points'];
        $points = $isTeam1 ? $team1Points : $team2Points;
        
        // Bestimme CSS-Klasse basierend auf Gewinn/Verlust
        $cssClass = 'set-score';
        if ($team1Points > $team2Points) {
            $cssClass .= $isTeam1 ? ' won' : ' lost';
        } elseif ($team2Points > $team1Points) {
            $cssClass .= $isTeam1 ? ' lost' : ' won';
        } else {
            $cssClass .= ' draw';
        }
        
        $html .= '<span class="' . $cssClass . '">' . $points . '</span>';
    }
    
    $html .= '</div>';
    return $html;
}

// Hole alle Final-Matches gruppiert nach Runden
$matches = $db->query("
    SELECT id, round, team1_id, team2_id, team1_ref, team2_ref,
           winner_id, loser_id, finished, start_time, field_number
    FROM matches
    WHERE phase = 'final'
    ORDER BY id
")->fetchAll();

// Gruppiere Matches nach Typ/Stufe
$matchesByType = [
    'Zwischenrunde' => [],
    'Halbfinale' => [],
    'Platzierung' => [],
    'Finale' => []
];

foreach ($matches as $match) {
    $round = $match['round'];
    
    if (strpos($round, 'Zwischenrunde') !== false) {
        $matchesByType['Zwischenrunde'][] = $match;
    } elseif (strpos($round, 'Halbfinale') !== false) {
        $matchesByType['Halbfinale'][] = $match;
    } elseif (strpos($round, 'Platz') !== false) {
        $matchesByType['Platzierung'][] = $match;
    } elseif (strpos($round, 'Finale') !== false && strpos($round, 'Halbfinale') === false) {
        $matchesByType['Finale'][] = $match;
    }
}

// Bestimme Champion
$champion = null;
if (!empty($matchesByType['Finale'])) {
    $finalMatch = $matchesByType['Finale'][0];
    if ($finalMatch['finished'] && $finalMatch['winner_id']) {
        $stmt = $db->prepare("SELECT name FROM teams WHERE id = ?");
        $stmt->execute([$finalMatch['winner_id']]);
        $champion = $stmt->fetchColumn();
    }
}

// Funktion zum Rendern eines Matches
function renderMatch($db, $match) {
    $team1Name = resolveTeamToName($db, $match['team1_id'], $match['team1_ref']);
    $team2Name = resolveTeamToName($db, $match['team2_id'], $match['team2_ref']);
    $sets = getMatchSets($db, $match['id']);
    $isWinner1 = $match['finished'] && $match['winner_id'] == $match['team1_id'];
    $isWinner2 = $match['finished'] && $match['winner_id'] == $match['team2_id'];
    
    ob_start();
    ?>
    <div class="bracket-match <?= $match['finished'] ? 'finished' : '' ?>">
      <div class="match-header">
        <span><?= htmlspecialchars($match['round']) ?> (#<?= $match['id'] ?>)</span>
        <div>
          <?php if ($match['start_time']): ?>
            <span>⏰ <?= date('H:i', strtotime($match['start_time'])) ?></span>
          <?php endif; ?>
          <?php if ($match['field_number']): ?>
            <span class="ms-2">🏐 Feld <?= $match['field_number'] ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="match-team <?= $isWinner1 ? 'winner' : ($match['finished'] && !$isWinner1 && $match['winner_id'] ? 'loser' : '') ?>">
        <span class="team-name <?= $team1Name == 'TBD' || strpos($team1Name, '_') !== false ? 'tbd' : '' ?>">
          <?= htmlspecialchars($team1Name) ?>
        </span>
        <?= formatMatchResultHTML($sets, true) ?>
      </div>
      <div class="match-team <?= $isWinner2 ? 'winner' : ($match['finished'] && !$isWinner2 && $match['winner_id'] ? 'loser' : '') ?>">
        <span class="team-name <?= $team2Name == 'TBD' || strpos($team2Name, '_') !== false ? 'tbd' : '' ?>">
          <?= htmlspecialchars($team2Name) ?>
        </span>
        <?= formatMatchResultHTML($sets, false) ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<div style="max-width: 1400px; margin: 0 auto;">
  
  <!-- Zwischenrunde -->
  <?php if (!empty($matchesByType['Zwischenrunde'])): ?>
  <div class="mb-5">
    <h3 class="text-center mb-4">🎯 Zwischenrunde</h3>
    <div class="row">
      <?php foreach ($matchesByType['Zwischenrunde'] as $match): ?>
        <div class="col-md-6 col-lg-3 mb-3">
          <?= renderMatch($db, $match) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Halbfinale -->
  <?php if (!empty($matchesByType['Halbfinale'])): ?>
  <div class="mb-5">
    <h3 class="text-center mb-4">⚔️ Halbfinale</h3>
    <div class="row">
      <?php foreach ($matchesByType['Halbfinale'] as $match): ?>
        <div class="col-md-6 col-lg-3 mb-3">
          <?= renderMatch($db, $match) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Platzierungsspiele -->
  <?php if (!empty($matchesByType['Platzierung'])): ?>
  <div class="mb-5">
    <h3 class="text-center mb-4">🥉 Platzierungsspiele</h3>
    <div class="row">
      <?php foreach ($matchesByType['Platzierung'] as $match): ?>
        <div class="col-md-6 col-lg-3 mb-3">
          <?= renderMatch($db, $match) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Finale -->
  <?php if (!empty($matchesByType['Finale'])): ?>
  <div class="mb-5">
    <h3 class="text-center mb-4">🏆 Finale</h3>
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-4">
        <?= renderMatch($db, $matchesByType['Finale'][0]) ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
<!-- Champion Display -->
  <?php if ($champion): ?>
  <div class="mb-5">
    <div class="d-flex justify-content-center">
      <div class="champion-display">
        <h3>🥇 Turniersieger</h3>
        <div class="champion-name"><?= htmlspecialchars($champion) ?></div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  
</div>

<div class="container mt-4">
  <div class="alert alert-info">
    <small>
      <strong>Legende:</strong><br>
      • <strong>Grüner Rahmen</strong> = Match beendet<br>
      • <strong>Grüner Hintergrund</strong> = Gewinner des Matches<br>
      • <strong>Grauer Text</strong> = Verlierer des Matches<br>
      • <strong>Satz-Spalten:</strong> Jede Zahl steht für die Punkte des Teams in diesem Satz<br>
      • <strong>Farben der Sätze:</strong> <span class="badge" style="background: #d4edda; color: #155724;">Grün</span> = Gewonnen, 
        <span class="badge" style="background: #f8d7da; color: #721c24;">Rot</span> = Verloren, 
        <span class="badge" style="background: #fff3cd; color: #856404;">Gelb</span> = Unentschieden
    </small>
  </div>
</div>

</body>
</html>

<?php
session_start();

// Lade Passwort aus turnier_config.json
$configPath = __DIR__ . '/../turnier_config.json';
$config = json_decode(file_get_contents($configPath), true);
$requiredPassword = $config['result_entry_password'] ?? 'admin';
$setsPerMatch = $config['sets_per_match'] ?? 2;

// Logout-Funktion
if (isset($_GET['logout'])) {
    unset($_SESSION['result_entry_authenticated']);
    header('Location: result_entry.php');
    exit;
}

// Login-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $requiredPassword) {
        $_SESSION['result_entry_authenticated'] = true;
        header('Location: result_entry.php');
        exit;
    } else {
        $loginError = true;
    }
}

// Prüfe ob authentifiziert
if (!isset($_SESSION['result_entry_authenticated']) || $_SESSION['result_entry_authenticated'] !== true) {
    // Zeige Login-Formular
    ?>
    <!doctype html>
    <html lang="de">
    <head>
      <meta charset="utf-8">
      <title>Login - Ergebniseingabe</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="container py-4">
    
    <div class="row justify-content-center mt-5">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">🔒 Ergebniseingabe</h2>
                    
                    <?php if (isset($loginError)): ?>
                        <div class="alert alert-danger">Falsches Passwort!</div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="mb-3">
                            <label for="password" class="form-label">Passwort</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   required autofocus placeholder="Passwort eingeben">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Anmelden</button>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <small class="text-muted">Passwort in <code>turnier_config.json</code> konfiguriert</small>
                    </div>
                </div>
            </div>
            
            <div class="mt-3 text-center">
                <a href="index.php" class="btn btn-link">← Zurück zum Spielplan</a>
            </div>
        </div>
    </div>
    
    </body>
    </html>
    <?php
    exit;
}

// Benutzer ist authentifiziert, zeige normale Seite
require 'db.php';
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Ergebniseingabe</title>
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
        
        .btn-sm {
            font-size: 0.75rem;
            padding: 0.3rem 0.5rem;
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
        
        /* Buttons in der Tabelle kleiner */
        .table .btn {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
        }
        
        /* Dropdown in Tabelle kleiner */
        .table select {
            font-size: 0.75rem;
            padding: 0.2rem;
        }
        
        /* Modal kompakter */
        .modal-body {
            padding: 10px;
        }
        
        .modal-title {
            font-size: 1rem;
        }
        
        /* Aktionsbuttons untereinander statt nebeneinander */
        .action-buttons .btn {
            display: block;
            width: 100%;
            margin-bottom: 5px;
        }
        
        .action-buttons form {
            display: block;
            width: 100%;
        }
    }
    
    @media (max-width: 576px) {
        .table {
            font-size: 0.7rem;
        }
        
        .table td, .table th {
            padding: 0.3rem 0.2rem;
        }
        
        /* Sehr kleine Buttons */
        .table .btn {
            font-size: 0.65rem;
            padding: 0.15rem 0.3rem;
        }
        
        /* Button-Text auf sehr kleinen Screens kürzen */
        .btn-action-text {
            display: none;
        }
        
        /* Badge kürzer */
        .badge {
            font-size: 0.6rem;
            padding: 0.2rem 0.3rem;
        }
    }
  </style>
</head>
<body class="container py-4">

<?php include 'header.php'; ?>

<div class="d-flex justify-content-end align-items-center mb-3">
    <a href="print_match_cards.php" target="_blank" class="btn btn-sm btn-success me-2">
        🖨️ Spielberichtsbogen drucken
    </a>
    <a href="result_entry.php?logout=1" class="btn btn-sm btn-outline-secondary">🔓 Abmelden</a>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item"><a class="nav-link" href="index.php">Spielplan</a></li>
  <li class="nav-item"><a class="nav-link" href="groups.php">Gruppen</a></li>
  <li class="nav-item"><a class="nav-link" href="table.php">Gesamt</a></li>
  <li class="nav-item"><a class="nav-link" href="bracket.php">Turnierbaum</a></li>
  <li class="nav-item"><a class="nav-link active" href="result_entry.php">Ergebnisse</a></li>
</ul>

<?php

// Funktion zum Auflösen von Team-Referenzen
function resolveTeam($db, $teamId, $teamRef) {
    if ($teamId) {
        $stmt = $db->prepare("SELECT name FROM teams WHERE id = ?");
        $stmt->execute([$teamId]);
        return $stmt->fetchColumn();
    }
    if ($teamRef) {
        if (strpos($teamRef, '_') !== false && in_array($teamRef[0], ['A', 'B'])) {
            return $teamRef;
        }
        if (strpos($teamRef, 'W_') === 0 || strpos($teamRef, 'L_') === 0) {
            return $teamRef;
        }
    }
    return "TBD";
}

// Ergebnis speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_result'])) {
    $matchId = $_POST['match_id'];
    
    // Sammle Satzergebnisse dynamisch
    $sets = [];
    for ($i = 1; $i <= $setsPerMatch; $i++) {
        $sets[] = [
            'team1' => intval($_POST['set' . $i . '_team1']),
            'team2' => intval($_POST['set' . $i . '_team2'])
        ];
    }
    
    // Bestimme Gewinner
    $team1Wins = 0;
    $team2Wins = 0;
    
    // Zähle Satzgewinne (Unentschieden werden nicht gezählt)
    foreach ($sets as $set) {
        if ($set['team1'] > $set['team2']) {
            $team1Wins++;
        } elseif ($set['team2'] > $set['team1']) {
            $team2Wins++;
        }
    }
    
    // Hole Team IDs, Runde und Phase
    $match = $db->prepare("SELECT team1_id, team2_id, round, phase FROM matches WHERE id = ?");
    $match->execute([$matchId]);
    $matchData = $match->fetch();
    
    // Bestimme ob Playoff-Match (alle Endrunden-Matches)
    $isPlayoff = ($matchData['phase'] === 'final');
    
    // Validierung: In der Endrunde keine Punktgleichheit erlauben
    if ($isPlayoff) {
        $totalTeam1 = $set1Team1 + $set2Team1;
        $totalTeam2 = $set1Team2 + $set2Team2;
        
        if ($totalTeam1 === $totalTeam2) {
            $errorMsg = "❌ Fehler: In Endrunden-Matches ist Punktgleichheit nicht erlaubt! (Team 1: $totalTeam1 Punkte, Team 2: $totalTeam2 Punkte). Bitte korrigiere das Ergebnis.";
            goto skip_save;
        }
    }
    
    // Bestimme Gewinner/Verlierer
    if ($team1Wins > $team2Wins) {
        $winnerId = $matchData['team1_id'];
        $loserId = $matchData['team2_id'];
    } elseif ($team2Wins > $team1Wins) {
        $winnerId = $matchData['team2_id'];
        $loserId = $matchData['team1_id'];
    } else {
        // 1:1 Satzstand
        if ($isPlayoff) {
            // In der Endrunde entscheidet die Punktdifferenz
            $totalTeam1 = $set1Team1 + $set2Team1;
            $totalTeam2 = $set1Team2 + $set2Team2;
            
            if ($totalTeam1 > $totalTeam2) {
                $winnerId = $matchData['team1_id'];
                $loserId = $matchData['team2_id'];
            } else {
                $winnerId = $matchData['team2_id'];
                $loserId = $matchData['team1_id'];
            }
        } else {
            // In der Vorrunde bleibt es unentschieden
            $winnerId = null;
            $loserId = null;
        }
    }
    
    // Lösche alte Ergebnisse falls vorhanden
    $db->prepare("DELETE FROM sets WHERE match_id = ?")->execute([$matchId]);
    
    // Speichere neue Ergebnisse dynamisch
    $insertStmt = $db->prepare("INSERT INTO sets (match_id, set_number, team1_points, team2_points) VALUES (?, ?, ?, ?)");
    for ($i = 0; $i < $setsPerMatch; $i++) {
        $insertStmt->execute([$matchId, $i + 1, $sets[$i]['team1'], $sets[$i]['team2']]);
    }
    
    // Update Match
    $db->prepare("UPDATE matches SET finished = 1, winner_id = ?, loser_id = ? WHERE id = ?")
        ->execute([$winnerId, $loserId, $matchId]);
    
    // Aktualisiere nachfolgende Finalrunden-Matches
    updateFinalMatches($db, $matchId);
    
    // Weise Schiedsrichter für neu aufgelöste Finalrunden-Matches zu
    assignRefereesForFinalMatches($db);
    
    $successMsg = "Ergebnis erfolgreich gespeichert!";
    
    skip_save: // Label für Validierungsfehler
}

// Ergebnis löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_result'])) {
    $matchId = $_POST['match_id'];
    
    $db->prepare("DELETE FROM sets WHERE match_id = ?")->execute([$matchId]);
    $db->prepare("UPDATE matches SET finished = 0, winner_id = NULL, loser_id = NULL WHERE id = ?")
        ->execute([$matchId]);
    
    $successMsg = "Ergebnis erfolgreich gelöscht!";
}

// Funktion zum Aktualisieren von Finalrunden-Matches
function updateFinalMatches($db, $completedMatchId) {
    // Hole Informationen über das abgeschlossene Match
    $completedMatch = $db->query("SELECT round FROM matches WHERE id = $completedMatchId")->fetch();
    if (!$completedMatch) return;
    
    // Finde alle Matches, die auf dieses Match als Referenz warten
    $stmt = $db->query("SELECT id, team1_ref, team2_ref, team1_id, team2_id, phase FROM matches WHERE phase = 'final'");
    
    foreach ($stmt as $match) {
        $updated = false;
        $newTeam1Id = $match['team1_id'];
        $newTeam2Id = $match['team2_id'];
        
        // Prüfe team1_ref
        if ($match['team1_ref'] && !$match['team1_id']) {
            $teamId = resolveTeamReference($db, $match['team1_ref'], $completedMatchId, $completedMatch['round']);
            if ($teamId) {
                $newTeam1Id = $teamId;
                $updated = true;
            }
        }
        
        // Prüfe team2_ref
        if ($match['team2_ref'] && !$match['team2_id']) {
            $teamId = resolveTeamReference($db, $match['team2_ref'], $completedMatchId, $completedMatch['round']);
            if ($teamId) {
                $newTeam2Id = $teamId;
                $updated = true;
            }
        }
        
        if ($updated) {
            $db->prepare("UPDATE matches SET team1_id = ?, team2_id = ? WHERE id = ?")
                ->execute([$newTeam1Id, $newTeam2Id, $match['id']]);
        }
    }
}

// Funktion zum Auflösen einer Team-Referenz
function resolveTeamReference($db, $ref, $completedMatchId, $completedRound) {
    // Gruppenplatzierung (A_1, B_2, etc.)
    if (strpos($ref, '_') !== false && in_array($ref[0], ['A', 'B'])) {
        list($groupName, $position) = explode('_', $ref);
        $groupId = $groupName === 'A' ? 1 : 2;
        return getGroupStandingTeam($db, $groupId, intval($position));
    }
    
    // Match-Referenz (W_xxx, L_xxx)
    if (strpos($ref, 'W_') === 0 || strpos($ref, 'L_') === 0) {
        $field = strpos($ref, 'W_') === 0 ? 'winner_id' : 'loser_id';
        $matchKey = substr($ref, 2); // Entferne W_ oder L_
        
        // Prüfe ob es eine alte numerische Referenz ist (z.B. W_21)
        if (is_numeric($matchKey)) {
            $refMatchId = intval($matchKey);
            if ($refMatchId == $completedMatchId) {
                return $db->query("SELECT $field FROM matches WHERE id = $completedMatchId")->fetchColumn();
            }
        } 
        // Neue match_key-Referenz (z.B. W_Halbfinale_1)
        else {
            // Konvertiere match_key zu round-Name (Unterstriche → Leerzeichen)
            $roundName = str_replace('_', ' ', $matchKey);
            
            // Prüfe ob das abgeschlossene Match diesem round entspricht
            if (strcasecmp($completedRound, $roundName) === 0) {
                return $db->query("SELECT $field FROM matches WHERE id = $completedMatchId")->fetchColumn();
            }
        }
    }
    
    return null;
}

// Funktion zum Berechnen der Gruppentabelle und Ermittlung der Position
function getGroupStandingTeam($db, $groupId, $position) {
    // Hole alle Teams der Gruppe
    $teams = $db->prepare("SELECT team_id FROM group_teams WHERE group_id = ? ORDER BY team_id");
    $teams->execute([$groupId]);
    $teamIds = $teams->fetchAll(PDO::FETCH_COLUMN);
    
    $standings = [];
    
    foreach ($teamIds as $teamId) {
        $wins = 0;
        $losses = 0;
        $setsWon = 0;
        $setsLost = 0;
        $pointsWon = 0;
        $pointsLost = 0;
        
        // Hole alle Matches des Teams in der Gruppe
        $matchesStmt = $db->prepare("
            SELECT m.id, m.team1_id, m.team2_id, m.winner_id, m.finished,
                   s.set_number, s.team1_points, s.team2_points
            FROM matches m
            LEFT JOIN sets s ON s.match_id = m.id
            WHERE m.group_id = ? AND m.phase = 'group' 
              AND (m.team1_id = ? OR m.team2_id = ?)
              AND m.finished = 1
            ORDER BY m.id, s.set_number
        ");
        $matchesStmt->execute([$groupId, $teamId, $teamId]);
        $matches = $matchesStmt->fetchAll();
        
        $currentMatchId = null;
        foreach ($matches as $row) {
            if ($currentMatchId !== $row['id']) {
                $currentMatchId = $row['id'];
                if ($row['winner_id'] == $teamId) {
                    $wins++;
                } else {
                    $losses++;
                }
            }
            
            if ($row['set_number']) {
                $isTeam1 = $row['team1_id'] == $teamId;
                $myPoints = $isTeam1 ? $row['team1_points'] : $row['team2_points'];
                $opponentPoints = $isTeam1 ? $row['team2_points'] : $row['team1_points'];
                
                if ($myPoints > $opponentPoints) {
                    $setsWon++;
                } else {
                    $setsLost++;
                }
                
                $pointsWon += $myPoints;
                $pointsLost += $opponentPoints;
            }
        }
        
        $standings[] = [
            'team_id' => $teamId,
            'wins' => $wins,
            'losses' => $losses,
            'sets_won' => $setsWon,
            'sets_lost' => $setsLost,
            'set_diff' => $setsWon - $setsLost,
            'points_won' => $pointsWon,
            'points_lost' => $pointsLost,
            'point_diff' => $pointsWon - $pointsLost
        ];
    }
    
    // Sortiere nach: 1. Siege, 2. Satzdifferenz, 3. Punktdifferenz
    usort($standings, function($a, $b) {
        if ($a['wins'] != $b['wins']) return $b['wins'] - $a['wins'];
        if ($a['set_diff'] != $b['set_diff']) return $b['set_diff'] - $a['set_diff'];
        return $b['point_diff'] - $a['point_diff'];
    });
    
    // Rückgabe des Teams an der gewünschten Position
    if ($position > 0 && $position <= count($standings)) {
        return $standings[$position - 1]['team_id'];
    }
    
    return null;
}

// Funktion zum Zuweisen von Schiedsrichtern für Finalrunden-Matches
function assignRefereesForFinalMatches($db) {
    // Hole alle Finalrunden-Matches ohne Schiedsrichter, aber mit beiden Teams
    $matches = $db->query("
        SELECT id, team1_id, team2_id 
        FROM matches 
        WHERE phase = 'final' 
          AND team1_id IS NOT NULL 
          AND team2_id IS NOT NULL
          AND (referee_team_id IS NULL OR referee_team_id = 0)
    ")->fetchAll();
    
    foreach ($matches as $match) {
        $playingTeams = [$match['team1_id'], $match['team2_id']];
        
        // Finde Team mit wenigsten Schiedsrichter-Einsätzen, das nicht spielt
        $referee = $db->prepare("
            SELECT t.id, COUNT(m.id) as ref_count
            FROM teams t
            LEFT JOIN matches m ON m.referee_team_id = t.id
            WHERE t.id NOT IN (?, ?)
            GROUP BY t.id
            ORDER BY ref_count ASC, t.id ASC
            LIMIT 1
        ");
        $referee->execute($playingTeams);
        $refData = $referee->fetch();
        
        if ($refData) {
            $db->prepare("UPDATE matches SET referee_team_id = ? WHERE id = ?")
                ->execute([$refData['id'], $match['id']]);
        }
    }
}

// Hole alle Matches
$matches = $db->query("
    SELECT m.id, m.phase, m.round, m.start_time, m.finished, m.field_number,
           m.team1_id, m.team2_id, m.team1_ref, m.team2_ref, m.referee_team_id,
           t1.name AS team1_name, t2.name AS team2_name,
           r.name AS referee_name
    FROM matches m
    LEFT JOIN teams t1 ON t1.id = m.team1_id
    LEFT JOIN teams t2 ON t2.id = m.team2_id
    LEFT JOIN teams r ON r.id = m.referee_team_id
    ORDER BY m.start_time, m.id
")->fetchAll();

// Alle Teams laden für Schiedsrichter-Dropdown
$allTeams = $db->query("SELECT id, name FROM teams ORDER BY name")->fetchAll();
?>

<?php if (isset($successMsg)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= $successMsg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (isset($errorMsg)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= $errorMsg ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <strong>Alle Matches</strong>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th class="d-none d-md-table-cell">Zeit</th>
                                <th class="d-none d-lg-table-cell">Feld</th>
                                <th class="d-none d-sm-table-cell">#</th>
                                <th class="d-none d-md-table-cell">Runde</th>
                                <th>Teams</th>
                                <th class="d-none d-lg-table-cell">Schiedsrichter</th>
                                <th class="d-none d-sm-table-cell">Status</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($matches as $m): 
                            $team1 = $m['team1_name'] ?: resolveTeam($db, $m['team1_id'], $m['team1_ref']);
                            $team2 = $m['team2_name'] ?: resolveTeam($db, $m['team2_id'], $m['team2_ref']);
                            $time = $m['start_time'] ? date('H:i', strtotime($m['start_time'])) : '-';
                            $field = $m['field_number'] ? 'Feld ' . $m['field_number'] : '-';
                            
                            // Prüfe ob beide Teams feststehen
                            $canEnterResult = $m['team1_id'] && $m['team2_id'];
                        ?>
                            <tr class="<?= $m['finished'] ? 'table-success' : '' ?>">
                                <td class="d-none d-md-table-cell"><?= $time ?></td>
                                <td class="d-none d-lg-table-cell"><span class="badge bg-info"><?= $field ?></span></td>
                                <td class="d-none d-sm-table-cell"><?= $m['id'] ?></td>
                                <td class="d-none d-md-table-cell"><span class="badge bg-secondary"><?= htmlspecialchars($m['round']) ?></span></td>
                                <td>
                                    <!-- Mobile: Zeige mehr Infos in dieser Spalte -->
                                    <div class="d-md-none">
                                        <small class="text-muted">#<?= $m['id'] ?> • <?= $time ?></small><br>
                                    </div>
                                    <strong><?= htmlspecialchars($team1) ?></strong>
                                    <span class="text-muted">-</span>
                                    <strong><?= htmlspecialchars($team2) ?></strong>
                                    <!-- Mobile: Status hier anzeigen -->
                                    <div class="d-sm-none mt-1">
                                        <?php if ($m['finished']): 
                                            $sets = $db->prepare("SELECT set_number, team1_points, team2_points FROM sets WHERE match_id = ? ORDER BY set_number");
                                            $sets->execute([$m['id']]);
                                            $setResults = $sets->fetchAll(PDO::FETCH_ASSOC);
                                            $scoreDisplay = [];
                                            foreach ($setResults as $set) {
                                                $scoreDisplay[] = $set['team1_points'] . ':' . $set['team2_points'];
                                            }
                                        ?>
                                            <span class="badge bg-success">✓ Beendet</span>
                                            <small class="ms-2"><?= implode(' | ', $scoreDisplay) ?></small>
                                        <?php elseif ($canEnterResult): ?>
                                            <span class="badge bg-warning text-dark">Offen</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Warten</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="d-none d-lg-table-cell" style="width: 120px;">
                                    <select class="form-select form-select-sm referee-select" data-match-id="<?= $m['id'] ?>">
                                        <option value="">-- Kein Schiri --</option>
                                        <?php foreach ($allTeams as $team):
                                            $isPlaying = ($team['id'] == $m['team1_id']) || ($team['id'] == $m['team2_id']);
                                            $selected = ($team['id'] == $m['referee_team_id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?= $team['id'] ?>" <?= $selected ?> <?= $isPlaying ? 'disabled' : '' ?>>
                                                <?= htmlspecialchars($team['name']) ?><?= $isPlaying ? ' (spielt)' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="d-none d-sm-table-cell">
                                    <?php if ($m['finished']): 
                                        // Hole Satzergebnisse
                                        $sets = $db->prepare("SELECT set_number, team1_points, team2_points FROM sets WHERE match_id = ? ORDER BY set_number");
                                        $sets->execute([$m['id']]);
                                        $setResults = $sets->fetchAll(PDO::FETCH_ASSOC);
                                        $scoreDisplay = [];
                                        foreach ($setResults as $set) {
                                            $scoreDisplay[] = $set['team1_points'] . ':' . $set['team2_points'];
                                        }
                                    ?>
                                        <span class="badge bg-success">✓ Beendet</span>
                                        <div class="small mt-1"><?= implode(' | ', $scoreDisplay) ?></div>
                                    <?php elseif ($canEnterResult): ?>
                                        <span class="badge bg-warning text-dark">Offen</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Warten auf Teams</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($canEnterResult): ?>
                                        <button class="btn btn-sm btn-primary mb-1" data-bs-toggle="modal" data-bs-target="#resultModal<?= $m['id'] ?>">
                                            <span class="d-none d-sm-inline"><?= $m['finished'] ? 'Bearbeiten' : 'Eintragen' ?></span>
                                            <span class="d-inline d-sm-none"><?= $m['finished'] ? '✏️' : '➕' ?></span>
                                        </button>
                                        <?php if ($m['finished']): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                                                <button type="submit" name="delete_result" class="btn btn-sm btn-danger" onclick="return confirm('Ergebnis wirklich löschen?')">
                                                    <span class="d-none d-sm-inline">Löschen</span>
                                                    <span class="d-inline d-sm-none">🗑️</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Modal für Ergebniseingabe -->
                            <?php if ($canEnterResult): 
                                // Hole vorhandene Ergebnisse
                                $setsQuery = $db->prepare("SELECT set_number, team1_points, team2_points FROM sets WHERE match_id = ? ORDER BY set_number");
                                $setsQuery->execute([$m['id']]);
                                $setResults = $setsQuery->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Erstelle Array mit allen Sätzen (mit Defaults)
                                $existingSets = [];
                                for ($i = 0; $i < $setsPerMatch; $i++) {
                                    $existingSets[$i] = $setResults[$i] ?? ['team1_points' => '', 'team2_points' => ''];
                                }
                            ?>
                            <div class="modal fade" id="resultModal<?= $m['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Match #<?= $m['id'] ?> - <?= htmlspecialchars($m['round']) ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="post">
                                            <div class="modal-body">
                                                <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                                                
                                                <div class="mb-3">
                                                    <h6><?= htmlspecialchars($team1) ?> - <?= htmlspecialchars($team2) ?></h6>
                                                </div>
                                                
                                                <?php for ($setNum = 1; $setNum <= $setsPerMatch; $setNum++): 
                                                    $setData = $existingSets[$setNum - 1];
                                                ?>
                                                <div class="mb-3">
                                                    <label class="form-label"><strong>Satz <?= $setNum ?></strong></label>
                                                    <div class="row">
                                                        <div class="col">
                                                            <input type="number" name="set<?= $setNum ?>_team1" class="form-control" 
                                                                   placeholder="<?= htmlspecialchars($team1) ?>" 
                                                                   value="<?= $setData['team1_points'] ?>" required min="0" max="30">
                                                        </div>
                                                        <div class="col-auto d-flex align-items-center">:</div>
                                                        <div class="col">
                                                            <input type="number" name="set<?= $setNum ?>_team2" class="form-control" 
                                                                   placeholder="<?= htmlspecialchars($team2) ?>" 
                                                                   value="<?= $setData['team2_points'] ?>" required min="0" max="30">
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endfor; ?>
                                                
                                                <div class="alert alert-info">
                                                    <small>Der Gewinner wird automatisch ermittelt. Bei Bedarf werden nachfolgende Finalrunden-Matches aktualisiert.</small>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                                                <button type="submit" name="save_result" class="btn btn-primary">Speichern</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const selects = document.querySelectorAll('.referee-select');
  
  selects.forEach(select => {
    select.addEventListener('change', async function() {
      const matchId = this.dataset.matchId;
      const refereeTeamId = this.value;
      
      try {
        const response = await fetch('update_referee.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            match_id: matchId,
            referee_team_id: refereeTeamId
          })
        });
        
        const result = await response.json();
        
        if (result.success) {
          // Optional: Visuelles Feedback
          this.classList.add('border-success');
          setTimeout(() => {
            this.classList.remove('border-success');
          }, 1000);
        } else {
          alert('Fehler beim Aktualisieren: ' + result.error);
          // Setze Select zurück
          location.reload();
        }
      } catch (error) {
        alert('Fehler beim Aktualisieren: ' + error.message);
        location.reload();
      }
    });
  });
});
</script>

</body>
</html>

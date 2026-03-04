<?php
require_once 'helpFunctions.php';

session_start();

// Lade Passwort aus turnier_config.json
$configPath = __DIR__ . '/../turnier_config.json';
$config = json_decode(file_get_contents($configPath), true);
$requiredPassword = $config['result_entry_password'] ?? 'admin';
$setsPerMatch = $config['sets_per_match'] ?? 2;
$setMinutes = $config['set_minutes'] ?? 10;

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

// PrÃ¼fe ob authentifiziert
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
                    <h2 class="card-title text-center mb-4">ðŸ”’ Ergebniseingabe</h2>
                    
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
                <a href="index.php" class="btn btn-link">â† ZurÃ¼ck zum Spielplan</a>
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

<div class="d-flex justify-content-end align-items-center mb-3 flex-wrap gap-2">
    <div class="input-group input-group-sm" style="width: auto;">
        <input type="text" id="remoteTimerStart" class="form-control" placeholder="MM:SS" 
               pattern="[0-9]{1,2}:[0-9]{2}" value="<?php echo sprintf('%02d:00', $setMinutes); ?>" style="width: 80px;">
        <button id="startTimerBtn" class="btn btn-primary" onclick="sendTimerCommand('start', this)">
            ⏱️ Start
        </button>
        <button class="btn btn-warning" onclick="sendTimerCommand('pause', this)">
            ⏸️ Pause
        </button>
        <button class="btn btn-info" onclick="sendTimerCommand('reset', this)">
            🔄 Reset
        </button>
    </div>
    <a href="print_match_cards.php" target="_blank" class="btn btn-sm btn-success">
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
    
    // Bestimme ob Endrunden-Match (alle Endrunden-Matches)
    $isFinalMatch = ($matchData['phase'] === 'final');
    
    // Berechne Gesamtpunkte für beide Teams
    $totalTeam1 = 0;
    $totalTeam2 = 0;
    foreach ($sets as $set) {
        $totalTeam1 += $set['team1'];
        $totalTeam2 += $set['team2'];
    }
    
    // Validierung: In der Endrunde keine Punktgleichheit erlauben
    if ($isFinalMatch) {
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
        if ($isFinalMatch) {
            // In der Endrunde entscheidet die Punktdifferenz
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
    
    // Aktualisiere nachfolgende Matches, Referees für die Gruppen werden auch upgedated
    updateMatchesWithResolvedTeams($db, $matchId);
    
    // Weise Schiedsrichter für neu aufgelöste Finalrunden-Matches zu
    assignRefereesForMatches($db);
    
    $successMsg = "Ergebnis erfolgreich gespeichert!";
    
    skip_save: // Label für Validierungsfehler
}

// Ergebnis löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_result'])) {
    $matchId = $_POST['match_id'];
    
    // Hole Phase und Round des Matches
    $match = $db->query("SELECT phase, round, group_id FROM matches WHERE id = $matchId")->fetch();
    $isGroupMatch = ($match && $match['phase'] === 'group');
    $groupId = ($match && $match['group_id']);;
    
    // Bei Finalrunden-Spielen: Prüfe ob abhängige Matches betroffen sind
    $dependentMatches = [];
    if (!isset($_POST['confirm_cascade'])) {
        $dependentMatches = checkDependentMatches($db, $match['group_id']);
    }
    
    // Spiele mit teamIds>=0
    $depMatches = array_values(array_map(function($entry) {return $entry['match'];}, $dependentMatches));
    $validMatches = array_filter($depMatches, function($x) { 
        if ((isset($x['team1_id']) && $x['team1_id'] >= 0) ||  
            (isset($x['team2_id']) && $x['team2_id'] >= 0)) {
            return true;
        }
        return false;
    });
    // Wenn abhängige Matches vorhanden sind und noch nicht bestätigt wurde
    if ($validMatches !== [null] && !isset($_POST['confirm_cascade'])) {
        // Zeige Warnung mit Bestätigungsformular
        $cascadeWarning = [
            'match_id' => $matchId,
            'matches' => $validMatches
        ];
    } else {
        // Führe das Löschen durch
        $warnings = [];
        $warnings = resetDependentMatches($db, $matchId, $match['group_id']);

        $db->prepare("DELETE FROM sets WHERE match_id = ?")->execute([$matchId]);
        $db->prepare("UPDATE matches SET finished = 0, winner_id = NULL, loser_id = NULL WHERE id = ?")
            ->execute([$matchId]);
        
        $successMsg = "Ergebnis erfolgreich gelöscht!";
        if (!empty($warnings)) {
            $successMsg .= "<br><strong>Achtung:</strong> Folgende abhängige Matches haben bereits Ergebnisse und wurden zurückgesetzt:<br>" . implode("<br>", $warnings);
        }
    }
}

function getUniqueGroupIds($matches) {
    $ids = array_map(function($entry) {
        return isset($entry['group_id']) ? $entry['group_id'] : null;
    }, $matches);
    $ids = array_filter($ids, function($v) { return $v !== null; });
    return array_values(array_unique($ids));
}

// Funktion zum Aktualisieren von Dependent-Matches
function updateMatchesWithResolvedTeams($db, $completedMatchId) {
    // check ob die Phase für diese group_id abgeschlossen ist
    $groups = $db->query("SELECT group_id FROM matches WHERE id = $completedMatchId")->fetch();
    $changedGroup = $groups['group_id'];
   
    $stmt = $db->prepare("SELECT id FROM matches WHERE group_id = ? AND finished = 0");
    $stmt->execute([$changedGroup]);
    $openMatches = $stmt->fetch();
    if ($openMatches) {
        // Es gibt noch offene Matches in der Gruppe, daher keine Updates in den Finalrunden durchführen
        return;
    }
    // Prüfe ob abhängige Matches betroffen sind
    $dependentMatches = [];
    $foundDependencies = checkDependentMatches($db, $changedGroup);
    $dependentMatches = array_values(array_map(function($entry) { return $entry['match']; }, $foundDependencies));
    
    foreach ($dependentMatches as $match) {
        $updated = false;
        $newTeam1Id = $match['team1_id'];
        $newTeam2Id = $match['team2_id'];
        
        // Prüfe team1_ref
        if ($match['team1_ref'] && (!$match['team1_id'] || $match['team1_id'] == -1)) {
            // db, ref=ref des zu ändernden Matches, completedMatchId=ID beendetes Match, group_id=gruppe die beendet wurde
            $teamId = resolveTeamReference($db, $match['team1_ref'], $completedMatchId, $changedGroup);
            if ($teamId) {
                $newTeam1Id = $teamId;
                $updated = true;
            }
        }
        
        // Prüfe team2_ref
        if ($match['team2_ref'] && (!$match['team2_id'] || $match['team2_id'] == -1)) {
            $teamId = resolveTeamReference($db, $match['team2_ref'], $completedMatchId, $changedGroup);
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
    if (!empty($dependentMatches)) {
        $uniqueGroupIds = getUniqueGroupIds($dependentMatches);
        foreach ($uniqueGroupIds as $groupId) {
            assignRefereesForMatches($db, $groupId);
        }
    }
    
}

function checkForOneMatchWithRef($db, $match, $affectedMatches, $groupId) {
    $matchIsDependent = false;
    foreach ([['ref' => $match['team1_ref'], 'teamId' => $match['team1_id'], 'finished' => $match['finished'], 'side' => 1], ['ref' => $match['team2_ref'], 'teamId' => $match['team2_id'], 'finished' => $match['finished'], 'side' => 2]] as $refInfo) {
        if (!$refInfo['ref']) continue;
        $refInfoArr = json_decode($refInfo['ref'], true); // klappt
        if($refInfoArr['type'] && $refInfoArr['type'] === "group_place"  && ($refInfoArr['group'] === $groupId)) {
            // in refInfo ist ein Match, welches sich auf das aktuelle bezieht, egal ob finished oder nicht
            $matchIsDependent = true;
            break;
        }
        if($refInfoArr['type'] && $refInfoArr['type'] === "match_winner"  && ($refInfoArr['match_id'] === $groupId)) {
            // in refInfo ist ein Match, welches sich auf das aktuelle bezieht, egal ob finished oder nicht
            $matchIsDependent = true;
            break;
        }
    }
    if ($matchIsDependent) {
        $affectedMatches[] = [
            'match' => $match,
            'checked' => NULL
        ];
    }

    return $affectedMatches;
}

function deleteDuplicatesInAffected($affected) {
    $unique = [];
    $seen = [];
    foreach ($affected as $entry) {
        // Erzeuge einen eindeutigen Hash für das Match (z. B. anhand der ID)
        $matchId = is_array($entry['match']) && isset($entry['match']['id']) ? $entry['match']['id'] : md5(serialize($entry['match']));
        if (!isset($seen[$matchId])) {
            $unique[] = $entry;
            $seen[$matchId] = true;
        }
}
    return $unique;
}

// Funktion zum Prüfen abhängiger Finalrunden-Matches (ohne Löschen)
function checkDependentMatches($db, $groupId) {
    $affected = [];
    // Hole alle Matches, die auf dieses Match referenzieren (egal ob group_ref oder W_/L_)
    $allMatches = $db->query("SELECT id, round, phase, group_id, finished, team1_id, team2_id, team1_ref, team2_ref FROM matches")->fetchAll();
$saveCounter = 0; 
    $checkedGroups = [];
    while (true) {
        if ($affected == []) {
            // Erste Iteration: Prüfe direkt alle Matches gegen das zu löschende Match
            foreach ($allMatches as $match) {
                $affected = checkForOneMatchWithRef($db, $match, $affected, $groupId);
            }
        } else {
            // Weitere Iterationen: Prüfe das nächste Match aus affected, um Refs auf die affectes auch zu finden
            for ($i=0; $i<count($affected); $i++) {
                if ($affected[$i]['checked'] == true) continue;
                $affected[$i]['checked'] = true;
                $matchToCheck = $affected[$i]['match'];

                // wenn Gruppe schon geprüft, weiter, um unnötige Prüfungen zu vermeiden
                if (in_array($matchToCheck['group_id'], $checkedGroups)) continue;
                $checkedGroups[] = $matchToCheck['group_id'];
                foreach ($allMatches as $match) {
                    $affected = checkForOneMatchWithRef($db, $match, $affected, $matchToCheck['group_id']);
                }
                $affected = deleteDuplicatesInAffected($affected);
            }
        }
        $affected = deleteDuplicatesInAffected($affected);
        // Abbruch, wenn alle matches in affected auf checked=true sind, also alle bereits geprüft wurden
        $stillUncheckedMatches = count(array_filter($affected, function($entry) {
            return empty($entry['checked']);
        })) > 0;
        if (!$stillUncheckedMatches) {
            break;
        }
// Sicherheitsabbruch
$saveCounter++;
if ($saveCounter>10) {
    logge("Abbruch nach 10 Iterationen. Möglicherweise zyklische Referenzen oder Fehler in der Logik.", 'red');
    break;
}

    }
    return $affected;
}

// Robustes, rekursives Zurücksetzen aller abhängigen Matches (egal ob Gruppen, ZG, Final)
function resetDependentMatches($db, $deletedMatchId, $groupId) {
    $warnings = [];
    // Hole alle Matches, die auf dieses Match referenzieren
    $allMatches = checkDependentMatches($db, $groupId);
    $allAffectedMatches = array_values(array_map(function($entry) { return $entry['match']; }, $allMatches));

    foreach ($allAffectedMatches as $match) {
        $newTeam1Id = $match['team1_id'];
        $newTeam2Id = $match['team2_id'];
        foreach ([['ref' => $match['team1_ref'], 'side' => 1], ['ref' => $match['team2_ref'], 'side' => 2]] as $refInfo) {
            $ref = $refInfo['ref'];
            $side = $refInfo['side'];
            if (!$ref) continue;

            $refInfoArr = json_decode($refInfo['ref'], true); // klappt
            if($refInfoArr['type'] && $refInfoArr['type'] === "group_place"  && ($refInfoArr['group'] === $groupId)) {
                // in refInfo ist ein Match, welches sich auf das aktuelle bezieht, egal ob finished oder nicht
                if ($side === 1) {$newTeam1Id = -1; $resetRef = true;}
                if ($side === 2) {$newTeam2Id = -1; $resetRef = true;}
            }
            if($refInfoArr['type'] && $refInfoArr['type'] === "match_winner"  && ($refInfoArr['match_id'] === $groupId)) {
                // in refInfo ist ein Match, welches sich auf das aktuelle bezieht, egal ob finished oder nicht
                if ($side === 1) {$newTeam1Id = -1; $resetRef = true;}
                if ($side === 2) {$newTeam2Id = -1; $resetRef = true;}
            }
        }

        if ($match['finished'] == 1) {
            $warnings[] = "• " . htmlspecialchars($match['round']) . "/" . htmlspecialchars($match['id']) . " (Ergebnis wurde ebenfalls gelöscht)";
            $db->prepare("DELETE FROM sets WHERE match_id = ?")->execute([$match['id']]);
            $sql = "UPDATE matches SET finished = 0, winner_id = NULL, loser_id = NULL, ";
            $sql .= $resetRef ? "referee_team_id = NULL, " : "";
            $sql .= "team1_id = ?, team2_id = ? WHERE id = ?";
            $db->prepare($sql)->execute([$newTeam1Id, $newTeam2Id, $match['id']]);
        } else {
            $sql = "UPDATE matches SET team1_id = ?, team2_id = ? ";
            $sql .= $resetRef ? ", referee_team_id = NULL " : "";
            $sql .= "WHERE id = ?";
            $db->prepare($sql)->execute([$newTeam1Id, $newTeam2Id, $match['id']]);
        }
    }
    return $warnings;
}

// Funktion zum Auflösen einer Team-Referenz, gibt die TeamID einer referenz zurück
// db, ref=ref des zu ändernden Matches, completedMatchId=ID beendetes Match, group_id=gruppe die beendet wurde
function resolveTeamReference($db, $ref, $completedMatchId, $completedGroupId) {
    // Gruppenplatzierung (type: group_place, group: G1, place: 1)
    $refArray = json_decode($ref, true);
    if ($refArray && isset($refArray['type']) && $refArray['type'] === 'group_place' && isset($refArray['group']) && isset($refArray['place'])) {
        $groupId = $refArray['group'];
        $position = $refArray['place'];
        if ($groupId !== $completedGroupId) {
            return -1;
        }

        $standings = calculateGroupStandings($db, $groupId);
        if (isset($standings[$position - 1])) {
            return $standings[$position - 1]['id'];
        }
        return -1;
    }
    
    // Match-Referenz (type: match_winner, match_id: 21, winner: true/false)
    if ($refArray && isset($refArray['type']) && $refArray['type'] === 'match_winner' && isset($refArray['match_id']) && isset($refArray['winner'])) {
        $field = $refArray['winner'] ? 'winner_id' : 'loser_id';
        if ($refArray['match_id'] == $completedGroupId) {
            return $db->query("SELECT $field FROM matches WHERE id = $completedMatchId")->fetchColumn();
        }
    }
    
    return -1;
}

// Funktion zum Zuweisen von Schiedsrichtern für Finalrunden-Matches und GruppenMatches
function assignRefereesForMatches($db, $groupId = null) {
    // Hole alle Finalrunden-Matches ohne Schiedsrichter, aber mit beiden Teams
    $phase = 'final';
    if ($groupId !== null) {
        $stmt = $db->prepare("
            SELECT id, team1_id, team2_id, start_time, referee_team_id
            FROM matches 
            WHERE phase = 'group' AND group_id = ?
            AND team1_id IS NOT NULL AND team1_id >= 0
            AND team2_id IS NOT NULL AND team2_id >= 0
        ");
        $stmt->execute([$groupId]);
        $matches = $stmt->fetchAll();
    } else {
        $matches = $db->query("
            SELECT id, team1_id, team2_id, start_time, referee_team_id
            FROM matches 
            WHERE phase = 'final' 
            AND team1_id IS NOT NULL AND team1_id >= 0
            AND team2_id IS NOT NULL AND team2_id >= 0
        ")->fetchAll();
    }
    
    foreach ($matches as $match) {
        // Prüfe, ob der Schiedsrichter ein spielendes Team ist - falls ja, lösche Zuweisung
        if ($match['referee_team_id'] && 
            ($match['referee_team_id'] == $match['team1_id'] || 
             $match['referee_team_id'] == $match['team2_id'])) {
            $db->prepare("UPDATE matches SET referee_team_id = NULL WHERE id = ?")
                ->execute([$match['id']]);
            $match['referee_team_id'] = null; // Für weitere Verarbeitung
        }
        
        // Überspringe, wenn bereits ein gültiger Schiedsrichter zugewiesen ist
        if ($match['referee_team_id']) {
            continue;
        }
        
        $playingTeams = [$match['team1_id'], $match['team2_id']];
        $startTime = $match['start_time'];
        
        // Finde alle Teams, die zur gleichen Zeit auf anderen Feldern spielen
        $concurrentMatches = $db->prepare("
            SELECT team1_id, team2_id 
            FROM matches 
            WHERE start_time = ? 
              AND id != ?
              AND team1_id IS NOT NULL 
              AND team2_id IS NOT NULL
        ");
        $concurrentMatches->execute([$startTime, $match['id']]);
        
        foreach ($concurrentMatches->fetchAll() as $concMatch) {
            $playingTeams[] = $concMatch['team1_id'];
            $playingTeams[] = $concMatch['team2_id'];
        }
        
        // Schließe Teams aus, die bereits als Schiedsrichter zur gleichen Zeit zugewiesen sind
        $alreadyRefereeing = $db->prepare("
            SELECT referee_team_id 
            FROM matches 
            WHERE start_time = ? 
              AND id != ? 
              AND referee_team_id IS NOT NULL
        ");
        $alreadyRefereeing->execute([$startTime, $match['id']]);
        
        foreach ($alreadyRefereeing->fetchAll() as $ref) {
            $playingTeams[] = $ref['referee_team_id'];
        }
        
        // Finde den nächsten Zeitslot
        $nextTimeStmt = $db->prepare("
            SELECT MIN(start_time) as next_time
            FROM matches
            WHERE start_time > ?
        ");
        $nextTimeStmt->execute([$startTime]);
        $nextTime = $nextTimeStmt->fetch();
        
        if ($nextTime && $nextTime['next_time']) {
            // Hole alle Teams, die im nächsten Zeitslot spielen
            $nextSlotMatches = $db->prepare("
                SELECT team1_id, team2_id 
                FROM matches 
                WHERE start_time = ?
                  AND team1_id IS NOT NULL 
                  AND team2_id IS NOT NULL
            ");
            $nextSlotMatches->execute([$nextTime['next_time']]);
            
            foreach ($nextSlotMatches->fetchAll() as $nextMatch) {
                $playingTeams[] = $nextMatch['team1_id'];
                $playingTeams[] = $nextMatch['team2_id'];
            }
        }
        
        $playingTeams = array_unique($playingTeams);   // entfernt duplikate
        $playingTeams = array_values($playingTeams);   // indiziert die keys linear durch

        // Sicherheitscheck: wenn keine Teams ausgeschlossen werden können, überspringe
        if (count($playingTeams) == 0) {
            continue;
        }
        
        // Erstelle Platzhalter für SQL IN-Klausel
        $placeholders = implode(',', array_fill(0, count($playingTeams), '?'));

        // Finde Team mit wenigsten Schiedsrichter-Einsätzen, das nicht zur gleichen Zeit spielt
        $referee = $db->prepare("
            SELECT t.id, COUNT(m.id) as ref_count
            FROM teams t
            LEFT JOIN matches m ON m.referee_team_id = t.id
            WHERE t.id NOT IN ($placeholders)
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

<?php if (isset($cascadeWarning)): ?>
<div class="alert alert-warning" role="alert">
    <h5 class="alert-heading"><strong>⚠️ ACHTUNG: Abhängige Spiele werden zurückgesetzt!</strong></h5>
    <p>Das Löschen dieses Ergebnisses betrifft auch folgende Spiele:</p>
    <ul>
        <?php foreach ($cascadeWarning['matches'] as $affected): ?>
            <li>
                <strong><?= htmlspecialchars($affected['round']) ?></strong>
                <?php if ($affected['finished'] == 1): ?>
                    <span class="badge bg-danger">Hat bereits ein Ergebnis (wird gelöscht)</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark">Teams werden zurückgesetzt</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <hr>
    <p class="mb-3"><strong>Möchten Sie wirklich fortfahren?</strong></p>
    <form method="post" class="d-inline">
        <input type="hidden" name="match_id" value="<?= $cascadeWarning['match_id'] ?>">
        <input type="hidden" name="confirm_cascade" value="1">
        <button type="submit" name="delete_result" class="btn btn-danger">
            Ja, Ergebnis und alle abhängigen Spiele löschen
        </button>
        <button type="button" class="btn btn-secondary" onclick="window.location.href='result_entry.php'">
            Abbrechen
        </button>
    </form>
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
                            // Behandle -1 wie NULL für nicht initialisiert
                            $team1_id = ($m['team1_id'] === null || $m['team1_id'] == -1) ? null : $m['team1_id'];
                            $team2_id = ($m['team2_id'] === null || $m['team2_id'] == -1) ? null : $m['team2_id'];
                            // Zeige Teamnamen oder referenz als String
                            $team1 = resolveTeamToName($db, $team1_id, $m['team1_ref']); //($team1_id === null ) ? $tempTeam1 : $m['team1_name'];
                            $team2 = resolveTeamToName($db, $team2_id, $m['team2_ref']); //($team2_id === null ) ? $tempTeam2 : $m['team2_name'];
                            $time = $m['start_time'] ? date('H:i', strtotime($m['start_time'])) : '-';
                            $field = $m['field_number'] ? 'Feld ' . $m['field_number'] : '-';
                            // Prüfe ob beide Teams feststehen
                            $canEnterResult = $team1_id && $team2_id;
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
                                        <?php if (!$m['finished']): ?>
                                        <button class="btn btn-sm btn-primary mb-1" data-bs-toggle="modal" data-bs-target="#resultModal<?= $m['id'] ?>">
                                            <span class="d-none d-sm-inline"><?= $m['finished'] ? 'Bearbeiten' : 'Eintragen' ?></span>
                                            <span class="d-inline d-sm-none"><?= $m['finished'] ? '✏️' : '➕' ?></span>
                                        </button>
                                        <?php endif; ?>
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
                                                                   value="<?= $setData['team1_points'] ?>" required min="0" max="99">
                                                        </div>
                                                        <div class="col-auto d-flex align-items-center">:</div>
                                                        <div class="col">
                                                            <input type="number" name="set<?= $setNum ?>_team2" class="form-control" 
                                                                   placeholder="<?= htmlspecialchars($team2) ?>" 
                                                                   value="<?= $setData['team2_points'] ?>" required min="0" max="99">
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

// Timer Remote Control
async function sendTimerCommand(command, buttonElement) {
  try {
    // Payload mit optionaler Startzeit
    const payload = { command: command };
    if (command === 'start') {
      const startTimeInput = document.getElementById('remoteTimerStart');
      if (startTimeInput && startTimeInput.value) {
        payload.startTime = startTimeInput.value;
      }
    }
    
    await fetch('Timer/timer_control.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    });
  } catch (error) {
    console.error('Fehler beim Senden des Timer-Befehls:', error);
  }
}
</script>

</body>
</html>

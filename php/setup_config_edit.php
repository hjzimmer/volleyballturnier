<?php
require 'db.php';
require 'helpFunctions.php';

$configPath = __DIR__ . '/../data/turnier_config.json';

// Laden der aktuellen Konfiguration
$config = json_decode(file_get_contents($configPath), true);

function speichereTurnierConfig($config, $configPath, $postData) {
    $config['tournament_name'] = $postData['tournament_name'] ?? $config['tournament_name'];
    $config['logo_path'] = $postData['logo_path'] ?? $config['logo_path'];
    $config['tournament_start'] = $postData['tournament_start'] ?? $config['tournament_start'];
    $config['fields'] = (int)($postData['fields'] ?? $config['fields']);
    $config['sets_per_match'] = (int)($postData['sets_per_match'] ?? $config['sets_per_match']);
    $config['set_minutes'] = (int)($postData['set_minutes'] ?? $config['set_minutes']);
    $config['pause_between_sets'] = (int)($postData['pause_between_sets'] ?? $config['pause_between_sets']);
    $config['pause_between_matches'] = (int)($postData['pause_between_matches'] ?? $config['pause_between_matches']);
    $config['lunch_break']['start'] = $postData['lunch_break_start'] ?? $config['lunch_break']['start'];
    $config['lunch_break']['duration_minutes'] = (int)($postData['lunch_break_duration'] ?? $config['lunch_break']['duration_minutes']);

    // Speichern
    file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $successMsg = 'Konfiguration erfolgreich gespeichert!';
    return $successMsg;
}

// Turnier-Initialisierung ausführen, wenn Button gedrückt wurde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['init_tournament'])) {
    // Speichere aktuelle Konfiguration vor Initialisierung
    speichereTurnierConfig($config, $configPath, $_POST);

    require_once 'setup_db_tools.php';
    require_once 'setup_group_stage_tools.php';
    require_once 'setup_tournament_phases.php';
    
    // 1. Datenbank neu anlegen  
    $db = init_db($db);
    
    // 2. Teams und Gruppen anlegen
    seed_teams($db);
    seed_groups($db);
    
    // 3. Gruppenspiele erzeugen
    $group_tables = prepare_all_group_matches_and_tables($db, $configPath);
    

    create_final_matches($db, $configPath, $group_tables);
    schedule_all_matches($db, $configPath);
    assign_group_referees($db);

    echo '<div class="alert alert-success mt-3">Turnierdatenbank wurde erfolgreich neu erstellt!</div>';

} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_phase'])) {
    $selectedPhaseIdx = isset($_POST['phase_select']) ? (int)$_POST['phase_select'] : null;
    if ($selectedPhaseIdx !== null && isset($config['phases'][$selectedPhaseIdx])) {
        $phase = &$config['phases'][$selectedPhaseIdx];
        $phase['name'] = $_POST['phase_name'];
        $phase['id'] = $_POST['phase_id'];
        if (isset($phase['matches']) && isset($_POST['matches'])) {
            foreach ($_POST['matches'] as $midx => $mdata) {
                $phase['matches'][$midx]['id'] = $mdata['id'];
                $phase['matches'][$midx]['name'] = $mdata['name'];
                $phase['matches'][$midx]['team1'] = json_decode($mdata['team1'], true);
                $phase['matches'][$midx]['team2'] = json_decode($mdata['team2'], true);
                $phase['matches'][$midx]['winner_placement'] = ($mdata['winner_placement'] === '0' || $mdata['winner_placement'] === 0) ? null : $mdata['winner_placement'];
                $phase['matches'][$midx]['loser_placement'] = ($mdata['loser_placement'] === '0' || $mdata['loser_placement'] === 0) ? null : $mdata['loser_placement'];
            }
        }
        if (isset($phase['groups']) && isset($_POST['groups'])) {
            foreach ($_POST['groups'] as $gidx => $gdata) {
                $phase['groups'][$gidx]['id'] = $gdata['id'];
                $phase['groups'][$gidx]['name'] = $gdata['name'];
                // Prüfe, ob alle Teams int sind, sonst als Referenz übernehmen
                $teams = $gdata['teams'] ?? [];
                $allInt = true;
                foreach ($teams as $t) {
                    if (!is_numeric($t) || (string)(int)$t !== (string)$t) {
                        $allInt = false;
                        break;
                    }
                }
                if ($allInt) {
                    $phase['groups'][$gidx]['teams'] = array_map('intval', $teams);
                } else {
                    // Referenzen als JSON decodieren
                    $phase['groups'][$gidx]['teams'] = array_values(array_filter(array_map(function($v) {
                        $v = trim($v);
                        if ($v === '') return null;
                        $decoded = json_decode($v, true);
                        return $decoded !== null ? $decoded : $v;
                    }, $teams)));
                }
            }
        }
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $successMsg = 'Phase erfolgreich gespeichert!';
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $successMsg = speichereTurnierConfig($config, $configPath, $_POST);
}
?>

<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Turnier Setup</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
        <nav class="navbar navbar-expand-lg navbar-light bg-light mb-3">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">Setup</a>
                <div class="d-flex">
                    <a href="result_entry.php" class="btn btn-outline-secondary me-2">Zurück zur Ergebniseingabe</a>
                </div>
            </div>
        </nav>
        <h1>Turnier Setup</h1>
    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success"><?= $successMsg ?></div>
    <?php endif; ?>
    <form method="post" class="row g-3">
      <input type="hidden" name="save_config">
      <fieldset class="border p-3 mb-4">
        <legend class="w-auto px-2">Turnierkonfiguration</legend>
        <div class="row g-3">
          <div class="col-md-6">
              <label class="form-label">Turniername</label>
              <input type="text" name="tournament_name" class="form-control" value="<?= htmlspecialchars($config['tournament_name']) ?>">
          </div>
          <div class="col-md-6">
              <label class="form-label">Logo-Pfad</label>
              <input type="text" name="logo_path" class="form-control" value="<?= htmlspecialchars($config['logo_path']) ?>">
          </div>
          <div class="col-md-4">
              <label class="form-label">Turnierstart (Datum/Uhrzeit)</label>
              <input type="datetime-local" name="tournament_start" class="form-control" value="<?= str_replace(' ', 'T', htmlspecialchars($config['tournament_start'])) ?>">
          </div>
          <div class="col-md-2">
              <label class="form-label">Felder</label>
              <input type="number" name="fields" class="form-control" value="<?= (int)$config['fields'] ?>" min="1">
          </div>
          <div class="col-md-2">
              <label class="form-label">Sätze pro Spiel</label>
              <input type="number" name="sets_per_match" class="form-control" value="<?= (int)$config['sets_per_match'] ?>" min="1">
          </div>
          <div class="col-md-2">
              <label class="form-label">Minuten pro Satz</label>
              <input type="number" name="set_minutes" class="form-control" value="<?= (int)$config['set_minutes'] ?>" min="1">
          </div>
          <div class="col-md-2">
              <label class="form-label">Pause zw. Sätzen (min)</label>
              <input type="number" name="pause_between_sets" class="form-control" value="<?= (int)$config['pause_between_sets'] ?>" min="0">
          </div>
          <div class="col-md-2">
              <label class="form-label">Pause zw. Spielen (min)</label>
              <input type="number" name="pause_between_matches" class="form-control" value="<?= (int)$config['pause_between_matches'] ?>" min="0">
          </div>
          <div class="col-md-4">
              <label class="form-label">Mittagspause Start (Datum/Uhrzeit)</label>
              <input type="datetime-local" name="lunch_break_start" class="form-control" value="<?= str_replace(' ', 'T', htmlspecialchars($config['lunch_break']['start'])) ?>">
          </div>
          <div class="col-md-2">
              <label class="form-label">Mittagspause Dauer (min)</label>
              <input type="number" name="lunch_break_duration" class="form-control" value="<?= (int)$config['lunch_break']['duration_minutes'] ?>" min="1">
          </div>
        </div>
<!---
        <div class="col-12">
                <p></p>
                <button type="submit" class="btn btn-primary">Speichern</button>
        </div>
--->
      </fieldset>
        <!-- Teams-Editor -->
        <fieldset class="border p-3 mb-4">
            <legend class="w-auto px-2">Teams bearbeiten</legend>
            <?php
            $teamConfigPath = __DIR__ . '/../data/team_config.json';
            $teamConfig = json_decode(file_get_contents($teamConfigPath), true);
            $teams = $teamConfig['teams'] ?? [];
            $warteliste = $teamConfig['warteliste'] ?? [];
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_teams'])) {
                // Teams aktualisieren
                $newTeams = [];
                if (isset($_POST['teams'])) {
                    foreach ($_POST['teams'] as $t) {
                        if (trim($t['name']) !== '') {
                            $newTeams[] = [
                                'id' => (int)$t['id'],
                                'name' => $t['name']
                            ];
                        }
                    }
                }
                // Warteliste aktualisieren
                $newWarteliste = [];
                if (isset($_POST['warteliste'])) {
                    foreach ($_POST['warteliste'] as $t) {
                        if (trim($t['name']) !== '') {
                            $newWarteliste[] = [
                                'id' => (int)$t['id'],
                                'name' => $t['name']
                            ];
                        }
                    }
                }
                $teamConfig['teams'] = $newTeams;
                $teamConfig['warteliste'] = $newWarteliste;
                file_put_contents($teamConfigPath, json_encode($teamConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                echo '<div class="alert alert-success mt-3">Teams erfolgreich gespeichert!</div>';
                // Reload für aktuelle Anzeige
                $teams = $newTeams;
                $warteliste = $newWarteliste;
            }
            ?>
            <form method="post">
                <h5>Teams</h5>
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr><th>ID</th><th>Name</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($teams as $idx => $team): ?>
                        <tr>
                            <td><input type="number" name="teams[<?= $idx ?>][id]" class="form-control form-control-sm" value="<?= htmlspecialchars($team['id']) ?>"></td>
                            <td><input type="text" name="teams[<?= $idx ?>][name]" class="form-control form-control-sm" value="<?= htmlspecialchars($team['name']) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    <!-- Neue Zeile für weiteres Team -->
                    <tr>
                        <td><input type="number" name="teams[<?= count($teams) ?>][id]" class="form-control form-control-sm" value=""></td>
                        <td><input type="text" name="teams[<?= count($teams) ?>][name]" class="form-control form-control-sm" value=""></td>
                    </tr>
                    </tbody>
                </table>
                <h5>Warteliste</h5>
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr><th>ID</th><th>Name</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($warteliste as $idx => $team): ?>
                        <tr>
                            <td><input type="number" name="warteliste[<?= $idx ?>][id]" class="form-control form-control-sm" value="<?= htmlspecialchars($team['id']) ?>"></td>
                            <td><input type="text" name="warteliste[<?= $idx ?>][name]" class="form-control form-control-sm" value="<?= htmlspecialchars($team['name']) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    <!-- Neue Zeile für weiteres Team auf Warteliste -->
                    <tr>
                        <td><input type="number" name="warteliste[<?= count($warteliste) ?>][id]" class="form-control form-control-sm" value=""></td>
                        <td><input type="text" name="warteliste[<?= count($warteliste) ?>][name]" class="form-control form-control-sm" value=""></td>
                    </tr>
                    </tbody>
                </table>
                <button type="submit" name="save_teams" class="btn btn-primary">Teams speichern</button>
            </form>
        </fieldset>

        <fieldset class="border p-3 mb-4">
            <legend class="w-auto px-2">Turnierdatenbank initialisieren</legend>
            <div class="mb-3">
                <p class="text-danger"><strong>Achtung:</strong> Durch das Initialisieren werden alle bestehenden Turnierdaten gelöscht und das Turnier gemäß aktueller Konfiguration neu angelegt!</p>
            </div>
            <form method="post" onsubmit="return confirm('Alle Turnierdaten werden gelöscht und neu erstellt! Fortfahren?');">
                <button type="submit" name="init_tournament" class="btn btn-danger">Turnierdatenbank neu erstellen</button>
            </form>
        </fieldset>
    </form>

    <!-- Phasen-Editor -->
    <fieldset class="border p-3 mb-4">
        <legend class="w-auto px-2">Phasen bearbeiten</legend>
        <?php $phases = $config['phases'] ?? []; ?>
        <form method="post" id="phaseEditForm">
            <div class="mb-3">
                <label for="phase_select" class="form-label">Phase auswählen</label>
                <select id="phase_select" name="phase_select" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Bitte wählen --</option>
                    <?php foreach ($phases as $idx => $phase): ?>
                        <option value="<?= $idx ?>" <?= (isset($_POST['phase_select']) && $_POST['phase_select'] == $idx) ? 'selected' : '' ?>><?= htmlspecialchars($phase['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php
        $selectedPhaseIdx = isset($_POST['phase_select']) ? (int)$_POST['phase_select'] : null;
        if ($selectedPhaseIdx !== null && isset($phases[$selectedPhaseIdx])):
            $phase = $phases[$selectedPhaseIdx];
        ?>
        <form method="post">
            <input type="hidden" name="phase_select" value="<?= $selectedPhaseIdx ?>">
            <div class="mb-3">
                <label class="form-label">Phasenname</label>
                <input type="text" name="phase_name" class="form-control" value="<?= htmlspecialchars($phase['name']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">ID</label>
                <input type="text" name="phase_id" class="form-control" value="<?= htmlspecialchars($phase['id']) ?>">
            </div>
            <?php if (isset($phase['matches'])): ?>
                <div class="mb-3">
                    <label class="form-label">Matches</label>
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Team 1</th>
                                <th>Team 2</th>
                                <th>Winner-Platz</th>
                                <th>Loser-Platz</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($phase['matches'] as $midx => $match): ?>
                            <tr>
                                <td><input type="text" name="matches[<?= $midx ?>][id]" class="form-control form-control-sm" value="<?= htmlspecialchars($match['id']) ?>"></td>
                                <td><input type="text" name="matches[<?= $midx ?>][name]" class="form-control form-control-sm" value="<?= htmlspecialchars($match['name']) ?>"></td>
                                <td><input type="text" name="matches[<?= $midx ?>][team1]" class="form-control form-control-sm" value="<?= htmlspecialchars(json_encode($match['team1'], JSON_UNESCAPED_UNICODE)) ?>"></td>
                                <td><input type="text" name="matches[<?= $midx ?>][team2]" class="form-control form-control-sm" value="<?= htmlspecialchars(json_encode($match['team2'], JSON_UNESCAPED_UNICODE)) ?>"></td>
                                <td><input type="text" name="matches[<?= $midx ?>][winner_placement]" class="form-control form-control-sm" value="<?= ($match['winner_placement'] === null ? '0' : htmlspecialchars(json_encode($match['winner_placement'], JSON_UNESCAPED_UNICODE))) ?>"></td>
                                <td><input type="text" name="matches[<?= $midx ?>][loser_placement]" class="form-control form-control-sm" value="<?= ($match['loser_placement'] === null ? '0' : htmlspecialchars(json_encode($match['loser_placement'], JSON_UNESCAPED_UNICODE))) ?>"></td>

                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif (isset($phase['groups'])): ?>
                <?php
                // Teams aus data/team_config.json laden
                $teamConfig = json_decode(file_get_contents(__DIR__ . '/../data/team_config.json'), true);
                $allTeams = $teamConfig['teams'] ?? [];
                // Prüfe, ob alle Teams in allen Gruppen int sind (echte IDs)
                $allGroupsHaveIntTeams = true;
                foreach ($phase['groups'] as $group) {
                    foreach ($group['teams'] as $t) {
                        if (!is_int($t)) {
                            $allGroupsHaveIntTeams = false;
                            break 2;
                        }
                    }
                }
                ?>
                <div class="mb-3">
                    <label class="form-label">Gruppen</label>
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Teams</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($phase['groups'] as $gidx => $group): ?>
                            <tr>
                                <td><input type="text" name="groups[<?= $gidx ?>][id]" class="form-control form-control-sm" value="<?= htmlspecialchars($group['id']) ?>"></td>
                                <td><input type="text" name="groups[<?= $gidx ?>][name]" class="form-control form-control-sm" value="<?= htmlspecialchars($group['name']) ?>"></td>
                                <td>
                                <?php if ($allGroupsHaveIntTeams): ?>
                                    <div class="d-flex flex-wrap" style="gap: 10px;">
                                    <?php foreach ($allTeams as $team): ?>
                                        <div class="form-check" style="min-width:220px;">
                                            <input class="form-check-input" type="checkbox" name="groups[<?= $gidx ?>][teams][]" value="<?= $team['id'] ?>" id="group<?= $gidx ?>_team<?= $team['id'] ?>" <?= (in_array($team['id'], $group['teams']) ? 'checked' : '') ?>>
                                            <label class="form-check-label" for="group<?= $gidx ?>_team<?= $team['id'] ?>">
                                                <?= htmlspecialchars($team['name']) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                <?php else:?>
                                    <!-- Teams als Referenzen: JSON-String pro Team -->
                                    <?php foreach ($group['teams'] as $tidx => $teamRef): ?>
                                        <input type="text" name="groups[<?= $gidx ?>][teams][<?= $tidx ?>]" class="form-control form-control-sm mb-1" value="<?= htmlspecialchars(json_encode($teamRef, JSON_UNESCAPED_UNICODE)) ?>">
                                    <?php endforeach; ?>
                                    <input type="text" name="groups[<?= $gidx ?>][teams][<?= count($group['teams']) ?>]" class="form-control form-control-sm mb-1" value="">
                                    <small class="text-muted">Team-Referenzen als JSON, z.B. {"type":"group_place","group":"G1","place":1}</small>
                                <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <button type="submit" name="save_phase" class="btn btn-primary">Phase speichern</button>
        </form>
        <?php endif; ?>
    </fieldset>
</body>
</html>

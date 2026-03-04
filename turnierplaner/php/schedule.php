
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

$stmt = $db->query("
SELECT m.id, m.round, m.start_time, m.field_number, m.finished,
       m.team1_id, m.team2_id,
       m.team1_ref, m.team2_ref
FROM matches m
ORDER BY m.id
");
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Turnierplan</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">

<?php 
include 'header.php'; 
?>

<table border='1'>
<tr><th>Zeit&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</th><th>Feld&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</th><th>Match&nbsp;&nbsp;&nbsp;</th><th>Runde</th><th>Team 1</th><th>Team 2</th><th>Ergebnis</th></tr>
<?php foreach ($stmt as $row): 
    $team1 = resolveTeamToName($db, $row['team1_id'], $row['team1_ref']);
    $team2 = resolveTeamToName($db, $row['team2_id'], $row['team2_ref']);
    $time = $row['start_time'] ? date('H:i', strtotime($row['start_time'])) : '-';
    $field = $row['field_number'] ? 'Feld ' . $row['field_number'] : '-';
    
    // Hole Satzergebnisse wenn Match beendet
    $resultDisplay = "-";
    if ($row["finished"]) {
        $sets = getSetResults($db, $row["id"]);
        if (count($sets) > 0) {
            $setScores = [];
            foreach ($sets as $set) {
                $setScores[] = $set['team1_points'] . ":" . $set['team2_points'];
            }
            $resultDisplay = implode(' | ', $setScores);
        }
    }
?>
<tr>
<td><?= $time ?></td>
<td><?= $field ?></td>
<td><?= $row['id'] ?></td>
<td><?= $row['round'] ?></td>
<td><?= $team1 ?></td>
<td><?= $team2 ?></td>
<td><?= $resultDisplay ?></td>
</tr>
<?php endforeach; ?>
</table>

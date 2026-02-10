
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

<h1>Turnierplan</h1>
<table border='1'>
<tr><th>Zeit</th><th>Feld</th><th>Match</th><th>Runde</th><th>Team 1</th><th>Team 2</th><th>Ergebnis</th></tr>
<?php foreach ($stmt as $row): 
    $team1 = resolveTeam($db, $row['team1_id'], $row['team1_ref']);
    $team2 = resolveTeam($db, $row['team2_id'], $row['team2_ref']);
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

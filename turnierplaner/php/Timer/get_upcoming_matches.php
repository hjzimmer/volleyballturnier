<?php
/**
 * Get Upcoming Matches API
 * Holt die nächsten 4 Spiele ohne Ergebnis aus der Datenbank
 */

header('Content-Type: application/json');

$dbPath = __DIR__ . '/../../data/tournament.db';

if (!file_exists($dbPath)) {
    echo json_encode(['error' => 'Datenbank nicht gefunden']);
    exit;
}

try {
    $db = new PDO("sqlite:" . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Hole die nächsten 4 Spiele ohne Ergebnis, sortiert nach Startzeit
    $query = "
        SELECT 
            m.id,
            m.field_number,
            m.start_time,
            t1.name as team1_name,
            t2.name as team2_name,
            tr.name as referee_name
        FROM matches m
        LEFT JOIN teams t1 ON m.team1_id = t1.id
        LEFT JOIN teams t2 ON m.team2_id = t2.id
        LEFT JOIN teams tr ON m.referee_team_id = tr.id
        WHERE m.finished = 0
        ORDER BY m.start_time ASC
        LIMIT 4
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatiere die Daten für das Frontend
    $result = [];
    foreach ($matches as $index => $match) {
        // Parse Start-Zeit
        $startTime = date('H:i', strtotime($match['start_time']));
        
        // Erste 2 Spiele sind "current", nächste 2 sind "next"
        $status = $index < 2 ? 'current' : 'next';
        
        $result[] = [
            'id' => $match['id'],
            'field' => $match['field_number'],
            'time' => $startTime,
            'team1' => $match['team1_name'] ?: 'TBD',
            'team2' => $match['team2_name'] ?: 'TBD',
            'referee' => $match['referee_name'] ?: '—',
            'status' => $status
        ];
    }
    
    echo json_encode($result);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Datenbankfehler: ' . $e->getMessage()]);
}

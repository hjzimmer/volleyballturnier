<?php
require 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['match_id']) || !isset($data['referee_team_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$matchId = (int)$data['match_id'];
$refereeTeamId = $data['referee_team_id'] === '' ? null : (int)$data['referee_team_id'];

try {
    $stmt = $db->prepare("UPDATE matches SET referee_team_id = ? WHERE id = ?");
    $stmt->execute([$refereeTeamId, $matchId]);
    
    // Hole den Namen des neuen Schiedsrichters
    $refereeName = null;
    if ($refereeTeamId) {
        $stmt = $db->prepare("SELECT name FROM teams WHERE id = ?");
        $stmt->execute([$refereeTeamId]);
        $refereeName = $stmt->fetchColumn();
    }
    
    echo json_encode([
        'success' => true,
        'referee_name' => $refereeName
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

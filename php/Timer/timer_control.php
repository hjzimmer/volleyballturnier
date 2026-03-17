<?php
/**
 * Timer Control API
 * Ermöglicht Remote-Steuerung des Countdown-Timers
 */

header('Content-Type: application/json');

$controlFile = __DIR__ . '/timer_commands.json';

// GET: Befehl abrufen
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($controlFile)) {
        $data = json_decode(file_get_contents($controlFile), true);
        echo json_encode($data);
    } else {
        echo json_encode(['command' => null, 'timestamp' => null]);
    }
    exit;
}

// POST: Befehl senden
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['command'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Command required']);
        exit;
    }
    
    $command = $input['command'];
    $allowedCommands = ['start', 'pause', 'reset'];
    if ($command === 'status') {
        // Timer-Status speichern
        $statusFile = __DIR__ . '/timer_status.json';
        $data = [
            'time' => $input['time'],
            'running' => $input['running'],
            'paused' => $input['paused'],
            'timestamp' => time()
        ];
        file_put_contents($statusFile, json_encode($data));
        echo json_encode(['success' => true, 'status' => $data]);
        exit;
    }
    if (!in_array($command, $allowedCommands)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid command. Allowed: ' . implode(', ', $allowedCommands) . ', status']);
        exit;
    }

    $data = [
        'command' => $command,
        'timestamp' => time()
    ];

    // Optional: Startzeit für Start-Befehl
    if ($command === 'start' && isset($input['startTime'])) {
        $data['startTime'] = $input['startTime'];
    }

    file_put_contents($controlFile, json_encode($data));
    echo json_encode(['success' => true, 'command' => $command]);
    exit;
}

// DELETE: Befehl löschen (nach Ausführung)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (file_exists($controlFile)) {
        unlink($controlFile);
    }
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

<?php
set_time_limit(0);

$stateFile = __DIR__ . '/timer_status.json';

/**
 * POST → Status aktualisieren
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    if ($data) {
        $data['updated'] = time();
        file_put_contents($stateFile, json_encode($data), LOCK_EX);
    }
    http_response_code(204);
    exit;
}

/**
 * GET → SSE Stream
 */

// Output-Buffering vollständig deaktivieren (Apache puffert sonst bis 4 KB)
ini_set('zlib.output_compression', false);
while (ob_get_level() > 0) ob_end_clean();
ob_implicit_flush(true);

header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");
header("Connection: keep-alive");
header("Content-Encoding: identity"); // mod_deflate/gzip deaktivieren

$lastHash = $_GET['last'] ?? '';
$start = time();
$maxLifetime = 3600; // 1h → danach Reconnect

while (true) {

    if (connection_aborted()) {
        exit;
    }

    clearstatcache();
    $json = file_get_contents($stateFile);
    $hash = md5($json);

    if ($hash !== $lastHash) {
        echo "event: update\n";
        echo "data: {$json}\n\n";
        $lastHash = $hash;
    }

    // Heartbeat (Proxy / Browser wach halten)
    echo ": ping\n\n";

  //  ob_flush();
    flush();

    // Sauberer Reconnect nach X Zeit
    if (time() - $start > $maxLifetime) {
        exit;
    }

    usleep(500000); // 500 ms
}

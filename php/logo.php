<?php
// Lade Logo-Pfad aus Konfiguration
$config = json_decode(file_get_contents(__DIR__ . '/../data/turnier_config.json'), true);
$logoPath = $config['logo_path'] ?? '';

if (empty($logoPath)) {
    http_response_code(404);
    exit;
}

$logoFile = __DIR__ . '/../' . $logoPath;

if (!file_exists($logoFile)) {
    http_response_code(404);
    exit;
}

// Bestimme MIME-Type
$extension = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'webp' => 'image/webp'
];

$mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

// Sende Header und Bild
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($logoFile));
header('Cache-Control: public, max-age=86400'); // 1 Tag Cache
readfile($logoFile);

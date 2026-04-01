<?php
require_once 'setup_db_tools.php';

// Prüfe ob SQLite verfügbar ist
if (!extension_loaded('pdo_sqlite')) {
    die('SQLite PDO Extension ist nicht geladen. Bitte aktiviere "extension=pdo_sqlite" in deiner php.ini');
}

$dbPath = __DIR__ . '/../data/tournament.db';
if (!file_exists($dbPath)) {
    createNewDb($dbPath);
}

$db = new PDO("sqlite:" . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
?>
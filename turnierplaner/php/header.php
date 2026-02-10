<?php
// Lade Turnier-Konfiguration
$config = json_decode(file_get_contents(__DIR__ . '/../turnier_config.json'), true);
$tournamentName = $config['tournament_name'] ?? 'Volleyball Turnier';
$logoPath = $config['logo_path'] ?? '';

// Prüfe ob Logo existiert
$logoExists = !empty($logoPath) && file_exists(__DIR__ . '/../' . $logoPath);
?>
<div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
    <h2 class="mb-0">🏐 <?= htmlspecialchars($tournamentName) ?></h2>
    <?php if ($logoExists): ?>
        <img src="logo.php" alt="Logo" style="max-height: 60px;">
    <?php endif; ?>
</div>

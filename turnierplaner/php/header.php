<?php
// Lade Turnier-Konfiguration
$config = json_decode(file_get_contents(__DIR__ . '/../turnier_config.json'), true);
$tournamentName = $config['tournament_name'] ?? 'Volleyball Turnier';
$logoPath = $config['logo_path'] ?? '';

// Prüfe ob Logo existiert
$logoExists = !empty($logoPath) && file_exists(__DIR__ . '/../' . $logoPath);
?>
<style>
    /* Mobile Optimierung für Header */
    @media (max-width: 768px) {
        .header-container {
            flex-direction: column !important;
            align-items: flex-start !important;
        }
        
        .header-container h2 {
            font-size: 1.2rem !important;
            margin-bottom: 10px !important;
        }
        
        .header-container img {
            max-height: 50px !important;
        }
    }
    
    @media (max-width: 576px) {
        .header-container h2 {
            font-size: 1rem !important;
        }
        
        .header-container img {
            max-height: 40px !important;
        }
    }
</style>
<div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom header-container">
    <h2 class="mb-0">🏐 <?= htmlspecialchars($tournamentName) ?></h2>
    <?php if ($logoExists): ?>
        <img src="logo.php" alt="Logo" style="max-height: 60px;">
    <?php endif; ?>
</div>

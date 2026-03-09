<?php
session_start();

// Prüfe Authentifizierung
require 'db.php';
require_once 'helpFunctions.php';

$configPath = __DIR__ . '/../data/turnier_config.json';
$config = json_decode(file_get_contents($configPath), true);
$requiredPassword = $config['result_entry_password'] ?? 'admin';
$setsPerMatch = $config['sets_per_match'] ?? 2;

if (isset($_GET['logout'])) {
    unset($_SESSION['result_entry_authenticated']);
    header('Location: result_entry.php');
    exit;
}

if (!isset($_SESSION['result_entry_authenticated']) || $_SESSION['result_entry_authenticated'] !== true) {
    header('Location: result_entry.php');
    exit;
}

// Hole alle Matches
$matches = $db->query("
    SELECT m.id, m.phase, m.round, m.field_number, m.start_time,
           m.team1_id, m.team2_id, m.team1_ref, m.team2_ref,
           t1.name as team1_name, t2.name as team2_name
    FROM matches m
    LEFT JOIN teams t1 ON m.team1_id = t1.id
    LEFT JOIN teams t2 ON m.team2_id = t2.id
    ORDER BY m.id
")->fetchAll();

// Funktion zum Auflösen von Team-Referenzen
function getTeamDisplay($teamName, $teamRef) {
    if ($teamName) return $teamName;
    if ($teamRef) return $teamRef;
    return "TBD";
}

// Content-Type für PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="spielberichtsbogen.pdf"');

// Einfaches PDF erstellen ohne externe Bibliothek - verwende HTML2PDF Ansatz
// Da TCPDF nicht immer verfügbar ist, erstelle ich eine druckbare HTML-Seite
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Spielberichtsbogen</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.2;
        }
        
        .page {
            page-break-after: always;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            align-content: start;
        }
        
        .page:last-child {
            page-break-after: avoid;
        }
        
        .page-header {
            grid-column: 1 / -1;
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 2px solid #000;
        }
        
        .page-footer {
            grid-column: 1 / -1;
            margin-top: 8px;
            padding: 6px;
            border: 1px solid #666;
            background: #f9f9f9;
            font-size: 7pt;
            text-align: center;
        }
        
        .match-card {
            border: 2px solid #000;
            padding: 5px;
            background: white;
            page-break-inside: avoid;
        }
        
        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #333;
            padding-bottom: 3px;
            margin-bottom: 4px;
            font-weight: bold;
            font-size: 9pt;
        }
        
        .match-id {
            font-size: 11pt;
            font-weight: bold;
        }
        
        .match-info {
            text-align: right;
            font-size: 7pt;
        }
        
        .teams {
            margin: 4px 0;
        }
        
        .team-row {
            display: flex;
            align-items: center;
            margin: 2px 0;
            padding: 3px;
            border: 1px solid #ccc;
            background: #f9f9f9;
        }
        
        .team-label {
            font-weight: bold;
            width: 45px;
            font-size: 8pt;
        }
        
        .team-name {
            flex: 1;
            font-size: 9pt;
            font-weight: bold;
        }
        
        .result-grid {
            display: grid;
            gap: 3px;
            margin: 4px 0;
            align-items: center;
        }
        
        .result-grid.sets-1 { grid-template-columns: auto 1fr; }
        .result-grid.sets-2 { grid-template-columns: auto 1fr 1fr; }
        .result-grid.sets-3 { grid-template-columns: auto 1fr 1fr 1fr; }
        .result-grid.sets-4 { grid-template-columns: auto 1fr 1fr 1fr 1fr; }
        .result-grid.sets-5 { grid-template-columns: auto 1fr 1fr 1fr 1fr 1fr; }
        
        .result-header {
            font-weight: bold;
            text-align: center;
            padding: 2px;
            background: #e0e0e0;
            border: 1px solid #666;
            font-size: 7pt;
        }
        
        .result-label {
            font-weight: bold;
            padding: 4px 3px;
            font-size: 8pt;
        }
        
        .result-box {
            border: 2px solid #000;
            height: 24px;
            background: white;
        }
        
        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px;
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px solid #ccc;
        }
        
        .signature-box {
            display: flex;
            flex-direction: column;
        }
        
        .signature-label {
            font-size: 6pt;
            color: #666;
            margin-bottom: 1px;
        }
        
        .signature-line {
            border-bottom: 1px solid #333;
            height: 16px;
        }
        
        .no-print.print-info {
            text-align: center;
            margin: 20px 0;
            font-size: 12pt;
            font-weight: bold;
        }
        .no-print.print-info {
            right: auto;
            left: 20px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
        
        @media screen {
            body {
                background: #f0f0f0;
                padding: 20px;
            }
            
            .page {
                background: white;
                max-width: 210mm;
                min-height: 297mm;
                margin: 0 auto 20px;
                padding: 10mm;
                box-shadow: 0 0 10px rgba(0,0,0,0.2);
            }
            
            .no-print {
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                padding: 15px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                z-index: 1000;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" style="padding: 10px 20px; font-size: 14pt; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px; margin-bottom: 10px; width: 100%;">
        🖨️ Drucken / PDF speichern
    </button>
    <button onclick="window.location.href='result_entry.php'" style="padding: 10px 20px; font-size: 11pt; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 5px; width: 100%;">
        ← Zurück
    </button>
    <div style="margin-top: 10px; font-size: 10pt; color: #666;">
        <strong>Tipp:</strong> Strg+P (Windows) oder Cmd+P (Mac)<br>
        Im Druckdialog "Als PDF speichern" wählen
    </div>
</div>

<div class="print-info no-print" style="margin-bottom: 30px; border: 2px solid #007bff; padding: 15px; background: #e7f3ff;">
    ⚠️ <strong>Vorschau - Spielberichtsbogen</strong><br>
    <span style="font-size: 9pt;">
        Diese Bögen können ausgedruckt und den Schiedsrichtern/Teams zur Ergebniseintragung übergeben werden.<br>
        • 8 Matches pro Seite<br>
        • Nach dem Spiel ausfüllen und beim Spielleiter abgeben<br>
        • Bitte Drucken-Button klicken oder Strg+P / Cmd+P drücken
    </span>
</div>

<?php
$matchesPerPage = 8;
$pageCount = ceil(count($matches) / $matchesPerPage);

for ($page = 0; $page < $pageCount; $page++) {
    $startIdx = $page * $matchesPerPage;
    $endIdx = min($startIdx + $matchesPerPage, count($matches));
    $pageMatches = array_slice($matches, $startIdx, $matchesPerPage);
    
    echo '<div class="page">';
    echo '<div class="page-header">';
    echo '<h1 style="font-size: 14pt; margin: 0 0 3px 0;">🏐 Volleyball Turnier - Spielberichtsbogen</h1>';
    echo '<div style="font-size: 9pt; color: #666;">Seite ' . ($page + 1) . ' von ' . $pageCount . '</div>';
    echo '</div>';
    
    foreach ($pageMatches as $m) {
        $team1 = resolveTeamToName($db, $m['team1_id'], $m['team1_ref']);
        $team2 = resolveTeamToName($db, $m['team2_id'], $m['team2_ref']);

        $round = $m['phase'] === 'group' ? 'Vorrunde' : $m['round'];
        $time = $m['start_time'] ? date('H:i', strtotime($m['start_time'])) : '-';
        $field = isset($m['field_number']) && $m['field_number'] ? 'Feld ' . $m['field_number'] : '';
        ?>
        <div class="match-card">
            <div class="match-header">
                <span class="match-id">Spiel #<?= $m['id'] ?></span>
                <span><?= htmlspecialchars($round) ?></span>
                <span class="match-info"><?= $time ?> <?= $field ?></span>
            </div>
            
            <div class="teams">
                <div class="team-row">
                    <span class="team-label">Team 1:</span>
                    <span class="team-name"><?= htmlspecialchars($team1) ?></span>
                </div>
                <div class="team-row">
                    <span class="team-label">Team 2:</span>
                    <span class="team-name"><?= htmlspecialchars($team2) ?></span>
                </div>
            </div>
            
            <div class="result-grid sets-<?= $setsPerMatch ?>">
                <div></div>
                <?php for ($s = 1; $s <= $setsPerMatch; $s++) { ?>
                <div class="result-header">Satz <?= $s ?></div>
                <?php } ?>
                
                <div class="result-label">Team 1</div>
                <?php for ($s = 1; $s <= $setsPerMatch; $s++) { ?>
                <div class="result-box"></div>
                <?php } ?>
                
                <div class="result-label">Team 2</div>
                <?php for ($s = 1; $s <= $setsPerMatch; $s++) { ?>
                <div class="result-box"></div>
                <?php } ?>
            </div>
            
            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-label">Schiedsrichter/in:</div>
                    <div class="signature-line"></div>
                </div>
                <div class="signature-box">
                    <div class="signature-label">Datum/Uhrzeit:</div>
                    <div class="signature-line"></div>
                </div>
            </div>
        </div>
        
        <?php
    }
    
    // Hinweis am Ende jeder Seite
    echo '<div class="page-footer">';
    echo '<strong>Hinweis:</strong> Bitte nach dem Spiel ausgefüllt beim Spielleiter abgeben.';
    echo '</div>';
    
    echo '</div>';
}
?>
</body>
</html>

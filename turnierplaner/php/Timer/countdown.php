<?php
/**
 * Countdown Timer - PHP Version
 * Liest Sound-Konfiguration aus config.json
 * Start- und Alarmzeiten sind am oberen Rand einstellbar
 */

// Konfigurationsdatei einlesen
$configFile = 'config.json';
$soundConfig = [];
$defaultStart = 600;
$log = '';

if (file_exists($configFile)) {
    $jsonContent = file_get_contents($configFile);
    $config = json_decode($jsonContent, true);
    
    if ($config && json_last_error() === JSON_ERROR_NONE) {
        // Lese Startzeit
        if (isset($config['start'])) {
            $defaultStart = parseTimeValue($config['start']);
        }
        
        // Lese Alerts
        if (isset($config['alerts']) && is_array($config['alerts'])) {
            foreach ($config['alerts'] as $alert) {
                if (isset($alert['alertTime']) && isset($alert['sounds']) && isset($alert['id'])) {
                    $soundConfig[] = [
                        'alertTime' => parseTimeValue($alert['alertTime']),
                        'sounds' => $alert['sounds'],
                        'id' => $alert['id']
                    ];
                }
            }
        }
    }
}

// Hinweis: Der garantierte Alarm wird dynamisch in JavaScript hinzugefügt (bei startSeconds - 1)

function parseTimeValue($value) {
    $value = trim($value);
    if (strpos($value, ':') !== false) {
        $parts = explode(':', $value);
        return intval($parts[0]) * 60 + intval($parts[1]);
    }
    return intval($value);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Countdown Timer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #000;
            color: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .controls {
            background-color: #333;
            padding: 15px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }
        
        .time-inputs {
            display: flex;
            gap: 15px;
            align-items: center;
            flex: 1;
            flex-wrap: wrap;
        }
        
        .input-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .input-group label {
            font-size: 12px;
            color: #ccc;
            white-space: nowrap;
        }
        
        .input-group input {
            width: 80px;
            padding: 8px;
            border: 1px solid #555;
            background-color: #222;
            color: #fff;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .buttons {
            display: flex;
            gap: 10px;
        }
        
        button {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .btn-start {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-start:hover {
            background-color: #45a049;
        }
        
        .btn-pause {
            background-color: #FF9800;
            color: white;
        }
        
        .btn-pause:hover {
            background-color: #e68900;
        }
        
        .btn-reset {
            background-color: #2196F3;
            color: white;
        }
        
        .btn-reset:hover {
            background-color: #0b7dda;
        }
        
        .countdown-display {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50vmin;
            font-weight: bold;
            letter-spacing: 0.02em;
            transition: color 0.5s;
        }
        
        .countdown-display.finished {
            color: #FF4D4D;
        }
        
        .status-indicator {
            position: fixed;
            top: 70px;
            right: 20px;
            padding: 8px 16px;
            background-color: rgba(0,0,0,0.7);
            border-radius: 4px;
            font-size: 14px;
            display: none;
        }
        
        .status-indicator.paused {
            display: block;
            color: #FF9800;
        }
        
        .alert-info {
            background-color: #1a1a1a;
            padding: 10px 20px;
            text-align: center;
            font-size: 13px;
            color: #4CAF50;
            border-bottom: 1px solid #333;
            min-height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .alert-info.visible {
            opacity: 1;
        }
        
        .alert-info .filename {
            font-weight: bold;
            color: #fff;
        }
        
        .alert-info .params {
            color: #aaa;
            margin-left: 10px;
        }
        
        /* Match Info Section */
        .match-info-section {
            background-color: #1a1a1a;
            padding: 15px;
            flex: 1;
            border-top: 2px solid #333;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            container-type: size;
        }
        
        .match-info-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            height: 100%;
            width: 100%;
        }
        
        .match-card {
            background-color: #2a2a2a;
            border-radius: 6px;
            padding: 15px;
            border: 2px solid #444;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            display: flex;
            justify-content: center;
            gap: 10px;
            min-height: 0;
        }
        
        .match-card.current {
            border-color: #4CAF50;
            background-color: #2d3a2d;
        }
        
        .match-card.next {
            border-color: #FF9800;
            background-color: #3a2f2d;
            opacity: 0.85;
        }
        
        .match-left {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        
        .match-right {
            display: flex;
            flex-direction: column;
            justify-content: center;
            flex: 1;
        }
        
        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            gap: 8px;
        }
        
        .match-field {
            font-size: clamp(12px, 8cqh, 32px);
            font-weight: bold;
            color: #4CAF50;
        }
        
        .match-time {
            font-size: clamp(12px, 8cqh, 32px);
            color: #FF9800;
        }
        
        .match-referee {
            font-size: clamp(14px, 6cqh, 32px);
            color: #aaa;
            text-align: center;
        }
        
        .match-teams {
            font-size: clamp(15px, 10cqh, 64px);
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
        }
        
        .team-name {
            color: #fff;
            font-weight: bold;
        }
        
        .vs-separator {
            color: #888;
            font-size: clamp(18px, 6cqh, 36px);
        }
        
        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .time-inputs {
                flex-direction: column;
                align-items: stretch;
            }
            
            .buttons {
                width: 100%;
            }
            
            button {
                flex: 1;
            }
            
            .countdown-display {
                font-size: 15vmin;
            }
            
            .match-info-container {
                grid-template-columns: 1fr;
            }
            
            .match-card {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="controls">
        <div class="time-inputs">
            <div class="input-group">
                <label for="startTime">Startzeit (MM:SS):</label>
                <input type="text" id="startTime" value="<?php echo sprintf('%02d:%02d', floor($defaultStart/60), $defaultStart%60); ?>" pattern="[0-9]{1,2}:[0-9]{2}">
            </div>
            <?php
            // Zeige Alarm-Zeitfelder basierend auf Config
            $alertCount = 0;
            foreach ($soundConfig as $idx => $alert) {
                $alertCount++;
                $alertMinutes = floor($alert['alertTime'] / 60);
                $alertSeconds = $alert['alertTime'] % 60;
                $alertID = $alert['id'];
                echo "<div class=\"input-group\">\n";
                echo "    <label for=\"alert{$alertCount}\">Alarm ID {$alertID} (MM:SS):</label>\n";
                echo "    <input type=\"text\" id=\"alert{$alertCount}\" value=\"" . sprintf('%02d:%02d', $alertMinutes, $alertSeconds) . "\" pattern=\"[0-9]{1,2}:[0-9]{2}\">\n";
                echo "</div>\n";
            }
            ?>
        </div>
        <div class="checkbox-group">
            <label>
                <input type="checkbox" id="myCheckbox" onchange="handleCheckboxChange(this)">
            </label>
        </div>
        <div class="buttons">
            <button class="btn-start" id="startBtn" onclick="startTimer()">▶️ Start</button>
            <button class="btn-pause" id="pauseBtn" onclick="pauseTimer()" disabled>⏸️ Pause</button>
            <button class="btn-reset" onclick="resetTimer()">🔄 Reset</button>
        </div>
    </div>
    
    <div class="alert-info" id="alertInfo"></div>
    
    <div class="status-indicator" id="statusIndicator">⏸️ Pausiert</div>
    
    <div class="countdown-display" id="countdownDisplay">00:00</div>
    
    <div class="match-info-section">
        <div class="match-info-container" id="matchInfoContainer">
            <!-- Match-Karten werden hier dynamisch eingefügt -->
        </div>
    </div>
    
    <script>
        // Sound-Konfiguration aus PHP
        const soundConfig = <?php echo json_encode($soundConfig); ?>;
        
        let startSeconds = <?php echo $defaultStart; ?>;
        let currentTime = startSeconds;
        let isRunning = false;
        let isPaused = false;
        let timerInterval = null;
        let triggeredAlerts = new Set();
        let currentAudio = null;
        let fadeInterval = null;
        
        // Garantierter Alarm-Sound (wird dynamisch hinzugefügt)
        const guaranteedAlarmSound = {
            file: 'sounds/alarm-2.mp3',
            offset: 0,
            fade: 0
        };
        
        // UI Elemente
        const display = document.getElementById('countdownDisplay');
        const startBtn = document.getElementById('startBtn');
        const pauseBtn = document.getElementById('pauseBtn');
        const statusIndicator = document.getElementById('statusIndicator');
        const startTimeInput = document.getElementById('startTime');
        const timeInputs = document.querySelector('.time-inputs');
        //timeInputs.style.display = 'none';  // makes time inputs invisible
        const alertInfo = document.getElementById('alertInfo');
        alertInfo.style.display = 'none'; // Alert-Info set zu inaktiv

        // Initialisiere Anzeige
        updateDisplay();
        
        function handleCheckboxChange(checkbox) {
            if (checkbox.checked) {
                const timeInputs = document.querySelector('.time-inputs');
                timeInputs.style.display = 'none';  // makes time inputs invisible            
            } else {
                const timeInputs = document.querySelector('.time-inputs');
                timeInputs.style.display = 'flex';  // makes time inputs visible            
            }
        }
        function parseTimeInput(value) {
            const match = value.match(/^(\d{1,2}):(\d{2})$/);
            if (match) {
                return parseInt(match[1]) * 60 + parseInt(match[2]);
            }
            return null;
        }
        
        function formatTime(seconds) {
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            
            if (h > 0) {
                return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
            }
            return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        }
        
        function updateDisplay() {
            display.textContent = formatTime(currentTime);
            if (currentTime === 0) {
                display.classList.add('finished');
            } else {
                display.classList.remove('finished');
            }
        }
        
        function startTimer() {
            // Stoppe erst alles komplett
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            
            // Lese aktuelle Startzeit aus Input und starte Timer damit
            const newStartTime = parseTimeInput(startTimeInput.value);
            if (newStartTime !== null) {
                startSeconds = newStartTime;
                currentTime = startSeconds;
                triggeredAlerts.clear(); // Setze Alerts zurück bei neuem Start
                updateDisplay();
            }
                        
            // Verhindere Start wenn Zeit 0 ist
            if (currentTime <= 0) {
                return;
            }
            
            // Aktualisiere Alarm-Zeiten (inkl. garantiertem Alarm)
            updateAlertTimes();
            //addGuaranteedAlarm();
            
            isRunning = true;
            isPaused = false;
            startBtn.disabled = true;
            pauseBtn.disabled = false;
            pauseBtn.textContent = '⏸️ Pause';
            statusIndicator.classList.remove('paused');
            
            timerInterval = setInterval(() => {
                if (currentTime > 0) {
                    currentTime--;
                    updateDisplay();
                    checkAlerts();
                } else {
                    stopTimer();
                }
            }, 1000);
        }
        
        function pauseTimer() {
            if (!isRunning) return;
            
            if (isPaused) {
                // Fortsetzen
                isPaused = false;
                pauseBtn.textContent = '⏸️ Pause';
                statusIndicator.classList.remove('paused');
                
                // Audio fortsetzen
                resumeAudio();
                
                timerInterval = setInterval(() => {
                    if (currentTime > 0) {
                        currentTime--;
                        updateDisplay();
                        checkAlerts();
                    } else {
                        stopTimer();
                    }
                }, 1000);
            } else {
                // Pausieren
                isPaused = true;
                pauseBtn.textContent = '▶️ Fortsetzen';
                statusIndicator.classList.add('paused');
                
                if (timerInterval) {
                    clearInterval(timerInterval);
                    timerInterval = null;
                }
                
                // Audio pausieren
                pauseAudio();
            }
        }
        
        function stopTimer() {
            isRunning = false;
            isPaused = false;
            
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            
            startBtn.disabled = false; // Button aktivieren nach Timer-Ende
            pauseBtn.disabled = true;
            pauseBtn.textContent = '⏸️ Pause';
            statusIndicator.classList.remove('paused');
            
            stopAllSounds();
        }
        
        function resetTimer() {
            // Stoppe alles komplett
            stopAllSounds();
            
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
            
            // Setze alle States zurück
            isRunning = false;
            isPaused = false;
            
            // Lese neue Startzeit
            const newStartTime = parseTimeInput(startTimeInput.value);
            if (newStartTime !== null) {
                startSeconds = newStartTime;
            }
            
            currentTime = startSeconds;
            triggeredAlerts = new Set(); // Erstelle neues Set
            
            // UI zurücksetzen
            startBtn.disabled = false;
            pauseBtn.disabled = true;
            pauseBtn.textContent = '⏸️ Pause';
            statusIndicator.classList.remove('paused');
            alertInfo.classList.remove('visible');
            alertInfo.innerHTML = '';
            
            updateDisplay();
        }
        
        function updateAlertTimes() {
            // Lese alle Alarm-Zeit-Inputs und aktualisiere soundConfig
            soundConfig.forEach((alert, idx) => {
                const input = document.getElementById(`alert${idx + 1}`);
                if (input) {
                    const newTime = parseTimeInput(input.value);
                    if (newTime !== null) {
                        alert.alertTime = newTime;
                    }
                }
            });
        }
        
        function addGuaranteedAlarm() {
            // Entferne alte garantierte Alarme (falls vorhanden)
            for (let i = soundConfig.length - 1; i >= 0; i--) {
                if (soundConfig[i].isGuaranteed) {
                    soundConfig.splice(i, 1);
                }
            }
            
            // Füge garantierten Alarm bei startSeconds - 1 hinzu
            const guaranteedAlertTime = startSeconds - 1;
            soundConfig.push({
                alertTime: guaranteedAlertTime,
                sounds: [guaranteedAlarmSound],
                isGuaranteed: true  // Markierung für späteres Entfernen
            });
            
        }
        
        function checkAlerts() {
            soundConfig.forEach((alert, idx) => {
                const alertKey = `${alert.alertTime}-${idx}`;
                if (currentTime === alert.alertTime && !triggeredAlerts.has(alertKey)) {
                    triggeredAlerts.add(alertKey);
                    
                    // Wähle zufälligen Sound aus der Liste
                    if (alert.sounds.length > 0) {
                        const randomSound = alert.sounds[Math.floor(Math.random() * alert.sounds.length)];
                        playSound(randomSound);
                        
                        // Zeige Alert-Info an
                        const fileName = randomSound.file.split('/').pop();
                        alertInfo.innerHTML = `<span> Alert ${idx + 1} bei ${formatTime(alert.alertTime)}: </span><span class="filename">${fileName}</span><span class="params"> (Offset: ${randomSound.offset}s, Fade: ${randomSound.fade}s)</span>`;
                        alertInfo.classList.add('visible');
                        
                        // Info nach 10 Sekunden ausblenden
                        setTimeout(() => {
                            alertInfo.classList.remove('visible');
                        }, 10000);
                    }
                }
            });
        }
        
        // Web Audio API Context (wird einmal erstellt)
        let audioContext = null;
        let currentAudioSource = null;
        let currentGainNode = null;
        let currentAudioBuffer = null;
        let audioStartTime = 0;
        let audioPausedAt = 0;
        let currentSoundConfig = null;
        let isAudioPlaying = false;
        
        function playSound(soundConfig) {
            stopAllSounds();
            
            if (!soundConfig.file) return;
            
            // Erstelle AudioContext falls noch nicht vorhanden
            if (!audioContext) {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
            
            // Speichere Config für Resume
            currentSoundConfig = soundConfig;
            
            // Lade Audio-Datei als ArrayBuffer
            fetch(soundConfig.file)
                .then(response => response.arrayBuffer())
                .then(arrayBuffer => audioContext.decodeAudioData(arrayBuffer))
                .then(audioBuffer => {
                    currentAudioBuffer = audioBuffer;
                    startAudioPlayback(soundConfig.offset || 0, soundConfig.fade || 0);
                })
                .catch(err => {
                    // Fehler beim Laden/Dekodieren
                });
        }
        
        function startAudioPlayback(offset, fade) {
            // Erstelle Audio Source
            currentAudioSource = audioContext.createBufferSource();
            currentAudioSource.buffer = currentAudioBuffer;
            
            // Erstelle Gain Node für Lautstärkekontrolle
            currentGainNode = audioContext.createGain();
            currentGainNode.gain.value = fade > 0 ? 0 : 1;
            
            // Verbinde: Source -> Gain -> Destination
            currentAudioSource.connect(currentGainNode);
            currentGainNode.connect(audioContext.destination);
            
            // Starte Wiedergabe mit Offset
            audioStartTime = audioContext.currentTime - offset;
            currentAudioSource.start(0, offset);
            isAudioPlaying = true;
            
            // Fade-In
            if (fade > 0) {
                const fadeEndTime = audioContext.currentTime + fade;
                currentGainNode.gain.linearRampToValueAtTime(1, fadeEndTime);
            }
        }
        
        function pauseAudio() {
            if (isAudioPlaying && currentAudioSource) {
                // Speichere aktuelle Position
                audioPausedAt = audioContext.currentTime - audioStartTime;
                
                // Stoppe Source
                try {
                    currentAudioSource.stop();
                } catch (e) {
                    // Ignore
                }
                currentAudioSource.disconnect();
                currentAudioSource = null;
                
                if (currentGainNode) {
                    currentGainNode.disconnect();
                    currentGainNode = null;
                }
                
                isAudioPlaying = false;
            }
        }
        
        function resumeAudio() {
            if (!isAudioPlaying && currentAudioBuffer && audioPausedAt > 0) {
                const fade = currentSoundConfig ? currentSoundConfig.fade || 0 : 0;
                startAudioPlayback(audioPausedAt, fade);
            }
        }
        
        function stopAllSounds() {
            if (fadeInterval) {
                clearInterval(fadeInterval);
                fadeInterval = null;
            }
            
            // Stoppe Web Audio API Source
            if (currentAudioSource) {
                try {
                    currentAudioSource.stop();
                } catch (e) {
                    // Ignore - bereits gestoppt
                }
                currentAudioSource.disconnect();
                currentAudioSource = null;
            }
            
            if (currentGainNode) {
                currentGainNode.disconnect();
                currentGainNode = null;
            }
            
            // Reset Audio States
            isAudioPlaying = false;
            audioPausedAt = 0;
            currentAudioBuffer = null;
            currentSoundConfig = null;
            
            // Stoppe auch altes HTML5 Audio falls vorhanden
            if (currentAudio) {
                currentAudio.pause();
                currentAudio.currentTime = 0;
                currentAudio = null;
            }
        }
        
        // Remote Control via Polling
        let lastCommandTimestamp = null;
        
        async function checkRemoteCommands() {
            try {
                const response = await fetch('timer_control.php');
                const data = await response.json();
                // Prüfe ob neuer Befehl vorhanden
                if (data.command && data.timestamp && data.timestamp !== lastCommandTimestamp) {
                    lastCommandTimestamp = data.timestamp;
                    
                    // Führe Befehl aus
                    switch(data.command) {
                        case 'start':
                            if (!isRunning || isPaused) {
                                // Setze Startzeit wenn remote übermittelt
                                if (data.startTime) {
                                    startTimeInput.value = data.startTime;
                                }
                                startTimer();
                            }
                            break;
                        case 'pause':
                            if (isRunning) {
                                pauseTimer();
                            }
                            break;
                        case 'reset':
                            resetTimer();
                            break;
                    }
                    
                    // Lösche Befehl nach Ausführung
                    await fetch('timer_control.php', { method: 'DELETE' });
                }
            } catch (error) {
                console.error('Remote command check failed:', error);
            }
        }
        
        // Starte Polling alle 2 Sekunden
        setInterval(checkRemoteCommands, 2000);
        
        // Match Info Management
        function updateMatchInfo(matches) {
            const container = document.getElementById('matchInfoContainer');
            container.innerHTML = '';
            
            if (!matches || matches.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: #666; padding: 20px;">Keine bevorstehenden Spiele</div>';
                return;
            }
            
            matches.forEach(match => {
                const card = document.createElement('div');
                card.className = 'match-card';
                
                // Markiere aktuelle und nächste Spiele
                if (match.status === 'current') {
                    card.classList.add('current');
                } else if (match.status === 'next') {
                    card.classList.add('next');
                }
                card.innerHTML = `
                    <div class="match-left">
                        <div class="match-field">Feld ${match.field} -> </div>
                        <div class="match-time">  ${match.time}</div>
                    </div>
                    <div class="match-right">
                        <div class="match-teams">
                            <span class="team-name">${match.team1}</span>
                            <span class="vs-separator">vs</span>
                            <span class="team-name">${match.team2}</span>
                        </div>
                        <div class="match-referee">Schiri:️ ${match.referee || '—'}</div>
                    </div>
                `;

                container.appendChild(card);
            });
        }
        
        // Lade Match-Daten aus der Datenbank
        async function loadUpcomingMatches() {
            try {
                const response = await fetch('get_upcoming_matches.php');
                const matches = await response.json();
                
                if (matches.error) {
                    console.error('Fehler beim Laden der Matches:', matches.error);
                    return;
                }
                
                updateMatchInfo(matches);
            } catch (error) {
                console.error('Fehler beim Laden der Matches:', error);
            }
        }
        
        // Initialer Load
        loadUpcomingMatches();
        
        // Aktualisiere alle 15 Sekunden
    //    setInterval(loadUpcomingMatches, 15000);
        
        // Tastenkürzel
        document.addEventListener('keydown', (e) => {
            if (e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                if (isRunning) {
                    pauseTimer();
                } else if (currentTime > 0) {
                    startTimer();
                }
            } else if (e.key === 'Escape') {
                resetTimer();
            }
        });
        
        // Verhindere Submit bei Enter in Inputs
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.blur();
                }
            });
        });
    </script>
</body>
</html>

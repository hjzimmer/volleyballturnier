<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Countdown</title>

<style>
#debug {
  position: fixed;
  top: 10px;
  right: 10px;
  font: 12px monospace;
  background: rgba(0,0,0,0.80);
  color: #0f0;
  padding: 10px;
  border-radius: 6px;
  z-index: 9999;
  display: none;
  white-space: pre;
  transition: background 0.2s;
}

#debug.flash {
  background: rgba(0,120,0,0.9);
}
</style>

</head>
<body>

<h1 id="display">–</h1>
<div id="debug"></div>

<script>
/* =========================
   DEBUG OVERLAY
   ========================= */

const debugEnabled = location.search.includes("debug=1");
const debugEl = document.getElementById("debug");


const debugState = {
  mode: "–",
  status: "–",
  reconnects: 0,
  lastUpdate: null,
  mobileFallback: false,
  updatesReceived: 0,
  flash: false
};

function renderDebug() {
  if (!debugEnabled) return;

  const age = debugState.lastUpdate
    ? Math.floor((Date.now() - debugState.lastUpdate) / 1000) + "s ago"
    : "–";

  debugEl.textContent =
`Mode: ${debugState.mode}
Status: ${debugState.status}
Updates empfangen: ${debugState.updatesReceived}${debugState.flash ? "  ✅ NEW" : ""}
Last update: ${age}
Reconnects: ${debugState.reconnects}
Mobile fallback: ${debugState.mobileFallback ? "YES" : "NO"}`;

  debugEl.style.display = "block";

  if (debugState.flash) {
    debugEl.classList.add("flash");
    setTimeout(() => {
      debugState.flash = false;
      debugEl.classList.remove("flash");
      renderDebug();
    }, 300);
  }
}


/* =========================
   COUNTDOWN LOGIK
   ========================= */

const display = document.getElementById("display");
let lastUpdated = 0;
let lastState = null;

function render(data) {
  lastState = data;
  tick();
}

function tick() {
  if (!lastState) return;

  if (!lastState.running) {
    display.textContent = "STOP";
    return;
  }

  const elapsed = Math.floor(Date.now()/1000) - lastState.updated;
  const remaining = Math.max(0, lastState.remaining - elapsed);
  display.textContent = remaining + "s";

  debugState.lastUpdate = Date.now();
  renderDebug();
}

// Sekündlich die Anzeige aktualisieren (flüssiger Countdown)
setInterval(tick, 1000);

/* =========================
   SSE (Desktop)
   ========================= */

function startSSE() {
  debugState.mode = "SSE";
  debugState.status = "connecting";
  debugState.mobileFallback = false;
  renderDebug();

  const evt = new EventSource("timer_control.php");


    evt.addEventListener("update", e => {
        const data = JSON.parse(e.data);

        debugState.updatesReceived++;
        debugState.flash = true;
        debugState.status = "connected";
        debugState.lastUpdate = Date.now();

        lastUpdated = data.updated;
        render(data);
        });


  evt.onerror = () => {
    evt.close();
    debugState.reconnects++;
    debugState.status = "reconnecting";
    renderDebug();

    setTimeout(startSSE, 2000);
  };
}

/* =========================
   POLLING FALLBACK (Mobile)
   ========================= */

function startPolling() {
  debugState.mode = "Polling";
  debugState.status = "active";
  debugState.mobileFallback = true;
  renderDebug();

  setInterval(() => {
    fetch("timer_status.json")
      .then(r => r.json())
      .then(render);
  }, 3000);
}

/* =========================
   FEATURE DETECTION
   ========================= */

const isMobile = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent);

if ("EventSource" in window && !isMobile) {
  startSSE();
} else {
  startPolling();
}
</script>

</body>
</html>

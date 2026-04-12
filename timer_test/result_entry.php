<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Timer Steuerung</title>
</head>
<body>

<h2>Timer starten</h2>
<input type="number" id="seconds" value="30">
<button onclick="start()">Start</button>
<button onclick="stop()">Stop</button>

<script>
function send(data) {
    fetch("Timer/timer_control.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data)
    });
}

function start() {
    send({
        remaining: parseInt(seconds.value),
        running: true
    });
}

function stop() {
    send({
        remaining: 0,
        running: false
    });
}
</script>

</body>
</html>


@echo off
setlocal

where php >nul 2>nul
if errorlevel 1 (
    echo PHP wurde nicht im PATH gefunden.
    echo Fuehre zuerst env.bat aus oder installiere PHP lokal.
    pause
    exit /b 1
)

pushd "%~dp0"
echo Starte lokalen Timer-Control-Server auf http://127.0.0.1:8081/timer_control.php
echo Beenden mit Strg+C
php -S 127.0.0.1:8081
popd

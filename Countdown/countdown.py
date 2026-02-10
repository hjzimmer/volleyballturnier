
import tkinter as tk
from tkinter import font as tkfont
import configparser
import os
import sys
import time
import threading
import platform
import warnings
import random
from screeninfo import get_monitors

# Unterdrücke AVX2-Warnung von pygame
warnings.filterwarnings("ignore", message=".*avx2.*")

try:
    import pygame
    HAS_PYGAME = True
except ImportError:
    HAS_PYGAME = False

CONFIG_PATH = "config.ini"
stop_fading = False  # Flag zum Abbrechen von Fade-Operationen

def parse_start_value(value: str) -> int:
    """
    Akzeptiert Sekunden (z.B. '300') oder MM:SS (z.B. '05:00') und gibt Sekunden zurück.
    """
    value = value.strip()
    if ":" in value:
        parts = value.split(":")
        if len(parts) != 2:
            raise ValueError("Ungültiges Zeitformat. Erwarte MM:SS, z.B. 05:00")
        mm, ss = parts
        return int(mm) * 60 + int(ss)
    return int(value)

def read_config(config_path: str) -> dict:
    """
    Liest die gesamte Timer-Konfiguration aus einer INI-Datei.
    Gibt ein Dictionary mit start_seconds, alerts (Liste von Tupeln) zurück.
    Alerts enthalten: (alert_time, [(sound_file, start_offset), ...])
    """
    cfg = configparser.ConfigParser()
    if not os.path.exists(config_path):
        # Beispiel-Konfig anlegen
        cfg["timer"] = {
            "start": "05:00",
            "alert_at_1": "10",
            "sound_file_1_1": "alert1.mp3:0",
            "sound_file_1_2": "alert1b.mp3:2",
            "ende_alert": "5",
            "ende_sound": "alert2.mp3:0"
        }
        with open(config_path, "w", encoding="utf-8") as f:
            cfg.write(f)
        print(f"Beispiel-Konfiguration erstellt: {config_path}")

    cfg.read(config_path, encoding="utf-8")
    if "timer" not in cfg or "start" not in cfg["timer"]:
        raise KeyError(f"In {config_path} muss [timer] mit Schlüssel 'start' vorhanden sein.")
    
    start_seconds = parse_start_value(cfg["timer"]["start"])
    
    # Sammle alle Alerts mit ihren Sound-Listen
    alerts = []
    i = 1
    while f"alert_at_{i}" in cfg["timer"]:
        alert_time = parse_start_value(cfg["timer"][f"alert_at_{i}"])
        
        # Sammle alle Sounds für diesen Alert
        # Suche alle Schlüssel, die mit "sound_file_{i}_" anfangen
        sounds = []
        sound_keys = [key for key in cfg["timer"].keys() if key.startswith(f"sound_file_{i}_")]
        # Sortiere die Schlüssel numerisch nach der letzten Nummer
        sound_keys.sort(key=lambda x: int(x.split("_")[-1]))
        
        for sound_key in sound_keys:
            sound_entry = cfg["timer"][sound_key]
            # Parse Format: "dateiname:start_offset:fade_sec" (fade optional in Sekunden, default 0)
            parts = sound_entry.split(":")
            sound_file = parts[0].strip()
            offset = 0
            fade = 0
            
            try:
                if len(parts) >= 2:
                    offset = float(parts[1])
                if len(parts) >= 3:
                    fade = int(float(parts[2]) * 1000)  # Konvertiere Sekunden zu Millisekunden
            except ValueError:
                pass
            
            sounds.append((sound_file, offset, fade))
        
        if sounds:
            alerts.append((alert_time, sounds))
        i += 1
    
    # Prüfe auch auf ende_alert
    if "ende_alert" in cfg["timer"]:
        alert_time = parse_start_value(cfg["timer"]["ende_alert"])
        end_sound = cfg["timer"].get("ende_sound", "ende.mp3:0")
        # Parse Format: "dateiname:start_offset:fade_sec" (fade optional in Sekunden, default 0)
        parts = end_sound.split(":")
        sound_file = parts[0].strip()
        offset = 0
        fade = 0
        
        try:
            if len(parts) >= 2:
                offset = float(parts[1])
            if len(parts) >= 3:
                fade = int(float(parts[2]) * 1000)  # Konvertiere Sekunden zu Millisekunden
        except ValueError:
            pass
        
        alerts.append((alert_time, [(sound_file, offset, fade)]))
    
    # Debug ausgabe aller Parameter
    def debug_config():
        print("\n" + "="*60)
        print("GELADENE KONFIGURATION:")
        print(f"Startsekunden: {start_seconds}")
        print("Alerts:")
        for alert_time, sounds in alerts:
            print(f"  Alert bei Restzeit {alert_time} Sekunden:")
            for sound_file, offset, fade in sounds:
                fade_sec = fade / 1000.0
                print(f"    Sound: {sound_file}, Offset: {offset}s, Fade: {fade_sec}s")
        print("="*60 + "\n")
    #debug_config()
    
    return {"start_seconds": start_seconds, "alerts": alerts}

def format_hhmmss(total_seconds: int) -> str:
    h = total_seconds // 3600
    m = (total_seconds % 3600) // 60
    s = total_seconds % 60
    if h > 0:
        return f"{h:02d}:{m:02d}:{s:02d}"
    return f"{m:02d}:{s:02d}"

def play_sound_async(sound_file: str, start_offset: float = 0, fade: int = 0):
    """
    Spielt eine Sounddatei asynchron in einem separaten Thread ab.
    start_offset: Startsekunde ab der der Sound abgespielt wird (bei 0 = von Anfang)
    fade: ms to fade in (wird durch manuelle Lautstärkeanpassung realisiert)
    """
    if not HAS_PYGAME:
        print(f"Warnung: pygame nicht installiert. Kann '{sound_file}' nicht abspielen.")
        return
    
    def play():
        try:
            pygame.mixer.init()
            if os.path.exists(sound_file):
                pygame.mixer.music.load(sound_file)
                pygame.mixer.music.set_volume(0)  # Start mit Lautstärke 0
                pygame.mixer.music.play(start=start_offset)
                
                # Fade-In durch progressive Lautstärkeanpassung
                if fade > 0:
                    global stop_fading
                    fade_steps = min(20, max(10, fade // 50))  # Zwischen 10 und maximal 20 Steps
                    step_duration = fade / fade_steps
                    fade_start_time = time.time()
                    for step in range(fade_steps):
                        if stop_fading:
                            pygame.mixer.music.stop()
                            return
                        volume = (step + 1) / fade_steps
                        current_time = time.time()
                        elapsed_ms = int((current_time - fade_start_time) * 1000)
                        elapsed_sec = elapsed_ms // 1000
                        # print(f"Setze Lautstärke auf {volume:.2f} ({elapsed_sec}s:{elapsed_ms % 1000:03d}ms)")
                        pygame.mixer.music.set_volume(volume)
                        time.sleep(step_duration / 1000.0)
                
                pygame.mixer.music.set_volume(1.0)  # Finale Lautstärke 100%
                
                # Warte, bis der Sound zu Ende gespielt ist
                while pygame.mixer.music.get_busy():
                    time.sleep(0.1)
            else:
                print(f"Warnung: Sounddatei nicht gefunden: {sound_file}")
        except Exception as e:
            print(f"Fehler beim Abspielen der Sounddatei: {e}")
    
    thread = threading.Thread(target=play, daemon=True)
    thread.start()

def stop_all_sounds():
    """
    Beendet alle laufenden Audio-Wiedergaben.
    """
    if not HAS_PYGAME:
        return
    
    try:
        pygame.mixer.music.stop()
    except Exception as e:
        print(f"Fehler beim Stoppen der Audio-Wiedergabe: {e}")

class CountdownApp:
    def __init__(self, root: tk.Tk, start_seconds: int, alerts: list = None):
        self.root = root
        self.start_seconds = start_seconds  # Speichere Startwert für Neustart
        self.remaining = max(0, int(start_seconds))
        self.alerts = alerts or []
        self.triggered_alerts = set()  # Speichert bereits ausgelöste Alerts
        self.resize_timer = None  # Timer für Debouncing von Resize-Events
        
        # Button-Frame am oberen Rand
        button_frame = tk.Frame(root, bg="#333333", height=50)
        button_frame.pack(side=tk.TOP, fill=tk.X, padx=5, pady=5)
        
        restart_btn = tk.Button(button_frame, text="🔄 Neustart", command=self.restart, 
                               bg="#4CAF50", fg="white", font=("Arial", 10, "bold"), padx=10)
        restart_btn.pack(side=tk.LEFT, padx=5)
        
        self.pause_btn = tk.Button(button_frame, text="⏸️ Pause", command=self.toggle_pause, 
                                   bg="#FF9800", fg="white", font=("Arial", 10, "bold"), padx=10)
        self.pause_btn.pack(side=tk.LEFT, padx=5)
        
        quit_btn = tk.Button(button_frame, text="✕ Beenden", command=self.root.destroy,
                            bg="#f44336", fg="white", font=("Arial", 10, "bold"), padx=10)
        quit_btn.pack(side=tk.LEFT, padx=5)
        
        # Main Label für Countdown
        self.label = tk.Label(root, text="", fg="#FFFFFF", bg="#000000")
        self.label.pack(expand=True, fill="both")

       
        # Fenstergröße auf 80% des Primär-Bildschirms setzen
        root.update_idletasks()
        root.wm_maxsize(root.winfo_screenwidth(), root.winfo_screenheight())

        screen_w, screen_h, offset_x, offset_y = get_largest_monitor()
        target_w = int(screen_w * 0.8)
        target_h = int(screen_h * 0.8)
        x = int(offset_x + ((screen_w - target_w) * 0.5))
        y = int(offset_y + ((screen_h - target_h) * 0.5))

        root.geometry(f"{target_w}x{target_h}+{x}+{y}")
        root.title("Countdown")

        # Dynamische Schriftgröße
        self.font_family = "Segoe UI" if "win" in sys.platform.lower() else "Arial"
        self.current_font = tkfont.Font(family=self.font_family, size=10, weight="bold")
        self.label.configure(font=self.current_font)

        # Tastenkürzel
        root.bind("<Escape>", lambda e: root.destroy())
        root.bind("<space>", self.toggle_pause)
        root.bind("<F11>", self.toggle_fullscreen)

        self.paused = False
        self.fullscreen = False

        # Bei jeder Größenänderung Schriftgröße anpassen
        root.bind("<Configure>", self._on_resize)

        # Start
        self._update_display()
        self._tick()

    def restart(self):
        """
        Startet den Countdown neu, liest die Config neu ein und setzt alle Alerts zurück.
        """
        global stop_fading
        stop_fading = True  # Bricht laufendes Fading ab
        time.sleep(0.1)  # Kurz warten, bis Fade gestoppt ist
        stop_fading = False
        stop_all_sounds()
        try:
            config = read_config(CONFIG_PATH)
            self.start_seconds = config["start_seconds"]
            self.alerts = config["alerts"]
        except Exception as e:
            print(f"Fehler beim Neu-Laden der Konfiguration: {e}")
        
        self.remaining = self.start_seconds
        self.triggered_alerts = set()
        self.paused = False
        self.label.config(fg="#FFFFFF")  # Farbe zurücksetzen
        self._update_display()
        self._update_title()
        print("Countdown neu gestartet")

    def toggle_pause(self, event=None):
        self.paused = not self.paused
        self._update_title()
        # Update button text and pause/unpause music
        if self.paused:
            self.pause_btn.config(text="▶️ Fortsetzen")
            # Pausiere Musikwiedergabe
            if HAS_PYGAME:
                try:
                    pygame.mixer.music.pause()
                except Exception as e:
                    print(f"Fehler beim Pausieren der Musik: {e}")
        else:
            self.pause_btn.config(text="⏸️ Pause")
            # Setze Musikwiedergabe fort
            if HAS_PYGAME:
                try:
                    pygame.mixer.music.unpause()
                except Exception as e:
                    print(f"Fehler beim Fortsetzen der Musik: {e}")

    def toggle_fullscreen(self, event=None):
        self.fullscreen = not self.fullscreen
        self.root.attributes("-fullscreen", self.fullscreen)

    def _update_title(self):
        status = "⏸️" if self.paused else "▶️"
        self.root.title(f"Countdown {status}")

    def _on_resize(self, event=None):
        """
        Debounce Resize-Events um zu häufiges Neuzeichnen zu vermeiden.
        """
        if self.resize_timer is not None:
            self.root.after_cancel(self.resize_timer)
        self.resize_timer = self.root.after(200, self._fit_font_to_label)

    def _fit_font_to_label(self):
        """
        Passt die Schriftgröße so an, dass die aktuelle Zeitdarstellung
        maximal groß in das Label passt (mit einem Rand).
        """
        text = self.label.cget("text") or "00:00"
        # Innenabstand/Rand
        margin_ratio = 0.08  # 8 % innen
        avail_w = max(1, int(self.label.winfo_width() * (1 - margin_ratio)))
        avail_h = max(1, int(self.label.winfo_height() * (1 - margin_ratio)))

        # Binäre Suche nach optimaler Schriftgröße
        low, high = 10, 800  # plausible Werte
        best = low
        while low <= high:
            mid = (low + high) // 2
            test_font = tkfont.Font(family=self.font_family, size=mid, weight="bold")
            tw = test_font.measure(text)
            th = test_font.metrics("linespace")
            if tw <= avail_w and th <= avail_h:
                best = mid
                low = mid + 1
            else:
                high = mid - 1

        # Setze Font nur, wenn sich die Größe wirklich ändert
        if self.current_font.cget("size") != best:
            self.current_font.configure(size=best)
            self.label.configure(font=self.current_font)

    def _update_display(self):
        text = format_hhmmss(self.remaining)
        # Aktualisiere Label nur, wenn sich der Text ändert (nicht jeden Frame neu zeichnen)
        if self.label.cget("text") != text:
            self.label.config(text=text)
            self._fit_font_to_label()

    def _tick(self):
        if not self.paused and self.remaining > 0:
            self.remaining -= 1
            self._update_display()
            # Prüfe ob einer der konfigurierten Zeitwerte erreicht wurde
            self._check_alerts()
        elif self.remaining == 0:
            # Optional: Farbwechsel oder Signal am Ende
            self.label.config(fg="#FF4D4D")  # Rot, wenn fertig
            # Beende alle Audio-Wiedergaben
            stop_all_sounds()
        # Jede Sekunde erneut aufrufen
        self.root.after(1000, self._tick)
    
    def _check_alerts(self):
        """
        Prüft, ob der aktuelle Zeitwert mit einem konfigurierten Alert übereinstimmt.
        Falls ja, wird zufällig ein Sound aus der Liste ausgewählt und abgespielt.
        """
        for alert_time, sounds_list in self.alerts:
            if self.remaining == alert_time and alert_time not in self.triggered_alerts:
                self.triggered_alerts.add(alert_time)
                # Wähle zufällig einen Sound aus der Liste
                sound_entry = random.choice(sounds_list)
                sound_file = sound_entry[0]
                start_offset = sound_entry[1] if len(sound_entry) > 1 else 0
                fade = sound_entry[2] if len(sound_entry) > 2 else 0
                fade_sec = fade / 1000.0
                print(f"Alert ausgelöst bei {format_hhmmss(alert_time)}: Spielen {sound_file} (Offset: {start_offset}s, Fade: {fade_sec}s)")
                play_sound_async(sound_file, start_offset, fade)
                
def get_largest_monitor():
    """
    Findet den Monitor mit der größten Fläche und gibt die Koordinaten zurück.
    Rückgabe: (screen_w, screen_h, offset_x, offset_y)
    """
    try:
        # Versuche screeninfo zu verwenden (falls installiert)
        import screeninfo
        monitors = screeninfo.get_monitors()
        if monitors:
            # Finde Monitor mit größter Fläche
            largest = max(monitors, key=lambda m: m.width * m.height)
            #print(f"Monitor-Informationen gefunden:")
            for i, m in enumerate(monitors):
                area = m.width * m.height
                #print(f"  Monitor {i}: {m.width}x{m.height} ({area} px²) at {m.x},{m.y}")
            #print(f"Größter Monitor: {largest.width}x{largest.height} at {largest.x},{largest.y}")
            return (largest.width, largest.height, largest.x, largest.y)
    except ImportError:
        pass
    except Exception as e:
        print(f"Fehler beim Abfragen der Monitore: {e}")
        
def test_screens():
    for monitor in get_monitors():
        print(str(monitor))
        print(f"Monitor: {monitor.name}")
        print(f"Breite: {monitor.width}px")
        print(f"Höhe: {monitor.height}px")
        print(f"Position: x={monitor.x}, y={monitor.y}")
    #   print(f"Ist primär: {monitor.is_primary}")
        print("---")


    # Primären Monitor finden
    #primary = next((m for m in get_monitors() if m.is_primary), get_monitors()[0])
    #print(f"Primärer Monitor: {primary.width}x{primary.height}")
    """
    Test-Routine: Nutzt get_largest_monitor() und printet alle Return-Werte.
    """
    print("\n" + "="*60)
    print("MONITOR-TEST: get_largest_monitor()")
    print("="*60 + "\n")
    
    screen_w, screen_h, offset_x, offset_y = get_largest_monitor()
    
    print(f"Rückgabewerte von get_largest_monitor():")
    print(f"  screen_w (Breite): {screen_w}px")
    print(f"  screen_h (Höhe): {screen_h}px")
    print(f"  offset_x (X-Position): {offset_x}px")
    print(f"  offset_y (Y-Position): {offset_y}px")
    print(f"\nGrößter Monitor: {screen_w}x{screen_h} at ({offset_x}, {offset_y})")
    print("="*60 + "\n")
    
def test_sounds():
    """
    Test-Routine: Spielt alle konfigurierten Sounds nacheinander mit 2 Sekunden Pause ab.
    """
    print("\n" + "="*60)
    print("SOUND-TEST: Spielen aller Sounds...")
    print("="*60 + "\n")
    
    try:
        config = read_config(CONFIG_PATH)
        alerts = config["alerts"]
    except Exception as e:
        print(f"Fehler beim Lesen der Konfiguration: {e}")
        return
    
    # Sammle alle einzigartigen Sounds
    all_sounds = []
    for alert_time, sounds_list in alerts:
        for sound_entry in sounds_list:
            sound_file = sound_entry[0]
            start_offset = sound_entry[1] if len(sound_entry) > 1 else 0
            fade = sound_entry[2] if len(sound_entry) > 2 else 0
            all_sounds.append((sound_file, start_offset, fade, alert_time))
    
    # Spielen Sie jeden Sound ab
    for idx, (sound_file, start_offset, fade, alert_time) in enumerate(all_sounds, 1):
        fade_sec = fade / 1000.0
        print(f"[{idx}/{len(all_sounds)}] Spielen: {sound_file}")
        print(f"  Offset: {start_offset}s, Fade: {fade_sec}s, Alert-Zeit: {format_hhmmss(alert_time)}")
        play_sound_async(sound_file, start_offset, fade)
        
        # Warte auf Sound + 2 Sekunden Pause
        time.sleep(15)  # Großzügige Wartezeit für Sound + 2s Pause
    
    print("="*60)
    print("Sound-Test abgeschlossen!")
    print("="*60 + "\n")

def main():
    # Test-Modus aktivieren mit --test Flag
    if "--test" in sys.argv:
        test_sounds()
        return

    if "--screens" in sys.argv:
        test_screens()
        return
    
    try:
        config = read_config(CONFIG_PATH)
        start_seconds = config["start_seconds"]
        alerts = config["alerts"]
    except Exception as e:
        print(f"Fehler beim Lesen der Konfiguration: {e}")
        print("Erstelle eine gültige 'config.ini' mit Abschnitt [timer] und Schlüssel 'start'.")
        return

    # Ausgabe aller Soundfiles und Keys
    """
    print("\n" + "="*60)
    print("KONFIGURIERTE SOUNDFILES:")
    print("="*60)
    cfg = configparser.ConfigParser()
    cfg.read(CONFIG_PATH, encoding="utf-8")
    
    sound_keys = [key for key in cfg["timer"].keys() if key.startswith("sound_file_")]
    sound_keys.sort(key=lambda x: (int(x.split("_")[2]), int(x.split("_")[3])))
    
    for key in sound_keys:
        value = cfg["timer"][key]
        print(f"  {key}: {value}")
    print("="*60 + "\n")
    """

    root = tk.Tk()
    # Hintergrund schwarz, Text weiß – guter Kontrast
    root.configure(bg="#000000")
    app = CountdownApp(root, start_seconds, alerts)
    root.mainloop()

if __name__ == "__main__":
    main()


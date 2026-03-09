from datetime import datetime, timedelta
import json
from db import get_connection

def schedule_all_matches(config_path):
    """
    Berechnet und setzt die Startzeiten für alle Matches basierend auf der Konfiguration.
    Berücksichtigt:
    - Parallele Felder (fields)
    - Match-Dauer (2 Sätze + Pausen)
    - Pausen zwischen Matches
    - Lunch-Break optimal zwischen vollständigen Matches platziert
    """
    with open(config_path, "r", encoding="utf-8") as f:
        cfg = json.load(f)

    start_time = datetime.fromisoformat(cfg["tournament_start"])
    fields = cfg["fields"]
    sets_per_match = cfg.get("sets_per_match", 2)

    set_time = timedelta(minutes=cfg["set_minutes"])
    set_pause = timedelta(minutes=cfg["pause_between_sets"])
    match_pause = timedelta(minutes=cfg["pause_between_matches"])

    # Dauer eines Matches: N Sätze + Pausen zwischen Sätzen + Pause nach Match
    # Pause zwischen Sätzen: (sets_per_match - 1) * set_pause
    match_duration = set_time * sets_per_match + set_pause * (sets_per_match - 1) + match_pause

    lunch_preferred_start = datetime.fromisoformat(cfg["lunch_break"]["start"])
    lunch_duration = timedelta(minutes=cfg["lunch_break"]["duration_minutes"])

    conn = get_connection()
    matches = conn.execute("SELECT id FROM matches ORDER BY id").fetchall()

    # Jedes Feld hat eine eigene "nächste verfügbare Zeit"
    field_available_at = [start_time for _ in range(fields)]
    lunch_taken = False

    for match in matches:
        # Wähle das Feld, das als nächstes frei wird
        field = min(range(fields), key=lambda i: field_available_at[i])
        start = field_available_at[field]

        # Prüfe ob Lunch-Break eingefügt werden soll
        # Bedingung: Alle Felder sind frei UND wir sind nahe dem bevorzugten Lunch-Zeitpunkt
        if not lunch_taken:
            earliest_field_time = min(field_available_at)
            latest_field_time = max(field_available_at)
            
            # Alle Felder sind zur gleichen Zeit frei (oder sehr nah beieinander)
            all_fields_free = (latest_field_time - earliest_field_time).total_seconds() < 60
            
            # Wir haben den bevorzugten Lunch-Zeitpunkt erreicht oder überschritten
            past_lunch_time = earliest_field_time >= lunch_preferred_start
            
            if all_fields_free and past_lunch_time:
                # Lunch-Break jetzt einplanen
                lunch_end = earliest_field_time + lunch_duration
                field_available_at = [lunch_end for _ in range(fields)]
                start = lunch_end
                lunch_taken = True
                print(f"Lunch-Break eingeplant: {earliest_field_time.strftime('%H:%M')} - {lunch_end.strftime('%H:%M')}")

        conn.execute(
            "UPDATE matches SET start_time = ?, field_number = ? WHERE id = ?",
            (start.isoformat(sep=" "), field + 1, match["id"])
        )

        # Nächster verfügbarer Zeitpunkt für dieses Feld
        field_available_at[field] = start + match_duration

    conn.commit()
    conn.close()
    
    print(f"{len(matches)} Matches zeitlich eingeplant.")


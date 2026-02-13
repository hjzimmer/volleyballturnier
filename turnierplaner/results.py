import json
from db import get_connection

def load_config():
    """Lädt die Turnierkonfiguration."""
    with open('turnier_config.json', 'r', encoding='utf-8') as f:
        return json.load(f)

def enter_result(match_id, *sets):
    """
    Trägt Ergebnisse für ein Match ein.
    
    Args:
        match_id: Die ID des Matches
        *sets: Variable Anzahl von Satz-Tupeln (team1_points, team2_points)
    """
    conn = get_connection()
    
    # Wenn alte Signatur mit genau 2 Sätzen verwendet wird (Rückwärtskompatibilität)
    if len(sets) == 2 and isinstance(sets[0], (tuple, list)) and isinstance(sets[1], (tuple, list)):
        set_results = sets
    else:
        # Neue Verwendung: enter_result(match_id, (10, 8), (12, 10), ...)
        set_results = sets
    
    # Speichere alle Sätze
    for set_number, (team1_points, team2_points) in enumerate(set_results, start=1):
        conn.execute(
            "INSERT INTO sets (match_id, set_number, team1_points, team2_points) VALUES (?, ?, ?, ?)",
            (match_id, set_number, team1_points, team2_points)
        )
    
    conn.execute(
        "UPDATE matches SET finished = 1 WHERE id = ?",
        (match_id,)
    )

    conn.commit()
    conn.close()


def evaluate_final_match(match_id):
    """
    Bestimmt den Gewinner eines Finalspiels basierend auf gewonnenen Sätzen.
    Bei Gleichstand wird nach Gesamtpunkten entschieden.
    
    Returns:
        1 wenn Team 1 gewonnen hat, 2 wenn Team 2 gewonnen hat
    """
    conn = get_connection()
    sets = conn.execute(
        "SELECT team1_points, team2_points FROM sets WHERE match_id = ?",
        (match_id,)
    ).fetchall()

    # Zähle gewonnene Sätze
    team1_sets = sum(1 for s in sets if s["team1_points"] > s["team2_points"])
    team2_sets = sum(1 for s in sets if s["team2_points"] > s["team1_points"])
    
    conn.close()

    # Gewinner ist wer mehr Sätze gewonnen hat
    if team1_sets > team2_sets:
        return 1
    elif team2_sets > team1_sets:
        return 2
    else:
        # Bei Gleichstand: Gesamtpunkte als Entscheidung
        total1 = sum(s["team1_points"] for s in sets)
        total2 = sum(s["team2_points"] for s in sets)
        
        if total1 > total2:
            return 1
        elif total2 > total1:
            return 2
        else:
            raise ValueError("Unentschieden in der Endrunde nicht erlaubt")

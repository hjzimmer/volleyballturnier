
from db import get_connection

def enter_result(match_id, set1, set2):
    conn = get_connection()

    conn.execute(
        "INSERT INTO sets (match_id, set_number, team1_points, team2_points) VALUES (?, 1, ?, ?)",
        (match_id, set1[0], set1[1])
    )
    conn.execute(
        "INSERT INTO sets (match_id, set_number, team1_points, team2_points) VALUES (?, 2, ?, ?)",
        (match_id, set2[0], set2[1])
    )

    conn.execute(
        "UPDATE matches SET finished = 1 WHERE id = ?",
        (match_id,)
    )

    conn.commit()
    conn.close()


def evaluate_final_match(match_id):
    conn = get_connection()
    sets = conn.execute(
        "SELECT team1_points, team2_points FROM sets WHERE match_id = ?",
        (match_id,)
    ).fetchall()

    total1 = sum(s["team1_points"] for s in sets)
    total2 = sum(s["team2_points"] for s in sets)

    conn.close()

    if total1 > total2:
        return 1
    elif total2 > total1:
        return 2
    else:
        raise ValueError("Unentschieden in der Endrunde nicht erlaubt")

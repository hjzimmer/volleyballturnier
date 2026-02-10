
from db import get_connection

def calculate_group_standings(group_id: int):
    conn = get_connection()

    teams = conn.execute(
        "SELECT team_id FROM group_teams WHERE group_id = ?",
        (group_id,)
    ).fetchall()

    team_ids = [t["team_id"] for t in teams]

    table = {
        tid: {"points": 0, "point_diff": 0, "matches": {}}
        for tid in team_ids
    }

    rows = conn.execute(
        '''
        SELECT m.team1_id, m.team2_id,
               s.team1_points, s.team2_points
        FROM matches m
        JOIN sets s ON s.match_id = m.id
        WHERE m.group_id = ?
        ''',
        (group_id,)
    ).fetchall()

    for r in rows:
        t1, t2 = r["team1_id"], r["team2_id"]
        p1, p2 = r["team1_points"], r["team2_points"]

        if p1 > p2:
            sp1, sp2 = 2, 0
        elif p2 > p1:
            sp1, sp2 = 0, 2
        else:
            sp1, sp2 = 1, 1

        diff = p1 - p2

        table[t1]["points"] += sp1
        table[t2]["points"] += sp2
        table[t1]["point_diff"] += diff
        table[t2]["point_diff"] -= diff

        table[t1]["matches"].setdefault(t2, {"points": 0, "point_diff": 0})
        table[t2]["matches"].setdefault(t1, {"points": 0, "point_diff": 0})

        table[t1]["matches"][t2]["points"] += sp1
        table[t2]["matches"][t1]["points"] += sp2
        table[t1]["matches"][t2]["point_diff"] += diff
        table[t2]["matches"][t1]["point_diff"] -= diff

    conn.close()
    return sort_group_table(table)


def sort_group_table(table: dict):
    items = list(table.items())
    items.sort(key=lambda x: (x[1]["points"], x[1]["point_diff"]), reverse=True)

    i = 0
    while i < len(items) - 1:
        t1, d1 = items[i]
        t2, d2 = items[i + 1]
        if d1["points"] == d2["points"]:
            direct = d1["matches"].get(t2)
            if direct and direct["points"] < d2["matches"][t1]["points"]:
                items[i], items[i + 1] = items[i + 1], items[i]
        i += 1

    return [tid for tid, _ in items]

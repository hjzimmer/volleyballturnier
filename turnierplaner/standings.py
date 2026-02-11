
from db import get_connection

def calculate_group_standings(group_id: int):
    conn = get_connection()

    teams = conn.execute(
        "SELECT team_id FROM group_teams WHERE group_id = ?",
        (group_id,)
    ).fetchall()

    team_ids = [t["team_id"] for t in teams]

    table = {
        tid: {"points": 0, "sets_won": 0, "point_diff": 0, "matches": {}}
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
            table[t1]["sets_won"] += 1
        elif p2 > p1:
            sp1, sp2 = 0, 2
            table[t2]["sets_won"] += 1
        else:
            sp1, sp2 = 1, 1

        diff = p1 - p2

        table[t1]["points"] += sp1
        table[t2]["points"] += sp2
        table[t1]["point_diff"] += diff
        table[t2]["point_diff"] -= diff

        table[t1]["matches"].setdefault(t2, {"points": 0, "sets_won": 0, "point_diff": 0})
        table[t2]["matches"].setdefault(t1, {"points": 0, "sets_won": 0, "point_diff": 0})

        table[t1]["matches"][t2]["points"] += sp1
        table[t2]["matches"][t1]["points"] += sp2
        if p1 > p2:
            table[t1]["matches"][t2]["sets_won"] += 1
        elif p2 > p1:
            table[t2]["matches"][t1]["sets_won"] += 1
        table[t1]["matches"][t2]["point_diff"] += diff
        table[t2]["matches"][t1]["point_diff"] -= diff

    conn.close()
    return sort_group_table(table)


def sort_group_table(table: dict):
    """
    Sortiert die Gruppentabelle nach:
    1. Satzpunkte (points)
    2. Gewonnene Sätze (sets_won)
    3. Punktdifferenz (point_diff)
    4. Direkter Vergleich (nur relevant wenn genau 2 Teams punktgleich sind)
    """
    items = list(table.items())
    
    def compare_teams(item1, item2):
        t1_id, t1_data = item1
        t2_id, t2_data = item2
        
        # 1. Nach Satzpunkten (höher ist besser)
        if t1_data["points"] != t2_data["points"]:
            return t2_data["points"] - t1_data["points"]
        
        # 2. Nach gewonnenen Sätzen (höher ist besser)
        if t1_data["sets_won"] != t2_data["sets_won"]:
            return t2_data["sets_won"] - t1_data["sets_won"]
        
        # 3. Nach Punktdifferenz (höher ist besser)
        if t1_data["point_diff"] != t2_data["point_diff"]:
            return t2_data["point_diff"] - t1_data["point_diff"]
        
        # 4. Direkter Vergleich (nur wenn sie gegeneinander gespielt haben)
        if t2_id in t1_data["matches"] and t1_id in t2_data["matches"]:
            direct_t1 = t1_data["matches"][t2_id]["points"]
            direct_t2 = t2_data["matches"][t1_id]["points"]
            
            if direct_t1 != direct_t2:
                return direct_t2 - direct_t1
            
            # Bei gleichem Punktestand: Gewonnene Sätze im direkten Vergleich
            direct_sets_t1 = t1_data["matches"][t2_id]["sets_won"]
            direct_sets_t2 = t2_data["matches"][t1_id]["sets_won"]
            
            if direct_sets_t1 != direct_sets_t2:
                return direct_sets_t2 - direct_sets_t1
            
            # Bei gleichen gewonnenen Sätzen: Punktdifferenz im direkten Vergleich
            direct_diff_t1 = t1_data["matches"][t2_id]["point_diff"]
            direct_diff_t2 = t2_data["matches"][t1_id]["point_diff"]
            
            if direct_diff_t1 != direct_diff_t2:
                return direct_diff_t2 - direct_diff_t1
        
        return 0
    
    from functools import cmp_to_key
    items.sort(key=cmp_to_key(compare_teams))
    
    return [tid for tid, _ in items]

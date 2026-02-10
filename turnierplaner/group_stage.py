from itertools import combinations
from db import get_connection

def schedule_group_matches_optimized(matches):
    """
    Ordnet Matches so an, dass Teams möglichst nicht direkt nacheinander spielen.
    Verwendet einen Greedy-Algorithmus.
    """
    scheduled = []
    remaining = list(matches)
    last_teams = set()
    
    while remaining:
        best_match = None
        best_score = -1
        
        for match in remaining:
            # Score: Anzahl der Teams in diesem Match, die NICHT gerade gespielt haben
            score = sum(1 for team in match if team not in last_teams)
            
            if score > best_score:
                best_score = score
                best_match = match
        
        # Füge bestes Match hinzu
        scheduled.append(best_match)
        remaining.remove(best_match)
        
        # Update last_teams - nur die letzten 1-2 Matches merken
        last_teams = set(best_match)
    
    return scheduled

def generate_interleaved_group_matches():
    conn = get_connection()

    teams_a = [r["team_id"] for r in conn.execute(
        "SELECT team_id FROM group_teams WHERE group_id = 1 ORDER BY team_id"
    )]

    teams_b = [r["team_id"] for r in conn.execute(
        "SELECT team_id FROM group_teams WHERE group_id = 2 ORDER BY team_id"
    )]

    matches_a = list(combinations(teams_a, 2))
    matches_b = list(combinations(teams_b, 2))
    
    # Optimiere die Reihenfolge für beide Gruppen
    matches_a_scheduled = schedule_group_matches_optimized(matches_a)
    matches_b_scheduled = schedule_group_matches_optimized(matches_b)

    print(f"Matches optimiert erstellt.")

    match_id = 1

    # Füge Matches abwechselnd ein (für zwei Felder)
    max_len = max(len(matches_a_scheduled), len(matches_b_scheduled))
    
    for i in range(max_len):
        if i < len(matches_a_scheduled):
            ma = matches_a_scheduled[i]
            conn.execute(
                '''
                INSERT INTO matches 
                (id, phase, group_id, round, team1_id, team2_id, referee_team_id) 
                VALUES (?, 'group', 1, 'Gruppe A', ?, ?, 0)
                ''',
                (match_id, ma[0], ma[1])
            )
            match_id += 1

        if i < len(matches_b_scheduled):
            mb = matches_b_scheduled[i]
            conn.execute(
                '''
                INSERT INTO matches 
                (id, phase, group_id, round, team1_id, team2_id, referee_team_id) 
                VALUES (?, 'group', 2, 'Gruppe B', ?, ?, 0)
                ''',
                (match_id, mb[0], mb[1])
            )
            match_id += 1

    conn.commit()
    conn.close()

    print(f"Matches gespeichert.")
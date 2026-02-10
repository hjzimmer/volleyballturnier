from db import get_connection

def assign_group_referees():
    """
    Weist Schiedsrichter für Gruppenspiele zu.
    Ein Schiedsrichter ist ein Team aus der gleichen Gruppe, das im selben Zeitslot nicht spielt.
    """
    conn = get_connection()
    
    # Hole alle Gruppenspiele sortiert nach ID (entspricht dem Zeitplan)
    matches = conn.execute("""
        SELECT id, group_id, team1_id, team2_id 
        FROM matches 
        WHERE phase = 'group'
        ORDER BY id
    """).fetchall()
    
    # Hole alle Teams pro Gruppe
    teams_by_group = {}
    for group_id in [1, 2]:
        teams = conn.execute(
            "SELECT team_id FROM group_teams WHERE group_id = ? ORDER BY team_id",
            (group_id,)
        ).fetchall()
        teams_by_group[group_id] = [t["team_id"] for t in teams]
    
    # Zähler für Schiedsrichter-Einsätze pro Team
    referee_count = {team: 0 for group_teams in teams_by_group.values() for team in group_teams}
    
    for match in matches:
        group_id = match["group_id"]
        playing_teams = {match["team1_id"], match["team2_id"]}
        
        # Finde verfügbare Teams aus derselben Gruppe
        available_teams = [
            team for team in teams_by_group[group_id] 
            if team not in playing_teams
        ]
        
        # Wähle das Team mit den wenigsten Schiedsrichter-Einsätzen
        if available_teams:
            referee = min(available_teams, key=lambda t: referee_count[t])
            referee_count[referee] += 1
            
            conn.execute(
                "UPDATE matches SET referee_team_id = ? WHERE id = ?",
                (referee, match["id"])
            )
            print(f"Match {match['id']}: Team {referee} als Schiedsrichter zugewiesen")
        else:
            print(f"Match {match['id']}: Kein verfügbares Schiedsrichter-Team gefunden")
    
    conn.commit()
    conn.close()
    
    print(f"Schiedsrichter für Gruppenphase zugewiesen.")


def assign_final_referee(match_id):
    """
    Weist einen Schiedsrichter für ein Finalrunden-Match zu.
    Wählt ein Team, das in diesem Match nicht spielt und möglichst wenig Schiedsrichter-Einsätze hat.
    """
    conn = get_connection()
    
    # Hole Match-Informationen
    match = conn.execute(
        "SELECT team1_id, team2_id FROM matches WHERE id = ?",
        (match_id,)
    ).fetchone()
    
    if not match or not match["team1_id"] or not match["team2_id"]:
        conn.close()
        return None
    
    playing_teams = {match["team1_id"], match["team2_id"]}
    
    # Hole alle Teams und zähle ihre Schiedsrichter-Einsätze
    teams = conn.execute("SELECT id FROM teams ORDER BY id").fetchall()
    
    team_referee_counts = []
    for team in teams:
        team_id = team["id"]
        if team_id in playing_teams:
            continue
        
        # Zähle Einsätze
        count = conn.execute(
            "SELECT COUNT(*) as cnt FROM matches WHERE referee_team_id = ?",
            (team_id,)
        ).fetchone()["cnt"]
        
        team_referee_counts.append((team_id, count))
    
    # Wähle Team mit wenigsten Einsätzen
    if team_referee_counts:
        referee = min(team_referee_counts, key=lambda x: x[1])[0]
        conn.execute(
            "UPDATE matches SET referee_team_id = ? WHERE id = ?",
            (referee, match_id)
        )
        conn.commit()
        conn.close()
        return referee
    
    conn.close()
    return None

from db import get_connection

def assign_group_referees():
    """
    Weist Schiedsrichter für Gruppenspiele zu.
    Ein Schiedsrichter ist ein Team aus der gleichen Gruppe, das im selben Zeitslot nicht spielt.
    """
    conn = get_connection()
    
    # Hole alle Gruppenspiele sortiert nach Startzeit und ID
    matches = conn.execute("""
        SELECT id, group_id, team1_id, team2_id, start_time
        FROM matches 
        WHERE phase = 'group'
        ORDER BY start_time, id
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
        start_time = match["start_time"]
        playing_teams = {match["team1_id"], match["team2_id"]}
        
        # Finde alle Teams, die zur gleichen Zeit spielen (auch in anderen Gruppen)
        concurrent_matches = conn.execute("""
            SELECT team1_id, team2_id 
            FROM matches 
            WHERE start_time = ? AND id != ?
            AND team1_id IS NOT NULL AND team2_id IS NOT NULL
        """, (start_time, match["id"])).fetchall()
        
        for concurrent_match in concurrent_matches:
            playing_teams.add(concurrent_match["team1_id"])
            playing_teams.add(concurrent_match["team2_id"])
        
        # Finde Teams, die bereits als Schiedsrichter für Spiele zur gleichen Zeit zugewiesen sind
        already_refereeing = conn.execute("""
            SELECT referee_team_id 
            FROM matches 
            WHERE start_time = ? AND id != ?
            AND referee_team_id IS NOT NULL
        """, (start_time, match["id"])).fetchall()
        
        for ref_match in already_refereeing:
            if ref_match["referee_team_id"]:
                playing_teams.add(ref_match["referee_team_id"])
        
        # Finde den nächsten Zeitslot
        next_time_result = conn.execute("""
            SELECT MIN(start_time) as next_time
            FROM matches
            WHERE start_time > ?
        """, (start_time,)).fetchone()
        
        if next_time_result and next_time_result["next_time"]:
            next_time = next_time_result["next_time"]
            
            #print(f"in prüfung for next matches")
            # Hole alle Teams, die im nächsten Zeitslot spielen
            next_slot_matches = conn.execute("""
                SELECT team1_id, team2_id 
                FROM matches 
                WHERE start_time = ?
                AND team1_id IS NOT NULL AND team2_id IS NOT NULL
            """, (next_time,)).fetchall()
            
            # Schließe diese Teams aus
            for next_match in next_slot_matches:
                if next_match["team1_id"]:
                    playing_teams.add(next_match["team1_id"])
                    #print(f"team x {next_match['team2_id']} ausgeschlossen")
                if next_match["team2_id"]:
                    playing_teams.add(next_match["team2_id"])
                    #print(f"team y {next_match['team2_id']} ausgeschlossen")
        
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
    Wählt ein Team, das in diesem Match nicht spielt, zur gleichen Zeit nicht auf einem anderen Feld spielt,
    und möglichst wenig Schiedsrichter-Einsätze hat.
    """
    conn = get_connection()
    
    # Hole Match-Informationen inkl. Startzeit
    match = conn.execute(
        "SELECT team1_id, team2_id, start_time FROM matches WHERE id = ?",
        (match_id,)
    ).fetchone()
    
    if not match or not match["team1_id"] or not match["team2_id"]:
        conn.close()
        return None
    
    playing_teams = {match["team1_id"], match["team2_id"]}
    start_time = match["start_time"]
    
    # Hole alle Teams, die zur gleichen Zeit auf anderen Feldern spielen
    concurrent_matches = conn.execute("""
        SELECT team1_id, team2_id 
        FROM matches 
        WHERE start_time = ? AND id != ? 
        AND team1_id IS NOT NULL AND team2_id IS NOT NULL
    """, (start_time, match_id)).fetchall()
    
    # Sammle alle Teams, die zur gleichen Zeit spielen
    for concurrent_match in concurrent_matches:
        playing_teams.add(concurrent_match["team1_id"])
        playing_teams.add(concurrent_match["team2_id"])
    
    # Schließe Teams aus, die bereits als Schiedsrichter zur gleichen Zeit zugewiesen sind
    already_refereeing = conn.execute("""
        SELECT referee_team_id 
        FROM matches 
        WHERE start_time = ? AND id != ? AND referee_team_id IS NOT NULL
    """, (start_time, match_id)).fetchall()
    
    for ref in already_refereeing:
        playing_teams.add(ref["referee_team_id"])
    
    # Finde den nächsten Zeitslot
    next_time = conn.execute("""
        SELECT MIN(start_time) as next_time
        FROM matches
        WHERE start_time > ?
    """, (start_time,)).fetchone()["next_time"]
    
    if next_time:
        # Hole alle Teams, die im nächsten Zeitslot spielen
        next_slot_matches = conn.execute("""
            SELECT team1_id, team2_id 
            FROM matches 
            WHERE start_time = ?
            AND team1_id IS NOT NULL AND team2_id IS NOT NULL
        """, (next_time,)).fetchall()
        
        # Schließe diese Teams aus
        for next_match in next_slot_matches:
            playing_teams.add(next_match["team1_id"])
            playing_teams.add(next_match["team2_id"])
    
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


def assign_final_referees():
    """
    Weist Schiedsrichter für alle Finalrunden-Matches zu.
    Berücksichtigt nur unbespielte Paarungen (finished = 0) mit festgelegten Teams.
    """
    conn = get_connection()
    
    # Hole alle ungespielten Finalrunden-Matches mit beiden Teams
    matches = conn.execute("""
        SELECT id, team1_id, team2_id 
        FROM matches 
        WHERE phase = 'final' 
        AND finished = 0
        AND team1_id IS NOT NULL 
        AND team2_id IS NOT NULL
        ORDER BY start_time, id
    """).fetchall()
    
    assigned_count = 0
    skipped_count = 0
    
    for match in matches:
        match_id = match["id"]
        
#        # Prüfe ob bereits ein Schiedsrichter zugewiesen ist
#        existing_ref = conn.execute(
#            "SELECT referee_team_id FROM matches WHERE id = ?",
#            (match_id,)
#        ).fetchone()["referee_team_id"]
#        
#        if existing_ref:
#            print(f"Match {match_id}: Schiedsrichter bereits zugewiesen (Team {existing_ref})")
#            skipped_count += 1
#            continue
#        
        # Weise Schiedsrichter zu
        referee = assign_final_referee(match_id)
        
        if referee:
            print(f"Match {match_id}: Team {referee} als Schiedsrichter zugewiesen")
            assigned_count += 1
        else:
            print(f"Match {match_id}: Kein verfügbares Schiedsrichter-Team gefunden")
    
    conn.close()
    
    print()
    print(f"Schiedsrichter für Finalrunde zugewiesen:")
    print(f"  - {assigned_count} neu zugewiesen")
    print(f"  - {skipped_count} bereits zugewiesen")
    print(f"  - {len(matches)} Matches gesamt")

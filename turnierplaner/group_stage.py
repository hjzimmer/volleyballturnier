def generate_interleaved_group_matches_for_phase(phase):
    """
    Erzeugt alle Gruppenspiele für eine beliebige Phase (beliebig viele Gruppen, beliebige Team-IDs oder Platzierungs-Strings).
    Für Gruppen mit echten Team-IDs (int) werden die Matches direkt erzeugt.
    Für Gruppen mit Platzierungs-Strings (z.B. "G1_1") wird KEIN Match erzeugt (Teams müssen erst aufgelöst werden).
    """
    from db import get_connection
    from itertools import combinations
    conn = get_connection()
    group_matches = []
    for group in phase.get("groups", []):
        teams = group.get("teams", [])
        # Erzeuge alle Paarungen, egal ob int (ID) oder str (Platzierungsreferenz)
        if len(teams) < 2:
            continue  # Keine Matches möglich
        matches = list(combinations(teams, 2))
        scheduled = schedule_group_matches_optimized(matches)
        group_matches.append({
            "group_id": group["id"],
            "group_name": group["name"],
            "matches": scheduled
        })
    print(f"Matches (inkl. Platzhalter) optimiert erstellt für Phase: {phase.get('name','?')}")
    # Ermittle aktuelle höchste Match-ID
    max_id = conn.execute("SELECT MAX(id) FROM matches").fetchone()[0]
    match_id = (max_id or 0) + 1
    max_len = max((len(g["matches"]) for g in group_matches), default=0)
    for i in range(max_len):
        for g in group_matches:
            if i < len(g["matches"]):
                t1, t2 = g["matches"][i]
                team1_id = t1 if isinstance(t1, int) else -1
                team2_id = t2 if isinstance(t2, int) else -1
                import json as _json
                if isinstance(t1, dict):
                    team1_ref = _json.dumps(t1, ensure_ascii=False)
                elif isinstance(t1, str):
                    team1_ref = t1
                else:
                    team1_ref = None
                if isinstance(t2, dict):
                    team2_ref = _json.dumps(t2, ensure_ascii=False)
                elif isinstance(t2, str):
                    team2_ref = t2
                else:
                    team2_ref = None
                conn.execute(
                    '''
                    INSERT INTO matches 
                    (id, phase, group_id, round, team1_id, team2_id, referee_team_id, team1_ref, team2_ref) 
                    VALUES (?, 'group', ?, ?, ?, ?, 0, ?, ?)
                    ''',
                    (match_id, g["group_id"], g["group_name"], team1_id, team2_id, team1_ref, team2_ref)
                )
                match_id += 1
    conn.commit()
    conn.close()
    print(f"Matches (inkl. Platzhalter) gespeichert für Phase: {phase.get('name','?')}")


def resolve_placeholder_teams_in_matches(phase_results, match_results=None):
    """
    Ersetzt Platzhalter-Teams (team1_ref/team2_ref) in der Tabelle matches durch die korrekten Team-IDs.
    """
    import json
    conn = get_connection()
    matches = conn.execute(
        "SELECT id, team1_ref, team2_ref FROM matches WHERE (team1_id = -1 OR team2_id = -1) AND (team1_ref IS NOT NULL OR team2_ref IS NOT NULL)"
    ).fetchall()
    for match in matches:
        team1_id = None
        team2_id = None
        # team1_ref prüfen
        if match[1]:
            team1_ref = json.loads(match[1])
            if team1_ref.get("type") == "group_place":
                key = f"{team1_ref['group']}_{team1_ref['place']}"
                team1_id = phase_results.get(key, -1)
            elif team1_ref.get("type") == "match_winner":
                key = f"M{team1_ref['match_id']}_Winner" if team1_ref.get("winner", True) else f"M{team1_ref['match_id']}_Loser"
                if match_results:
                    team1_id = match_results.get(key, -1)
        # team2_ref prüfen
        if match[2]:
            team2_ref = json.loads(match[2])
            if team2_ref.get("type") == "group_place":
                key = f"{team2_ref['group']}_{team2_ref['place']}"
                team2_id = phase_results.get(key, -1)
            elif team2_ref.get("type") == "match_winner":
                key = f"M{team2_ref['match_id']}_Winner" if team2_ref.get("winner", True) else f"M{team2_ref['match_id']}_Loser"
                if match_results:
                    team2_id = match_results.get(key, -1)
        if (team1_id is not None and team1_id != -1) or (team2_id is not None and team2_id != -1):
            conn.execute(
                "UPDATE matches SET team1_id = COALESCE(NULLIF(?, -1), team1_id), team2_id = COALESCE(NULLIF(?, -1), team2_id) WHERE id = ?",
                (team1_id, team2_id, match[0])
            )
    conn.commit()
    conn.close()
    print("Platzhalter-Teams in Matches wurden aufgelöst.")
    
    
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

    def team_hashable(team):
        if isinstance(team, dict):
            return str(team)
        return team

    while remaining:
        best_match = None
        best_score = -1

        for match in remaining:
            # Score: Anzahl der Teams in diesem Match, die NICHT gerade gespielt haben
            score = sum(1 for team in match if team_hashable(team) not in last_teams)

            if score > best_score:
                best_score = score
                best_match = match

        # Füge bestes Match hinzu
        scheduled.append(best_match)
        remaining.remove(best_match)

        # Update last_teams - nur die letzten 1-2 Matches merken
        last_teams = set(team_hashable(t) for t in best_match)

    return scheduled

#  def generate_interleaved_group_matches():
#      conn = get_connection()#  
#      # Alle Gruppen der aktuellen Phase laden
#      groups = list(conn.execute("SELECT id, name FROM groups ORDER BY id"))
#      group_matches = []
#      for group in groups:
#          team_ids = [r["team_id"] for r in conn.execute(
#              "SELECT team_id FROM group_teams WHERE group_id = ? ORDER BY team_id", (group["id"],)
#          )]
#          matches = list(combinations(team_ids, 2))
#          scheduled = schedule_group_matches_optimized(matches)
#          group_matches.append({
#              "group_id": group["id"],
#              "group_name": group["name"],
#              "matches": scheduled
#          })#  
#      print(f"Matches optimiert erstellt.")#  
#      match_id = 1
#      # Füge Matches abwechselnd gruppenweise ein (Round-Robin über alle Gruppen)
#      max_len = max(len(g["matches"]) for g in group_matches)
#      for i in range(max_len):
#          for g in group_matches:
#              if i < len(g["matches"]):
#                  t1, t2 = g["matches"][i]
#                  conn.execute(
#                      '''
#                      INSERT INTO matches 
#                      (id, phase, group_id, round, team1_id, team2_id, referee_team_id) 
#                      VALUES (?, 'group', ?, ?, ?, ?, 0)
#                      ''',
#                      (match_id, g["group_id"], g["group_name"], t1, t2)
#                  )
#                  match_id += 1#  
#      conn.commit()
#      conn.close()
#      print(f"Matches gespeichert.")
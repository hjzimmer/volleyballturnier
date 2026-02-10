
from db import init_db
from seed import seed_teams, seed_groups
from group_stage import generate_interleaved_group_matches
from standings import calculate_group_standings
from finals import create_final_matches
from scheduling import schedule_all_matches
from referees import assign_group_referees

if __name__ == "__main__":
    init_db()
    seed_teams()
    seed_groups()
    generate_interleaved_group_matches()
    
    # Weise Schiedsrichter für Gruppenphase zu
    assign_group_referees()

    standings_a = calculate_group_standings(1)
    standings_b = calculate_group_standings(2)

    group_tables = {
        "A": standings_a,
        "B": standings_b
    }

    create_final_matches("final_config.json", group_tables)
    
    # Zeitplan für alle Matches erstellen
    schedule_all_matches("turnier_config.json")
    
    print("Gruppenphase & Endrunde initialisiert.")

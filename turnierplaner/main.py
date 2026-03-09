import sys
from validate_turnier_config import validate_turnier_config
from db import init_db
from seed import seed_teams, seed_groups
from group_stage import generate_interleaved_group_matches_for_phase
from standings import calculate_group_standings
from finals import create_final_matches
from scheduling import schedule_all_matches
from referees import assign_group_referees

if __name__ == "__main__":
    # Validierung der Turnier-Konfiguration
    if not validate_turnier_config("data/turnier_config.json"):
        print("[ABBRUCH] data/turnier_config.json ist fehlerhaft. Bitte korrigieren und erneut starten.")
        sys.exit(1)

    init_db()
    seed_teams()
    seed_groups()

    from tournament_phases import prepare_all_group_matches_and_tables
    group_tables = prepare_all_group_matches_and_tables("data/turnier_config.json")

    create_final_matches("data/turnier_config.json", group_tables)
    schedule_all_matches("data/turnier_config.json")
    assign_group_referees()
    print("Gruppenphasen & Endrunde initialisiert.")

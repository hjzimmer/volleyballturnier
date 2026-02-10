import argparse
from scheduling import schedule_all_matches
from referees import assign_group_referees

parser = argparse.ArgumentParser(description="Turnier CLI")
parser.add_argument("action", choices=["schedule", "assign_refs"])
args = parser.parse_args()

if args.action == "schedule":
    schedule_all_matches("time_config.json")
    print("Zeitplan erstellt")

elif args.action == "assign_refs":
    assign_group_referees()
    print("Schiedsrichter zugewiesen")

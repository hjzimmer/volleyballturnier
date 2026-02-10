import argparse
import subprocess
import sys
from scheduling import schedule_all_matches
from referees import assign_group_referees

parser = argparse.ArgumentParser(description="Turnier CLI")
parser.add_argument("action", choices=["init", "schedule", "assign_refs", "fill_results", "rename_team"])
args = parser.parse_args()

if args.action == "init":
    result = subprocess.run([sys.executable, "main.py"], capture_output=True, text=True)
    print(result.stdout)
    if result.stderr:
        print(result.stderr, file=sys.stderr)
    sys.exit(result.returncode)

elif args.action == "fill_results":
    result = subprocess.run([sys.executable, "fill_group_results.py"])
    sys.exit(result.returncode)

elif args.action == "rename_team":
    result = subprocess.run([sys.executable, "rename_team.py"])
    sys.exit(result.returncode)

elif args.action == "schedule":
    schedule_all_matches("time_config.json")
    print("Zeitplan erstellt")

elif args.action == "assign_refs":
    assign_group_referees()
    print("Schiedsrichter zugewiesen")

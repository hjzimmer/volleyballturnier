import json
from group_stage import generate_interleaved_group_matches_for_phase
from standings import calculate_group_standings
from db import get_connection

def prepare_all_group_matches_and_tables(config_path):
    """
    Legt für alle Phasen mit Gruppen die Gruppenspiele an und berechnet die Tabellen.
    Gibt group_tables als Dict zurück (group_id aus Konfig als Key).
    """
    with open(config_path, "r", encoding="utf-8") as f:
        config = json.load(f)
    phases = config.get("phases", [])

    # Für jede Phase mit Gruppen: Gruppenspiele anlegen
    for phase in phases:
        if "groups" in phase:
            print(f"Erzeuge Gruppenspiele für Phase: {phase['name']}")
            generate_interleaved_group_matches_for_phase(phase)

    # Dynamisch alle Gruppen-Tabellen berechnen
    group_tables = {}
    conn = get_connection()
    for phase in phases:
        if "groups" in phase:
            for group_idx, group in enumerate(phase["groups"], start=1):
                group_id = group_idx  # Annahme: IDs wie in seed_groups()
                standings = calculate_group_standings(group_id)
                group_tables[group["id"]] = standings
    conn.close()
    return group_tables
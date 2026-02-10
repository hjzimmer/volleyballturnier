PRAGMA foreign_keys = ON;

CREATE TABLE teams (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL
);

CREATE TABLE groups (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL
);

CREATE TABLE group_teams (
    group_id INTEGER NOT NULL,
    team_id INTEGER NOT NULL,
    PRIMARY KEY (group_id, team_id)
);

CREATE TABLE matches (
    id INTEGER PRIMARY KEY,
    phase TEXT NOT NULL,
    group_id INTEGER,
    round TEXT NOT NULL,
    team1_id INTEGER,
    team2_id INTEGER,
    team1_ref TEXT,
    team2_ref TEXT,
    referee_team_id INTEGER,
    field_number INTEGER,
    start_time DATETIME,
    finished INTEGER DEFAULT 0,
    winner_id INTEGER,
    loser_id INTEGER,
    winner_placement INTEGER,
    loser_placement INTEGER
);

CREATE TABLE sets (
    id INTEGER PRIMARY KEY,
    match_id INTEGER NOT NULL,
    set_number INTEGER CHECK (set_number IN (1,2)),
    team1_points INTEGER NOT NULL,
    team2_points INTEGER NOT NULL
);
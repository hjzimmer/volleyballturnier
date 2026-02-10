import sqlite3
import os

# Stelle sicher, dass data-Verzeichnis existiert
DB_DIR = 'data'
DB_PATH = os.path.join(DB_DIR, 'tournament.db')

if not os.path.exists(DB_DIR):
    os.makedirs(DB_DIR)

def get_connection():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

# Bestehende Datenbank löschen für sauberen Neustart und initialisieren
def init_db():
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)
    
    conn = get_connection()
    
    with open('schema.sql', 'r', encoding='utf-8') as f:
        schema = f.read()
    
    conn.executescript(schema)
    conn.commit()
    conn.close()
    
    print(f"Datenbank initialisiert.")
    

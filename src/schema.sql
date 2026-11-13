-- Hydrantenverwaltung: Datenbankschema (MySQL/MariaDB)
-- Alle Tabellen werden vom Installer angelegt. Keine Semikolons in Strings verwenden.
-- {name} wird beim Ausführen durch Tabellen-Präfix + name ersetzt (so kann die Datenbank mit anderen Anwendungen geteilt werden).

CREATE TABLE {users} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NULL,
  rolle VARCHAR(20) NOT NULL,
  aktiv INTEGER NOT NULL DEFAULT 1,
  token_hash CHAR(64) NULL,
  token_purpose VARCHAR(10) NULL,
  token_expires DATETIME NULL,
  auth_epoch INTEGER NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE UNIQUE INDEX {ux_users_email} ON {users} (email);
CREATE INDEX {ix_users_token} ON {users} (token_hash);

CREATE TABLE {settings} (
  skey VARCHAR(64) NOT NULL PRIMARY KEY,
  svalue TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {login_attempts} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  kind VARCHAR(10) NOT NULL,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX {ix_attempts_email} ON {login_attempts} (kind, email, attempted_at);
CREATE INDEX {ix_attempts_ip} ON {login_attempts} (kind, ip, attempted_at);

CREATE TABLE {audit_log} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  created_at DATETIME NOT NULL,
  user_id INTEGER NULL,
  user_label VARCHAR(190) NOT NULL,
  action VARCHAR(60) NOT NULL,
  object_type VARCHAR(40) NOT NULL,
  object_id INTEGER NULL,
  details TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX {ix_audit_created} ON {audit_log} (created_at);

-- Pruefdurchgang (Jahrespruefung)
CREATE TABLE {campaigns} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  aktiv INTEGER NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Touren sind feste Plaene und gehoeren zu keinem Pruefdurchgang. Ein Pruefdurchgang waehlt seine Touren ueber campaign_tours.
CREATE TABLE {tours} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  beschreibung TEXT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eine Tour in einem Pruefdurchgang, mit eigenem Link (Token)
CREATE TABLE {campaign_tours} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  campaign_id INTEGER NOT NULL,
  tour_id INTEGER NOT NULL,
  token CHAR(64) NOT NULL,
  link_aktiv INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (campaign_id) REFERENCES {campaigns} (id) ON DELETE CASCADE,
  FOREIGN KEY (tour_id) REFERENCES {tours} (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE UNIQUE INDEX {ux_ctours_token} ON {campaign_tours} (token);
CREATE UNIQUE INDEX {ux_ctours_pair} ON {campaign_tours} (campaign_id, tour_id);

-- Auswahllisten, vom Admin pflegbar. Die Hydranten speichern den unveraenderlichen Schluessel.
CREATE TABLE {hydrant_types} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  schluessel VARCHAR(30) NOT NULL,
  name VARCHAR(60) NOT NULL,
  symbol VARCHAR(20) NOT NULL,
  osm_wert VARCHAR(30) NULL,
  sortierung INTEGER NOT NULL DEFAULT 0,
  aktiv INTEGER NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE UNIQUE INDEX {ux_htypes_key} ON {hydrant_types} (schluessel);

CREATE TABLE {positions} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  schluessel VARCHAR(30) NOT NULL,
  name VARCHAR(60) NOT NULL,
  osm_wert VARCHAR(30) NULL,
  sortierung INTEGER NOT NULL DEFAULT 0,
  aktiv INTEGER NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE UNIQUE INDEX {ux_positions_key} ON {positions} (schluessel);

CREATE TABLE {hydrants} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  kennung VARCHAR(190) NOT NULL,
  typ VARCHAR(20) NOT NULL,
  durchmesser_dn INTEGER NULL,
  durchfluss_lmin INTEGER NULL,
  durchfluss_zeichen VARCHAR(1) NULL,
  druck_bar DECIMAL(5,2) NULL,
  position_art VARCHAR(30) NULL,
  lat DECIMAL(10,7) NULL,
  lon DECIMAL(10,7) NULL,
  bemerkung TEXT NULL,
  tour_id INTEGER NULL,
  aktiv INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  FOREIGN KEY (tour_id) REFERENCES {tours} (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE UNIQUE INDEX {ux_hydrants_kennung} ON {hydrants} (kennung);
CREATE INDEX {ix_hydrants_tour} ON {hydrants} (tour_id);

CREATE TABLE {hydrant_images} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  hydrant_id INTEGER NOT NULL,
  datei VARCHAR(190) NOT NULL,
  beschriftung VARCHAR(255) NULL,
  sortierung INTEGER NOT NULL DEFAULT 0,
  user_id INTEGER NULL,
  created_at DATETIME NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'ok',
  quelle VARCHAR(10) NOT NULL DEFAULT 'app',
  outing_id INTEGER NULL,
  ersetzt_id INTEGER NULL,
  vorgeschlagen_von VARCHAR(255) NULL,
  veraltet_at DATETIME NULL,
  veraltet_von VARCHAR(255) NULL,
  marker_x DECIMAL(6,5) NULL,
  marker_y DECIMAL(6,5) NULL,
  marker_rx DECIMAL(6,5) NULL,
  marker_ry DECIMAL(6,5) NULL,
  FOREIGN KEY (hydrant_id) REFERENCES {hydrants} (id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX {ix_himages_hydrant} ON {hydrant_images} (hydrant_id);

-- Tourantritt: Teilnehmer einer Tour im Pruefdurchgang
CREATE TABLE {tour_outings} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  tour_id INTEGER NOT NULL,
  campaign_id INTEGER NULL,
  teilnehmer VARCHAR(255) NOT NULL,
  started_at DATETIME NOT NULL,
  FOREIGN KEY (tour_id) REFERENCES {tours} (id) ON DELETE CASCADE,
  FOREIGN KEY (campaign_id) REFERENCES {campaigns} (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {revisions} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  hydrant_id INTEGER NOT NULL,
  campaign_id INTEGER NULL,
  tour_id INTEGER NULL,
  outing_id INTEGER NULL,
  user_id INTEGER NULL,
  quelle VARCHAR(12) NOT NULL,
  ergebnis VARCHAR(10) NOT NULL,
  bemerkung TEXT NULL,
  revised_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (hydrant_id) REFERENCES {hydrants} (id),
  FOREIGN KEY (campaign_id) REFERENCES {campaigns} (id) ON DELETE SET NULL,
  FOREIGN KEY (tour_id) REFERENCES {tours} (id) ON DELETE SET NULL,
  FOREIGN KEY (outing_id) REFERENCES {tour_outings} (id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES {users} (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX {ix_revisions_hydrant} ON {revisions} (hydrant_id, revised_at);
CREATE INDEX {ix_revisions_campaign} ON {revisions} (campaign_id, hydrant_id);

CREATE TABLE {defect_types} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  sortierung INTEGER NOT NULL DEFAULT 0,
  aktiv INTEGER NOT NULL DEFAULT 1,
  gilt_fuer VARCHAR(80) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {revision_defects} (
  revision_id INTEGER NOT NULL,
  defect_type_id INTEGER NOT NULL,
  PRIMARY KEY (revision_id, defect_type_id),
  FOREIGN KEY (revision_id) REFERENCES {revisions} (id) ON DELETE CASCADE,
  FOREIGN KEY (defect_type_id) REFERENCES {defect_types} (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {revision_images} (
  id INTEGER PRIMARY KEY AUTO_INCREMENT,
  revision_id INTEGER NOT NULL,
  datei VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (revision_id) REFERENCES {revisions} (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX {ix_rimages_revision} ON {revision_images} (revision_id);

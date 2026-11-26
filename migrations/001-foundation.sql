CREATE TABLE IF NOT EXISTS local_identity (id TINYINT PRIMARY KEY, project VARCHAR(80) NOT NULL, database_name VARCHAR(64) NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(20) PRIMARY KEY, checksum CHAR(64) NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS seed_metadata (id TINYINT PRIMARY KEY, version VARCHAR(20) NOT NULL, teams INT NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS dinas (id BIGINT PRIMARY KEY, name VARCHAR(100) NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS categories (id BIGINT PRIMARY KEY, name VARCHAR(100) NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS users (
 id BIGINT PRIMARY KEY, username VARCHAR(100) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
 password_scheme ENUM('modern','legacy_sha256') NOT NULL, password_version INT NOT NULL DEFAULT 1,
 active BOOLEAN NOT NULL DEFAULT TRUE, role ENUM('warga','admin','petugas') NOT NULL, dinas_id BIGINT NULL,
 FOREIGN KEY (dinas_id) REFERENCES dinas(id), CHECK ((role='petugas' AND dinas_id IS NOT NULL) OR (role IN ('warga','admin') AND dinas_id IS NULL))
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS reports (
 id BIGINT PRIMARY KEY, author_id BIGINT NOT NULL, title VARCHAR(200) NOT NULL, description TEXT NOT NULL,
 category_id BIGINT NOT NULL, location_text VARCHAR(255) NOT NULL, dinas_id BIGINT NULL,
 status ENUM('baru','ditugaskan','diproses','selesai','ditolak') NOT NULL DEFAULT 'baru', created_at DATETIME NOT NULL,
 FOREIGN KEY(author_id) REFERENCES users(id), FOREIGN KEY(category_id) REFERENCES categories(id), FOREIGN KEY(dinas_id) REFERENCES dinas(id),
 CHECK ((status IN ('baru','ditolak') AND dinas_id IS NULL) OR (status IN ('ditugaskan','diproses','selesai') AND dinas_id IS NOT NULL))
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS report_history (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, report_id BIGINT NOT NULL, actor_id BIGINT NOT NULL,
 from_status ENUM('baru','ditugaskan','diproses','selesai','ditolak') NULL,
 to_status ENUM('baru','ditugaskan','diproses','selesai','ditolak') NOT NULL,
 note TEXT NOT NULL, visibility ENUM('warga','internal') NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(report_id) REFERENCES reports(id), FOREIGN KEY(actor_id) REFERENCES users(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS attachments (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, report_id BIGINT NOT NULL, storage_key VARCHAR(100) NOT NULL UNIQUE,
 mime_type ENUM('image/jpeg','image/png') NOT NULL, size_bytes INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL,
 FOREIGN KEY(report_id) REFERENCES reports(id), CHECK(size_bytes BETWEEN 1 AND 2097152)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS rate_limit_state (
 identity_key CHAR(64) PRIMARY KEY, failures INT UNSIGNED NOT NULL, window_started_at DATETIME NOT NULL, expires_at DATETIME NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS login_audit (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, request_id CHAR(36) NOT NULL, outcome ENUM('success','denied','error') NOT NULL, created_at DATETIME NOT NULL
) ENGINE=InnoDB;

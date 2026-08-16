CREATE TABLE IF NOT EXISTS users (
    u_id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS keywords (
    k_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    name TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS positions (
    p_id INTEGER PRIMARY KEY AUTOINCREMENT,
    keyword_id INTEGER,
    position INTEGER CHECK(position BETWEEN 1 AND 100),
    date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(`keyword_id`, `date`)
);

CREATE INDEX IF NOT EXISTS `idx_positions_keyword_id` ON `positions`(`keyword_id`);
CREATE INDEX IF NOT EXISTS `idx_positions_date` ON `positions`(`date`);

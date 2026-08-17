CREATE TABLE IF NOT EXISTS users (
    u_id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS projects (
    project_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    domain TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(u_id)
);

CREATE TABLE IF NOT EXISTS keywords (
    k_id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(project_id)
);

CREATE TABLE IF NOT EXISTS positions (
    p_id INTEGER PRIMARY KEY AUTOINCREMENT,
    keyword_id INTEGER,
    position INTEGER CHECK(position BETWEEN 1 AND 100),
    date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(`keyword_id`, `date`)
);

CREATE INDEX IF NOT EXISTS `idx_projects_user_id` ON `projects`(`user_id`);
CREATE INDEX IF NOT EXISTS `idx_projects_status` ON `projects`(`status`);
CREATE INDEX IF NOT EXISTS `idx_keywords_project_id` ON `keywords`(`project_id`);
CREATE INDEX IF NOT EXISTS `idx_positions_keyword_id` ON `positions`(`keyword_id`);
CREATE INDEX IF NOT EXISTS `idx_positions_date` ON `positions`(`date`);

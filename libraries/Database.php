<?php

class Database
{
    private PDO $pdo;

    public function __construct()
    {
        $isNewDb = !file_exists(DB_PATH);

        $dsn = DB_DRIVER . ':' . DB_PATH;

        $this->pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);


        if ($isNewDb) {
            $this->initSchema();
        } else {
            $this->migrate();
        }
    }

    private function initSchema(): void
    {
        $schema = file_get_contents(__DIR__ . '/../schema.sql');
        $this->pdo->exec($schema);
    }

    /**
     * Migrate existing databases to the latest schema.
     */
    private function migrate(): void
    {
        // Add projects table if it doesn't exist
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS `projects` (
                `project_id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `user_id` INTEGER NOT NULL,
                `name` TEXT NOT NULL,
                `domain` TEXT NOT NULL DEFAULT \'\',
                `status` TEXT NOT NULL DEFAULT \'active\',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`u_id`)
            )
        ');

        // Check if keywords table still has user_id column (old schema)
        $stmt = $this->pdo->query("PRAGMA table_info(`keywords`)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasUserId = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'user_id') {
                $hasUserId = true;
                break;
            }
        }

        if ($hasUserId) {
            // Create new keywords table with project_id
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS `keywords_new` (
                    `k_id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `project_id` INTEGER NOT NULL,
                    `name` TEXT NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (`project_id`) REFERENCES `projects`(`project_id`)
                )
            ');

            // Migrate existing data: create a default project per user, then move keywords
            $users = $this->fetchAll('SELECT DISTINCT `user_id` FROM `keywords` WHERE `user_id` IS NOT NULL');
            foreach ($users as $user) {
                // Create a default project for each user
                $defaultProjectId = $this->insert('projects', [
                    'user_id' => $user['user_id'],
                    'name' => 'Default Project',
                    'domain' => '',
                    'status' => 'active',
                ]);

                // Move keywords to the default project
                $this->query(
                    'INSERT INTO `keywords_new` (`k_id`, `project_id`, `name`, `created_at`)
                     SELECT `k_id`, ?, `name`, `created_at` FROM `keywords` WHERE `user_id` = ?',
                    [$defaultProjectId, $user['user_id']]
                );
            }

            // Drop old table and rename new one
            $this->pdo->exec('DROP TABLE IF EXISTS `keywords`');
            $this->pdo->exec('ALTER TABLE `keywords_new` RENAME TO `keywords`');
        }

        // Add indexes
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS `idx_projects_user_id` ON `projects`(`user_id`)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS `idx_projects_status` ON `projects`(`status`)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS `idx_keywords_project_id` ON `keywords`(`project_id`)');
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys($data)));
        $sql = "UPDATE `$table` SET $set WHERE $where";
        $stmt = $this->query($sql, array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM `$table` WHERE $where";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
}

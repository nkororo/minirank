<?php

/**
 * Database abstraction layer.
 *
 * Provides a thin wrapper around PDO with convenience methods for
 * querying, inserting, updating, and deleting rows. Handles schema
 * initialization and migration for SQLite databases.
 */
class Database
{
    private PDO $pdo;

    /**
     * Connect to the database and run schema migration if needed.
     */
    public function __construct()
    {
        $isNewDb = !file_exists(DB_PATH);

        $dsn = DB_DRIVER . ':' . DB_PATH;

        $this->pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        /* Enable foreign key enforcement (SQLite has it off by default) */
        $this->pdo->exec('PRAGMA foreign_keys = ON');


        if ($isNewDb) {
            $this->initSchema();
        } else {
            $this->migrate();
        }
    }

    /**
     * Create all tables and indexes from schema.sql on a fresh database.
     */
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

        // Add updated_at column to projects if missing
        $this->addColumnIfNotExists('projects', 'updated_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');

        // Add updated_at column to keywords if missing
        $this->addColumnIfNotExists('keywords', 'updated_at', 'DATETIME DEFAULT CURRENT_TIMESTAMP');

        // Deduplicate positions: keep only the latest row per (keyword_id, date)
        $this->pdo->exec('
            DELETE FROM `positions` WHERE `p_id` NOT IN (
                SELECT MAX(`p_id`) FROM `positions` GROUP BY `keyword_id`, `date`
            )
        ');

        // Add UNIQUE constraint on (keyword_id, date) if missing
        $this->addUniqueIndexIfNotExists('positions', 'idx_positions_keyword_date', ['keyword_id', 'date']);
    }

    /**
     * Add a column to a table if it does not already exist.
     */
    private function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        $stmt = $this->pdo->query("PRAGMA table_info(`$table`)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            if ($col['name'] === $column) {
                return;
            }
        }
        $this->pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }

    /**
     * Add a UNIQUE index on the given columns if it does not already exist.
     */
    private function addUniqueIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        $quotedColumns = array_map(fn($col) => "`$col`", $columns);
        $columnList = implode(', ', $quotedColumns);

        // Check if the index already exists
        $stmt = $this->pdo->prepare('SELECT `name` FROM `sqlite_master` WHERE `type` = ? AND `name` = ?');
        $stmt->execute(['index', $indexName]);
        if ($stmt->fetch()) {
            return;
        }

        $this->pdo->exec("CREATE UNIQUE INDEX `$indexName` ON `$table` ($columnList)");
    }

    /**
     * Get the underlying PDO instance for advanced operations (transactions, etc.).
     *
     * @return PDO The raw PDO connection.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a prepared SQL statement and return the PDOStatement.
     *
     * @param string $sql    The SQL query with named or positional placeholders.
     * @param array  $params Bound parameter values.
     * @return PDOStatement The executed statement.
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row from the database.
     *
     * @param string $sql    The SQL query.
     * @param array  $params Bound parameter values.
     * @return ?array The first row as an associative array, or null if not found.
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Fetch all rows matching the given query.
     *
     * @param string $sql    The SQL query.
     * @param array  $params Bound parameter values.
     * @return array Array of associative arrays.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Insert a row into the specified table.
     *
     * @param string $table The target table name.
     * @param array  $data  Column-value pairs to insert.
     * @return int The last insert ID (auto-increment primary key).
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update rows matching the WHERE clause.
     *
     * @param string $table       The target table name.
     * @param array  $data        Column-value pairs to set.
     * @param string $where       The WHERE clause with placeholders.
     * @param array  $whereParams Bound parameter values for the WHERE clause.
     * @return int Number of affected rows.
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($col) => "`$col` = ?", array_keys($data)));
        $sql = "UPDATE `$table` SET $set WHERE $where";
        $stmt = $this->query($sql, array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    /**
     * Delete rows matching the WHERE clause.
     *
     * @param string $table  The target table name.
     * @param string $where  The WHERE clause with placeholders.
     * @param array  $params Bound parameter values for the WHERE clause.
     * @return int Number of deleted rows.
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM `$table` WHERE $where";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
}

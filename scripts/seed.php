<?php

/**
 * CLI Seeder Script
 *
 * Inserts demo keywords (if not present) and generates 30 days of daily positions
 * (from T-29 up to today) for each keyword with ranks between 1 and 100.
 *
 * Usage: php scripts/seed.php [project_id]
 *
 * If no project_id is provided, the script looks for the first active project.
 * If no projects exist, a default project is created for user_id 1.
 */

/* Bypass init.php session/auth check in CLI by using a public op */
$_GET['op'] = 'login';

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../libraries/seeder.php';

/* Avoid SQLite 'database is locked' when web requests run concurrently */
$db->getPdo()->exec('PRAGMA busy_timeout = 5000');

/* Resolve project_id from CLI argument or find/create one */
$projectId = (int) ($argv[1] ?? 0);

if ($projectId <= 0) {
    /* Try to find the first active project */
    $project = $db->fetchOne(
        'SELECT `project_id` FROM `projects` WHERE `status` = ? ORDER BY `project_id` ASC LIMIT 1',
        ['active']
    );

    if ($project) {
        $projectId = (int) $project['project_id'];
    } else {
        /* Create a default project for user_id 1 */
        $userId = 1;

        /* Ensure user exists */
        $user = $db->fetchOne('SELECT `u_id` FROM `users` WHERE `u_id` = ?', [$userId]);
        if (!$user) {
            $db->insert('users', [
                'email' => 'admin@minirank.local',
                'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            ]);
            $userId = (int) $db->getPdo()->lastInsertId();
        }

        $projectId = $db->insert('projects', [
            'user_id' => $userId,
            'name' => 'Demo Project',
            'domain' => 'example.com',
            'status' => 'active',
        ]);

        echo "Created default project (ID: {$projectId}) for user {$userId}\n";
    }
}

/* Verify project exists */
$project = $db->fetchOne(
    'SELECT `project_id`, `name` FROM `projects` WHERE `project_id` = ?',
    [$projectId]
);

if (!$project) {
    fwrite(STDERR, "Error: Project {$projectId} not found.\n");
    exit(1);
}

echo "Seeding project: {$project['name']} (ID: {$projectId})\n";

/* Execute shared seeder logic */
$result = seedProjectHistory($projectId);

if ($result['keywords_inserted'] > 0) {
    echo "Inserted {$result['keywords_inserted']} demo keywords.\n";
} else {
    echo "Project already has keywords, skipping keyword insertion.\n";
}

echo "Generated {$result['records_generated']} position records for {$result['keywords_count']} keywords.\n";
echo "Seeding complete.\n";

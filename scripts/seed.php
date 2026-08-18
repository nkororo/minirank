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

require_once __DIR__ . '/../init.php';

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

/* Demo keywords to insert if none exist */
$demoKeywords = [
    'seo tools',
    'rank tracker',
    'best coffee machine',
    'online shop',
    'auto repair',
    'digital marketing',
    'web hosting',
    'keyword research',
    'analytics dashboard',
    'local seo',
];

/* Check existing keywords for this project */
$existing = $db->fetchAll(
    'SELECT `k_id` FROM `keywords` WHERE `project_id` = ?',
    [$projectId]
);

if (empty($existing)) {
    /* Insert demo keywords */
    foreach ($demoKeywords as $name) {
        $db->insert('keywords', [
            'project_id' => $projectId,
            'name' => $name,
        ]);
    }
    echo "Inserted " . count($demoKeywords) . " demo keywords.\n";
} else {
    echo "Project already has " . count($existing) . " keywords, skipping keyword insertion.\n";
}

/* Generate 30-day position history */
$result = generatePositionHistory($projectId);

echo "Generated {$result['records_generated']} position records for {$result['keywords_count']} keywords.\n";
echo "Seeding complete.\n";

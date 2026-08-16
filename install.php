<?php

require_once __DIR__ . '/init.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!file_exists(DB_PATH)) {
        try {
            $dsn = DB_DRIVER . ':dbname=' . DB_PATH;
            $tempDb = new Database($dsn);
            $schema = file_get_contents(__DIR__ . '/schema.sql');
            $tempDb->getPdo()->exec($schema);
            $message = 'Database installed successfully!';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    } else {
        $message = 'Database already exists.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="max-w-lg mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">Install</h1>
    <?php if ($message): ?>
        <p class="mb-4 p-3 bg-blue-100 text-blue-800 rounded"><?php echo sanitize($message); ?></p>
    <?php endif; ?>
    <form method="POST">
        <button type="submit"
            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Install Database</button>
    </form>
    <p class="text-sm text-gray-500 mt-4">This will create the SQLite database and run schema.sql.</p>
</div>
<?php
require_once __DIR__ . '/includes/footer.php';

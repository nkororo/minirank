<?php

/**
 * Application constants.
 *
 * All environment-independent constants are defined here using uppercase names.
 * Environment-specific values (secrets, paths) should be defined in .env.
 */

define('APP_NAME', 'MiniRank');
define('APP_URL', 'http://localhost:8000');

define('DB_DRIVER', 'sqlite');
define('DB_PATH', __DIR__ . '/database.sqlite');

define('POSITION_MIN', 1);
define('POSITION_MAX', 100);

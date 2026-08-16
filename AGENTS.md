# MiniRank

## Project

PHP project for keyword position tracker on simulated data.

## Tech Stack

- Backend: Plain PHP 8.2.12
- Database: SQLite with PDO
- Frontend: HTML5, Tailwind CSS, Vanilla JavaScript

## Rules

- Always use PDO prepared statements with parameter binding for SQL queries. Never concatenare variable intro SQL strings.
- Escape all dynamic user output using `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` to prevent XSS.
- No secrets or credentials committed to the repository.
- Keep code minimal, modular, and easy to read.
- Use '' for PHP and "" for JS.
- In SQL statements use `column_name/table_name` for columns name and table name. 
- `init.php` must be included in all php files.
- All general functions must be deined in `Functions.php`.
- A class must have its own .php file in `libraries/`.
- All constant variables need to be defined in `parameters.php` with uppercase. Example: `define('CONSTANT_NAME', $value)`.
- SQLite database files (`database.sqlite`, `*.db`) and `.env` are gitignored
- Apply DRY principle. If something repeats make it a function.
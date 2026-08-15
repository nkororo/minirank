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
- Group database connection in `db.php`
- SQLite database files (`database.sqlite`, `*.db`) and `.env` are gitignored


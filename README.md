# MiniRank — SEO Rank Tracker

A lightweight, zero-dependency SEO Rank Tracker built with **Vanilla PHP**, **SQLite (PDO)**, **Vanilla JS**, and **CSS**.

---

## Features

* **Multi-Project Management:** Create, archive, and manage multiple domains per user account.
* **SEO Keyword Tracking:** Track positions (1–100) with automatic 7-day trend indicators (*Improved*, *Declined*, *Stable*).
* **30-Day History Seeder (M2):** Generate 10 demo keywords and 30 days of randomized daily rankings via CLI (`scripts/seed.php`) or UI.
* **Live Position Simulation (M3):** Real-time server-side ranking updates via AJAX without page reloads.

---
## Prerequisites

* **PHP 8.0+**
* **PDO SQLite Extension** enabled in `php.ini` (`pdo_sqlite`, `sqlite3`)

---

## Quick Start

### 1. Setup Database & Seed Demo Data
Run the all-in-one setup script to initialize the SQLite database, apply `schema.sql`, create the default user and project, and seed 30 days of position history:

```bash
php scripts/seed.php
```

### 2. Start the Local Server

```
php -S localhost:8000
```

### 3. Log In

Open `http://localhost:8000` in your web browser. Email: `admin@minirank.local`


## Project Structure

```text
MiniRank/
├── ajax/                   # Asynchronous API handlers
├── assets/                 # Frontend client assets
├── includes/               # Shared templates (header, footer)
├── libraries/              # Core PHP modules (Auth, Dashboard, Database, Keyword, Position, Project, seeder)
├── scripts/
│   └── seed.php            # CLI setup & 30-day history seeder
├── .gitignore              # Git ignore rules
├── AGENTS.md               # Agent guidelines & specification rules
├── arrays.php              # Array helper functions
├── Functions.php           # Global helper utilities
├── index.php               # Auth gateway (Login / Register)
├── init.php                # App bootstrapper & session handler
├── parameters.php          # Global application parameters
├── process.html            # Process documentation & AI retrospective
├── README.md               # Project documentation
└── schema.sql              # Database schema definitions
```

# 🃏 GameMGT — Casino Game Management System

A production-ready, full-stack **Casino Game Management System** built with **Laravel 12**. It manages casino game tables, game configurations, payout rules, chip presets, financial floats, ledger transactions, game history, reporting, and role-based access control — all through a web dashboard and a versioned REST API.

---

## 📋 Table of Contents

- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [System Requirements](#-system-requirements)
- [Installation Guide](#-installation-guide)
  - [Option A — XAMPP (Recommended for Local)](#option-a--xampp-recommended-for-local)
  - [Option B — Laravel Dev Server](#option-b--laravel-dev-server-artisan-serve)
- [Environment Configuration](#-environment-configuration)
- [Running the Application](#-running-the-application)
- [Default Login Credentials](#-default-login-credentials)
- [Seeded Data](#-seeded-data)
- [API Reference](#-api-reference)
- [Running Tests](#-running-tests)
- [Project Structure](#-project-structure)
- [Troubleshooting](#-troubleshooting)

---

## ✨ Features

- **Game Table Management** — Create, configure, activate/deactivate game tables with per-table presets
- **7 Game Types** — Baccarat, Andar Bahar, Dragon Tiger, 3 Card Poker, Blackjack, Mini Flush, Casino War
- **Game Presets** — Per-game configuration (min/max bets, commissions, side bets, rules)
- **Chip Presets** — Define chip denominations linked to game table configurations
- **Payout Rules** — Configurable payout multipliers per game type with jackpot support
- **Shoe Types** — Track physical card shoe hardware (Angel, Bee, Safeshoe, etc.)
- **Financial Engine**
  - **Table Float** — Open/close cash float sessions per table per game day
  - **Table Ledger** — Full transaction lifecycle: BUYIN, CASHOUT, FILL, CREDIT with pending → claimed → completed status workflow
  - **Idempotency** — Duplicate-transaction protection via `Idempotency-Key` header
- **Game History** — Per-game round history recording via API
- **Live Dashboard** — Auto-polling dashboard showing all open tables, float summaries, and ledger totals
- **Reports** — Ledger, Float, GameDay, and Table reports with date/table filters and CSV export
- **Role-Based Access Control (RBAC)** — Custom roles and permissions, applied per route
- **User Management** — Create users with roles, card IDs, activate/deactivate
- **REST API** — Versioned API (`/api/v1/`) with Sanctum token authentication
- **MAC Address Binding** — Bind/unbind physical terminals to game tables
- **Themes** — UI theme management
- **Utilities** — Database reset tools for development/testing

---

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| **Framework** | Laravel 12.0 |
| **Language** | PHP 8.2+ |
| **Database** | MySQL / MariaDB (Eloquent ORM) |
| **Auth** | Laravel Breeze (Web Sessions) + Laravel Sanctum (API) |
| **Frontend** | Blade Templates, Bootstrap 5, Alpine.js, Axios |
| **Build Tool** | Vite 7 |
| **Testing** | Pest PHP 3 |
| **Code Style** | Laravel Pint (PSR-12) |
| **API Docs** | Postman |

---

## ⚙️ System Requirements

Before you begin, make sure the following are installed on your machine:

| Requirement | Minimum Version | Notes |
|---|---|---|
| **PHP** | 8.2 | Extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo` |
| **Composer** | 2.x | [getcomposer.org](https://getcomposer.org) |
| **Node.js** | 18.x LTS or higher | [nodejs.org](https://nodejs.org) |
| **npm** | 9.x or higher | Bundled with Node.js |
| **MySQL / MariaDB** | 8.0 / 10.6+ | Via XAMPP, Laragon, or standalone |
| **Git** | Any | [git-scm.com](https://git-scm.com) |

> **Windows Users:** [XAMPP](https://www.apachefriends.org/) is the easiest way to get PHP + MySQL running locally.

---

## 🚀 Installation Guide

### Option A — XAMPP (Recommended for Local)

This method runs the app under Apache via XAMPP, which is ideal for Windows development.

#### Step 1 — Install XAMPP

Download and install XAMPP from [apachefriends.org](https://www.apachefriends.org/).
Start **Apache** and **MySQL** from the XAMPP Control Panel.

#### Step 2 — Clone the Repository

```bash
cd C:\xampp\htdocs
git clone https://github.com/iaxivanazim/gamemgt.git
cd gamemgt
```

#### Step 3 — Install PHP Dependencies

```bash
composer install
```

#### Step 4 — Install Node.js Dependencies

```bash
npm install
```

#### Step 5 — Create the Environment File

```bash
# Linux / Mac
cp .env.example .env

# Windows (Command Prompt)
copy .env.example .env
```

#### Step 6 — Configure the Database

Open **phpMyAdmin** at `http://localhost/phpmyadmin` and create a new database:

```sql
CREATE DATABASE gamemgt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then open `.env` and update the database block:

```env
APP_NAME="XTable"
APP_URL=http://localhost/gamemgt/public

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gamemgt
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

> **Note:** XAMPP's default MySQL username is `root` with no password. Adjust if yours differs.

#### Step 7 — Generate Application Key

```bash
php artisan key:generate
```

#### Step 8 — Run Migrations and Seeders

```bash
php artisan migrate --seed
```

This creates all 30 database tables and seeds: roles, users, permissions, game types, payout rules, and shoe types.

#### Step 9 — Build Frontend Assets

```bash
# Development (with hot reload via separate terminal)
npm run dev

# OR production build (compiled, no dev server needed)
npm run build
```

#### Step 10 — Access the Application

Open your browser and visit:

```
http://localhost/gamemgt/public
```

---

### Option B — Laravel Dev Server (`artisan serve`)

Use this if you prefer the built-in PHP development server without XAMPP.

#### Step 1 — Clone the Repository

```bash
git clone https://github.com/iaxivanazim/gamemgt.git
cd gamemgt
```

#### Step 2 — Install Dependencies

```bash
composer install
npm install
```

#### Step 3 — Environment Setup

```bash
cp .env.example .env
```

Update `.env`:

```env
APP_NAME="XTable"
APP_URL=http://localhost:9000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gamemgt
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

#### Step 4 — Generate Key, Migrate, Seed

```bash
php artisan key:generate
php artisan migrate --seed
```

#### Step 5 — Start All Services (Single Command)

```bash
composer dev
```

This runs all 4 services concurrently:

| Service | Command | URL |
|---|---|---|
| App Server | `php artisan serve` | http://localhost:8000 |
| Vite HMR | `npm run dev` | (hot reload) |
| Queue Worker | `php artisan queue:listen` | (background) |
| Log Viewer | `php artisan pail` | (terminal) |

Or run each in a separate terminal:

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev

# Terminal 3 (optional)
php artisan queue:work
```

#### Step 6 — Access the Application

```
http://localhost:9000
```

---

## 🔧 Environment Configuration

Key `.env` variables explained:

```env
# Application
APP_NAME="XTable"
APP_ENV=local           # Change to 'production' on live server
APP_DEBUG=true          # Set to false in production
APP_URL=http://localhost/gamemgt/public

# Database (MySQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gamemgt
DB_USERNAME=root
DB_PASSWORD=

# Sessions & Cache
# Use 'database' for local, 'redis' for production
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Mail
# 'log' driver writes emails to storage/logs/laravel.log (local dev)
MAIL_MAILER=log
```

---

## ▶️ Running the Application

### Development Mode

```bash
composer dev
```

### Production Optimisation

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### Useful Artisan Commands

```bash
# Clear all caches
php artisan optimize:clear

# Fresh migration with seeds (WARNING: destroys all data)
php artisan migrate:fresh --seed

# Run seeders only (without re-migrating)
php artisan db:seed

# List all routes
php artisan route:list

# Format code with Laravel Pint
./vendor/bin/pint
```

---

## 🔐 Default Login Credentials

After running `php artisan migrate --seed`, the following admin account is created:

| Field | Value |
|---|---|
| **Username** | `admin` |
| **Password** | `admin@123` |
| **Card ID** | `ADMIN001` |
| **Role** | Admin (full access) |

> ⚠️ **Change the default password immediately** after your first login in any non-development environment.

---

## 🌱 Seeded Data

The following reference data is inserted automatically via `php artisan migrate --seed`:

### Roles

| Role | Slug |
|---|---|
| Admin | `admin` |

### Game Types

| Name | Code | Description |
|---|---|---|
| Baccarat | `BAC` | Bet on Player, Banker, or Tie |
| Andar Bahar | `AB` | Indian card game — Andar or Bahar side |
| Dragon Tiger | `DT` | Dragon vs Tiger high-card comparison |
| 3 Card Poker | `3CP` | Three-card poker variant against dealer |
| Blackjack | `BJ` | Beat the dealer to 21 |
| Mini Flush | `MF` | Flush-based card game |
| Casino War | `CW` | Simple high-card battle with war option |

### Shoe Types

`Angel` · `Bee` · `Safeshoe` · `Eshoe` · `LT` · `Ideal`

### Permissions

| Module | Permissions |
|---|---|
| Users | `view-users`, `create-users`, `edit-users`, `deactivate-users` |
| Roles | `view-roles`, `create-roles`, `edit-roles`, `delete-roles`, `assign-permissions` |
| Game Tables | `create-game_tables` |
| Chips | `view-chips`, `create-chips`, `edit-chips`, `delete-chips` |
| History | `view-history` |
| Ledger | `view-ledger` |
| Utilities | `manage-resets` |

---

## 📡 API Reference

All API endpoints are prefixed with `/api/v1/`. No auth token is required for local development by default.

Full interactive documentation available on Postman:
🔗 **[View API Docs on Postman](https://documenter.getpostman.com/view/31035377/2sBXcEmMLA)**

### Endpoints Overview

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/game-tables` | List all game tables |
| `GET` | `/api/v1/game-tables/active` | List active game tables |
| `GET` | `/api/v1/game-tables/{id}` | Get single table |
| `GET` | `/api/v1/game-tables/{id}/configuration` | Full table config (preset, chips, payouts) |
| `GET` | `/api/v1/game-tables/by-mac/{mac}` | Find table by MAC address |
| `POST` | `/api/v1/game-tables/{id}/register-mac` | Bind MAC to table |
| `GET` | `/api/v1/game-tables/{id}/float` | Current float for table |
| `GET` | `/api/v1/game-tables/{id}/bet-index` | Get current bet index |
| `POST` | `/api/v1/game-tables/{id}/bet-index` | Update bet index |
| `POST` | `/api/v1/tables/{id}/open` | Open float session |
| `POST` | `/api/v1/tables/{id}/close` | Close float session |
| `GET` | `/api/v1/tables/{id}/session` | Current session info |
| `GET` | `/api/v1/tables/{id}/history` | Float history |
| `POST` | `/api/v1/ledger/txn` | Create ledger transaction *(idempotent)* |
| `GET` | `/api/v1/ledger/table/{table_id}` | Transactions by table |
| `GET` | `/api/v1/ledger/table/{table_id}/summary` | Ledger summary |
| `GET` | `/api/v1/ledger/tab/{tab_id}` | Transactions by tab |
| `GET` | `/api/v1/ledger/txn/{txn_id}` | Single transaction |
| `POST` | `/api/v1/ledger/txn/{txn_id}/claim` | Claim transaction |
| `POST` | `/api/v1/ledger/txn/{txn_id}/complete` | Complete transaction |
| `GET` | `/api/v1/ledger/pending` | All pending transactions |
| `POST` | `/api/v1/history/{game}` | Record game round |
| `GET` | `/api/v1/history/{game}/table/{id}` | History by table |
| `GET` | `/api/v1/history/{game}/tab/{tabId}` | History by tab |
| `GET` | `/api/v1/history/{game}/{recordId}` | Single history record |
| `GET` | `/api/v1/game-day/current` | Current game day |
| `POST` | `/api/v1/game-day/start` | Start game day |
| `POST` | `/api/v1/game-day/close` | Close game day |
| `GET` | `/api/v1/payout-rules/game-type/{id}` | Payout rules by game type |
| `GET` | `/api/v1/game-types` | All game types |
| `GET` | `/api/v1/users` | All users |

### Idempotency (Ledger Transactions)

To prevent duplicate ledger writes, send a unique UUID with every `POST /api/v1/ledger/txn`:

```http
POST /api/v1/ledger/txn
Content-Type: application/json
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000

{ ... }
```

Response header will indicate:
- `Idempotency-Key-Status: original` — first request processed
- `Idempotency-Key-Status: replayed` — duplicate returned from cache
- `Idempotency-Key-Status: key-mismatch` — same key, different body (rejected)

---

## 🧪 Running Tests

Tests are written with **Pest PHP**.

```bash
# Run all tests
php artisan test

# Or use Pest directly
./vendor/bin/pest

# Run a specific file
./vendor/bin/pest tests/Feature/TableFloatControllerTest.php

# Run with code coverage (requires Xdebug or PCOV)
./vendor/bin/pest --coverage
```

Test locations:

```
tests/
├── Feature/
│   ├── Auth/                        # Auth flow tests
│   ├── ProfileTest.php
│   └── TableFloatControllerTest.php
└── Unit/
```

---

## 📁 Project Structure

```
gamemgt/
├── app/
│   ├── FormatsGameTable.php         # Trait: consistent API JSON formatting
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                # Breeze auth controllers
│   │   │   ├── DashboardController.php
│   │   │   ├── GameTableController.php
│   │   │   ├── TableFloatController.php
│   │   │   ├── TableLedgerController.php
│   │   │   ├── GameHistoryController.php
│   │   │   ├── ReportController.php
│   │   │   ├── RoleController.php
│   │   │   ├── UserController.php
│   │   │   └── ...
│   │   ├── Middleware/
│   │   │   ├── CheckPermission.php      # RBAC route guard
│   │   │   └── IdempotencyMiddleware.php # Duplicate request protection
│   │   └── Requests/
│   ├── Models/                      # 28 Eloquent models
│   │   ├── GameTable.php
│   │   ├── TableFloat.php
│   │   ├── TableLedger.php
│   │   ├── BaccaratPreset.php       # Per-game preset models
│   │   ├── BaccaratHistory.php      # Per-game history models
│   │   └── ...
│   ├── Rules/
│   │   └── PipeSeparatedNumbers.php # Custom validation rule
│   └── Services/
│       └── ResetService.php         # DB reset logic
├── database/
│   ├── migrations/                  # 30 migration files (Feb–Jul 2026)
│   └── seeders/                     # 8 seeders
├── resources/
│   ├── css/app.css
│   ├── js/app.js
│   └── views/
│       ├── dashboard.blade.php
│       ├── game_tables/             # index, create, edit
│       ├── ledger/
│       ├── reports/
│       ├── history/
│       ├── roles/
│       ├── users/
│       ├── chips/
│       ├── themes/
│       ├── layouts/
│       ├── components/
│       └── utilities/
├── routes/
│   ├── api.php                      # REST API routes (/api/v1/)
│   ├── web.php                      # Web dashboard routes (auth-gated)
│   └── auth.php                     # Auth routes (Breeze)
├── tests/
│   ├── Feature/
│   └── Unit/
├── composer.json
├── package.json
└── vite.config.js
```

---

## 🔍 Troubleshooting

### ❌ `php artisan migrate` fails — "Access denied for user 'root'"
Verify your `.env` `DB_*` credentials match your MySQL setup. For XAMPP, the default is username `root` with an empty password.

### ❌ Assets not loading — CSS/JS 404
Run `npm run build` (production) or `npm run dev` (development). Also verify `APP_URL` is correct in `.env`.

### ❌ "No application encryption key has been specified"
```bash
php artisan key:generate
```

### ❌ "419 Page Expired" on form submit
Ensure `SESSION_DRIVER=database` and run `php artisan migrate` so the `sessions` table exists.

### ❌ Composer install fails with PHP version error
This project requires **PHP 8.2+**. Check with `php -v`. On XAMPP, make sure the correct PHP version is active.

### ❌ White screen / 500 Server Error
Check `storage/logs/laravel.log` for the actual error message. Then clear caches:
```bash
php artisan optimize:clear
```

### ❌ Permission denied on `storage/` (Linux/Mac)
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### ❌ "Vite manifest not found"
You must build frontend assets before running the app without the dev server:
```bash
npm run build
```

### ❌ `composer dev` not working
Ensure `concurrently` is installed as a dev dependency:
```bash
npm install
```
Then retry `composer dev`.

---

## 📄 License

This project is proprietary software. All rights reserved.

---

## 👤 Author
Ivan Azim
Built with ❤️ using Laravel 12.
For questions or issues, open a GitHub Issue.

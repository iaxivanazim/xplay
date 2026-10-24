API docs
[Click here for API Docs](https://documenter.getpostman.com/view/31035377/2sBYHUTfiM)

# 🎰 XPLAY — Virtuals (ETG Casino Gaming System)

## -> End-to-End Operational Workflows (When & How to Use)

### Scenario A: Tablet First-Time Setup & Boot Sequence
1. Physical tablet boots Android OS.
2. Tablet reads Wi-Fi MAC address: `00:E0:4C:68:00:01`.
3. Calls `GET /api/v1/tabs/by-mac/00:E0:4C:68:00:01`.
4. If found, receives `tab_id = 1` and `table_name = "Amatic-1"`.
5. Calls `GET /api/v1/tabs/1/configuration` to render minimum bet ($1), maximum bet ($5000), and chip values.
6. Calls `GET /api/v1/tabs/1/status` to determine if locked or ready.
7. Initiates periodic background keepalive `POST /api/v1/tabs/1/heartbeat`.

---

### Scenario B: Player Buyin & Session Issuance at Cage
1. Player approaches Cashier desk, requests $500 on **Amatic-1**.
2. Cashier clicks **Amatic-1** on `/dashboard`.
3. Cashier enters `$500.00` and optional player name.
4. Client sends `POST /api/v1/ledger/txn` (`BUYIN`, amount: 500).
5. Server atomically:
   - Records BUYIN in ledger.
   - Generates 6-digit session OTP (`839214`).
   - Updates `tab_statuses.current_balance = 500.00`.
   - Unlocks the tab.
6. Player walks to terminal, types OTP `839214` to begin play.

---

### Scenario C: Live Bet Placement & Automated Resulting
1. Player touches screen to place $50 on Number 17 (Position 68) and $100 on Red (Position 150).
2. Tablet sends `POST /api/v1/tabs/1/bet` with `bets: [{position_index: 68, amount: 50}, {position_index: 150, amount: 100}]`.
3. Server validates balance ($500 >= $150), accepts bets, and updates balance to $350.
4. Physical roulette wheel spins. The ball drops into pocket **17**.
5. Station hardware reader sends `POST /api/v1/station/1/roulette/result`:
   `{ "game_no": "ROL-2026-002", "winning_number": 17 }`.
6. Server Resulting Engine:
   - Evaluates Position 68 (Straight 17) -> **WON $1,800.00** (payout 35:1).
   - Evaluates Position 150 (Red) -> **LOST** (17 is black).
   - Credits Tab #1: `$350 + $1,800 = $2,150.00`.
   - Saves record to `roulette_history`.
7. Tablet updates screen showing winning pocket #17 in gold and balance $2,150.00.

---

### Scenario D: Disaster Recovery (Power Outage / Wi-Fi Loss)
1. Tablet power cord unplugged or Wi-Fi drops. Tablet turns off.
2. Tablet turns back on 2 minutes later.
3. Tablet application invokes:
   `POST /api/v1/tabs/1/restore` with `{ "hardware_mac": "00:E0:4C:68:00:01" }`.
4. Server retrieves `tab_statuses` snapshot and sends back:
   - Active Session ID (`42`)
   - Session OTP (`839214`)
   - Exact Balance (`$2,150.00`)
   - Last round outcome (#17)
5. Tablet restores the player session immediately without requiring cage intervention.

---

### Scenario E: Player Cashout at Cage
1. Player decides to leave, returns to the Cashier.
2. Cashier clicks **Amatic-1** on `/dashboard`.
3. Drawer shows active balance `$2,150.00` and OTP `839214`.
4. Cashier confirms player's OTP, clicks **"Cashout"**.
5. System invokes `POST /dashboard/tab/1/cashout`.
6. Server records `CASHOUT` transaction in `table_ledgers`, completes `tab_sessions`, resets `tab_statuses.current_balance = 0.00`, and sets `lock_status = 'locked'`.
7. Tablet screen locks and shows "Thank you for playing".

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
git clone https://github.com/iaxivanazim/xplay.git
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
APP_NAME="XPlay"
APP_URL=http://localhost/gamemgt/public
SERVER_PORT=9001

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=xplay
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
git clone https://github.com/iaxivanazim/xplay.git
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
APP_NAME="XPlay"
APP_URL=http://localhost:9001

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
| App Server | `php artisan serve` | http://localhost:9001 |
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
http://localhost:9001
```

---

## 🔧 Environment Configuration

Key `.env` variables explained:

```env
# Application
APP_NAME="XTable"
APP_ENV=local           # Change to 'production' on live server
APP_DEBUG=true          # Set to false in production
APP_URL=http://localhost/xplay/public
SERVER_PORT=9001

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

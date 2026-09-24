# xplay — Virtuals Tab-Based Cage & Configuration System

> **Reference Document** — Living document. Updated as development progresses.
> Last Updated: 2026-09-18 | Phase 1 ✅ · Phase 2 ✅ · Phase 3 DB Layer ✅ · Phase 3 Controllers/Views in progress

---

## 1. Project Overview

**xplay** is a **cashier-facing cage system** for a casino's **Virtuals** (RNG/virtual-dealer) gaming floor. It handles the full lifecycle of a player's session at a virtual game terminal — from cash-in through gameplay to cash-out — and is the **authoritative source** for game results, payouts, ledger, and session state.

### 1.1 What xplay Does (System Purpose)

| Function | Description |
|---|---|
| **Cage — Cash In (Buyin)** | Cashier receives cash from player, credits the tab, generates OTP session key |
| **Cage — Cash Out (Cashout)** | Player ends session, cashier redeems chips, debits tab balance |
| **Tab Configuration** | Register tabs, bind MAC, assign game presets, chip sets, payout rules |
| **Result Calculation** | Game station sends raw cards; xplay calculates winner + payout amounts |
| **Ledger** | Immutable audit trail of every Buyin and Cashout transaction |
| **History** | Round-by-round game records with bet, outcome, and credit detail |
| **Reports** | Shift, daily, and per-tab financial and game summaries |
| **Session Recovery** | ⚠️ **TOP PRIORITY** — On any failure, current session is fully restored to the tab from the exact point it was interrupted |

### 1.2 What Are "Virtuals" in a Casino?

- **No live dealer** — outcomes driven by RNG or pre-recorded video.
- Players use physical **game stations (Tabs/Machines)** — hardware terminals MAC-bound to the system.
- The cage cashier manages all monetary flow. The terminal handles betting UI and card display.
- xplay is the **single source of truth** for balances, outcomes, and session state.

> ⚠️ **Note:** Some client requirements are still being defined. Migrations are intentionally **not dropped** to preserve revert safety.

---

## 2. Scope — Games

| Game | Status |
|---|---|
| **Andar Bahar** | ✅ Existing |
| **Baccarat** | ✅ Existing |
| **Roulette** | 🆕 To build (Phase 2) |
| Dragon Tiger / 3CP / BJ / MF / CW | ❌ Removed (Phase 1) |

---

## 3. Core Concepts

### 3.1 Two Distinct Hardware Entities

This system has **two separate physical/virtual hardware entities** that must not be confused:

| Entity | Called | Model | Role |
|---|---|---|---|
| **Game Station** | **Table** | `GameTable` → `Tab` (Phase 3) | Where cards are drawn. Has a middleware that sends card data to xplay. Identified by `table_id`. MAC-address bound. |
| **Player Betting Terminal** | **Tab** (player side) | (identified as `tab_id` string) | Where the player places bets and sees results. Multiple tabs can be connected to one game station. Identified by `tab_id` string (e.g. `"TAB-007"`). |

> **Critical distinction:** `table_id` = the card-drawing game station (FK in all history tables). `tab_id` = the individual player terminal (string, nullable, scoped per player session).

### 3.2 xplay as the Result Calculation Engine

**xplay is not just a config portal — it is the authoritative game result engine.**

The flow for every game round:

```
Game Station (Table)
  → Cards drawn by RNG / physical shuffle machine
  → Station middleware sends raw card data to xplay API:
      POST /api/v1/history/{game}
      {
        "table_id": 1,          ← which game station
        "tab_id": "TAB-007",    ← which player terminal
        "player_cards": ["Ah","Kd","3c"],
        "banker_cards": ["2h","9s"],
        ... raw card data only
      }
            ↓
  [xplay GameHistoryController]
    → Receives raw card data
    → Applies game rules (e.g. Baccarat: natural, third-card rule)
    → Determines winner (player / banker / tie)
    → Applies configured payout rules for this table
    → Calculates win_amount from bet_amount × payout_multiplier
    → Stores complete result in history table
    → Updates tab_statuses snapshot
    → Publishes result to Kafka → Tab terminals receive outcome
```

**xplay owns the truth of every round result.** The game station only sends card values — it never pre-calculates outcomes. This design means:
- Payout rules configured in the portal are the **single source of truth** for all payouts.
- Changing a payout rule in the portal immediately affects all future rounds.
- Game logic bugs are fixed in one place (xplay), not across every terminal.

### 3.3 Table ID via Middleware (X-Table-ID)

Every API request from a game station carries its identity via an HTTP header:

```
Game Station  →  POST /api/v1/history/baccarat
               Header: X-Table-ID: 1
                         ↓
              [ResolveTableMiddleware]
               Finds GameTable/Tab by ID or MAC
               Validates station is active + bound
               Attaches $request->gameTable
                         ↓
              Controller stamps table_id on the history record
```

- `tab_id` in the request body identifies the **player terminal** for that round.
- `table_id` is resolved from the middleware header, never trusted from body.
- This ensures a rogue station cannot impersonate another table.

---

## 4. Feature Specifications

### 4.1 Enable / Disable Tabs

- Tabs have a boolean `status` column (active / inactive).
- **Disabling** a tab prevents it from accepting new sessions / game rounds.
- Can be toggled from the Tab Config page by authorised users.
- A disabled tab's current open session should be gracefully flagged (not abruptly cut).
- Kafka event published on status change → tab terminal receives update instantly.

### 4.2 Ledger — Buyin & Cashout Only (Cage Transactions)

The ledger in xplay is **simplified** compared to gamemgt. Only **two transaction types** exist:

| Transaction | Code | Description |
|---|---|---|
| **Buyin** | `BUYIN` | Player purchases chips at the cage. Cash goes into cage, chips added to tab balance. |
| **Cashout** | `CASHOUT` | Player redeems chips at the cage. Chips deducted from tab balance, cash paid out from cage. |

**Key design points:**
- Transactions go through the **cage**, not the table float. There is no table float open/close cycle in xplay.
- `FILL`, `CREDIT`, `DROP`, `ADJUST`, `PAYOUT`, `VOID`, `BET`, `LOSS` from gamemgt are **not applicable**.
- `tab_balance` is maintained per transaction (running balance on the tab).
- `float_balance` column may be repurposed or dropped (to be confirmed).
- The `TableFloat` / `TableFloatController` concept may be **removed or significantly stripped down**.
- Each Buyin creates a session OTP (see §4.4).

**Ledger record structure (proposed):**
```
tab_ledger / table_ledger (renamed)
  txn_id       PK
  tab_id       FK → tabs.id
  txn_type     ENUM('BUYIN', 'CASHOUT')
  amount       DECIMAL
  tab_balance  DECIMAL   ← running balance after this txn
  gameday      DATE
  otp          STRING    ← OTP associated with this session
  reference    STRING    ← cage reference / receipt number
  initiated_by STRING    ← cashier user ID
  processed    TINYINT   ← 0=pending, 1=complete, 2=claimed
  created_at
```

### 4.3 Enabling / Disabling Tabs (Admin Control)

- Managed from the Tab Config index page.
- Disabled tabs cannot receive Buyins or start new sessions.
- Status changes are broadcast via Kafka.

### 4.4 Tab Locking / Unlocking + OTP Session

**Concept in a real casino:**
In virtual gaming, a player's session is tied to a unique passcode (OTP) that:
1. Is generated when a **Buyin** is processed at the cage.
2. Is given to the player along with their chips.
3. Must be entered at the Tab terminal to start playing (session activation).
4. Remains valid for the entire session until the player **Cashes Out**.
5. Prevents other players from using the same tab mid-session.

**Lock / Unlock:**
- A tab can be **locked** (e.g. on break, end of shift, malfunction).
- A locked tab displays a lockout screen and rejects incoming bets.
- Only an authorised user (cashier/supervisor) can unlock it via the portal.
- Unlock can optionally require supervisor OTP/PIN.

**Data model:**
```
Tab (tabs table)
  lock_status    ENUM('unlocked', 'locked', 'break')
  locked_at      TIMESTAMP nullable
  locked_by      FK → users.id nullable
```

**OTP Session model:**
```
tab_sessions
  id
  tab_id         FK → tabs.id
  otp            STRING(6-8)  ← generated at Buyin
  txn_id         FK → tab_ledger.txn_id (the Buyin txn)
  status         ENUM('active', 'completed', 'expired', 'voided')
  started_at     TIMESTAMP
  completed_at   TIMESTAMP nullable  ← set on Cashout
  player_ref     STRING nullable     ← optional player identifier
  buyin_amount   DECIMAL
  cashout_amount DECIMAL nullable
```

**Session Lifecycle:**
```
Cage: Process Buyin
  → Generate OTP (e.g. 6-digit random)
  → Create tab_session record (status: active)
  → Record Buyin in tab_ledger
  → Publish Kafka event: tab.session.start

Player: Enter OTP at terminal
  → Terminal verifies OTP via API
  → Tab unlocks for that session

Player: Plays rounds
  → Each round recorded in history table

Cage: Process Cashout
  → Verify OTP matches active session
  → Record Cashout in tab_ledger
  → Close tab_session (status: completed)
  → Publish Kafka event: tab.session.end
  → Tab locks / resets to idle
```

### 4.5 History API — Parameterised Fetch

Current `byTable` and `byTab` endpoints are extended. A new unified endpoint:

```
GET /api/v1/history/{game}

Query Parameters:
  tab_id      string   — filter by specific tab
  table_id    int      — filter by tab's table ID (legacy compat)
  date        date     — filter by game date (YYYY-MM-DD)
  from        datetime — range start
  to          datetime — range end
  winner      string   — filter by round winner
  game_no     string   — fetch a specific game/round number
  otp         string   — filter all rounds in a given session OTP
  limit       int      — number of rows to return (default: 50, max: 500)
  offset      int      — for pagination (alternative to page)
  page        int      — page number (used if offset not provided)
  per_page    int      — rows per page (default: 50)
  sort        string   — 'asc' | 'desc' (default: desc)
```

**Response envelope:**
```json
{
  "data": [...],
  "meta": {
    "total": 1200,
    "per_page": 50,
    "current_page": 1,
    "last_page": 24,
    "game_no": "G-0042",
    "prev_game_no": "G-0041",
    "next_game_no": "G-0043"
  }
}
```

### 4.6 Tab Current Status Table

A dedicated `tab_statuses` table acts as a **real-time snapshot / materialised view** of each tab's current state. It is:

- Updated on every significant event (session start/end, buyin, cashout, lock, game round).
- Used by the Tab terminal for **recovery** after data loss, network delay, or corruption.
- Used by the portal dashboard for instant status reads (no expensive joins).
- Think of it as a **last-known-good state** record.

**Schema:**
```
tab_statuses
  tab_id            FK → tabs.id (PK — one row per tab)
  status            ENUM('idle', 'active', 'locked', 'break', 'disabled')
  lock_status       ENUM('unlocked', 'locked', 'break')
  current_session_id FK → tab_sessions.id nullable
  current_otp       STRING nullable          ← active session OTP
  current_balance   DECIMAL nullable         ← current tab chip balance
  last_buyin_at     TIMESTAMP nullable
  last_cashout_at   TIMESTAMP nullable
  last_game_no      STRING nullable          ← last round played
  last_game_at      TIMESTAMP nullable
  last_synced_at    TIMESTAMP                ← when this row was last updated
  recovery_data     JSON nullable            ← serialised last state for terminal recovery
```

**API endpoint for terminal recovery:**
```
GET /api/v1/tabs/{id}/status   ← returns tab_statuses row for a tab
```

### 4.7 Kafka Integration

**What is Kafka?**
Apache Kafka is a distributed **event streaming platform** used for real-time, high-throughput, fault-tolerant messaging between systems. In xplay it bridges the **portal** (backend config/cashier system) and the **Tab terminals** (game clients).

**Why Kafka here?**
- Tab terminals need to receive configuration changes, session events, and lock/unlock commands **instantly**.
- The portal needs to receive game round results from terminals reliably.
- HTTP polling is too slow and unreliable for live casino environments.
- Kafka provides **guaranteed delivery**, **message ordering per partition**, and **replay** capability (important for recovery).

**How Kafka fits in xplay:**

```
┌─────────────────────────────────────────────────────────────┐
│                        KAFKA BROKER                          │
│                                                             │
│  Topics:                                                    │
│  ┌──────────────────┐   ┌──────────────────┐               │
│  │ portal.tab.events│   │ tab.game.events  │               │
│  │ (portal→terminal)│   │ (terminal→portal)│               │
│  └──────────────────┘   └──────────────────┘               │
└─────────────────────────────────────────────────────────────┘
         ▲  produce                   ▲  produce
         │  consume ▼                 │  consume ▼
┌─────────────┐                ┌─────────────────┐
│  xplay      │                │  Tab Terminals  │
│  Laravel    │  ◄────────────►│  (Game Clients) │
│  Backend    │                │                 │
└─────────────┘                └─────────────────┘
```

**Kafka Topics (Proposed):**

| Topic | Direction | Events |
|---|---|---|
| `portal.tab.config` | Portal → Terminal | Tab preset changed, payout rule update, chip set change |
| `portal.tab.status` | Portal → Terminal | Tab enabled/disabled, locked/unlocked, break mode |
| `portal.tab.session` | Portal → Terminal | Session OTP issued (Buyin), session closed (Cashout) |
| `tab.game.history` | Terminal → Portal | Each game round result (bets, outcomes, credits) |
| `tab.game.status` | Terminal → Portal | Terminal heartbeat, connectivity check, current state |

**Laravel Kafka Implementation:**

Package: `mateusjunges/laravel-kafka`

```bash
composer require mateusjunges/laravel-kafka
```

**Producer (Portal publishes events):**
```php
// When cashier processes a Buyin / issues OTP
use Junges\Kafka\Facades\Kafka;

Kafka::publishOn('portal.tab.session')
    ->withBodyKey('event', 'session.start')
    ->withBodyKey('tab_id', $tab->id)
    ->withBodyKey('otp', $otp)
    ->withBodyKey('buyin_amount', $amount)
    ->send();
```

**Consumer (Portal listens for game results from terminals):**
```php
// Artisan command / background worker
Kafka::createConsumer(['tab.game.history'])
    ->withAutoCommit()
    ->withHandler(function(ConsumedMessage $message) {
        $payload = $message->getBody();
        // Store game round in history table
        // Update tab_statuses
    })
    ->build()
    ->consume();
```

**Laravel Queue Integration:**
- Kafka consumers run as long-lived background processes (via `php artisan kafka:consume` or supervisor).
- Incoming game history messages are dispatched as Laravel Jobs for processing.
- Portal events (tab config, session) are published synchronously after the DB write.

**Message Format (JSON):**
```json
{
  "event": "session.start",
  "tab_id": "TAB-007",
  "otp": "482931",
  "buyin_amount": 5000.00,
  "gameday": "2026-09-13",
  "timestamp": "2026-09-13T10:30:00Z",
  "portal_version": "1.0"
}
```

**Infrastructure Notes:**
- Kafka broker runs as a separate service (Docker container or managed Kafka cloud service).
- In development: use `bitnami/kafka` Docker image.
- Partition key = `tab_id` — ensures message ordering per tab.
- Retention: 7 days (allows terminal to replay missed messages on reconnect).

---

## 5. ⚠️ Failure Management & Session Recovery (HIGHEST PRIORITY)

> The client has **explicitly emphasized** this as the most critical feature of the system.
> A session that starts must be fully restorable from any point of failure — no data loss, no orphaned credits.

### 5.1 What Is a "Failure" in This System?

| Failure Type | Description | Impact Without Recovery |
|---|---|---|
| **Terminal crash** | Tab hardware/software crashes mid-session | Player loses session, credits disappear |
| **Network loss** | Terminal loses connection to xplay portal | Round results not saved, balance unknown |
| **Portal server down** | xplay Laravel app crashes or restarts | New buy-ins fail, config unreachable |
| **Database unavailable** | DB crash, connection pool exhausted | All state lost for duration |
| **Power failure** | Terminal or server loses power | Session mid-round when power cut |
| **Kafka broker down** | Messaging layer unavailable | Config changes don't reach terminals |
| **Partial write** | DB write started but not committed | Inconsistent balance/session state |
| **Duplicate request** | Network retry causes double transaction | Player credited/debited twice |

---

### 5.2 Recovery Architecture — Four Layers

```
┌─────────────────────────────────────────────────────────────────┐
│  LAYER 1 — tab_statuses (Real-Time Snapshot)                    │
│  One row per tab. Updated after EVERY state-changing event.     │
│  Terminal fetches this on reconnect → instant full restore.     │
├─────────────────────────────────────────────────────────────────┤
│  LAYER 2 — Idempotency (Duplicate Prevention)                   │
│  Every Buyin/Cashout/history POST carries an Idempotency-Key.  │
│  Replayed requests return the original response, no DB write.   │
├─────────────────────────────────────────────────────────────────┤
│  LAYER 3 — Kafka Replay (Message Delivery Guarantee)            │
│  7-day retention. Terminal reconnects → consumes missed events. │
│  Portal reconnects → replays terminal game results from queue.  │
├─────────────────────────────────────────────────────────────────┤
│  LAYER 4 — tab_sessions + tab_ledger (Audit Source of Truth)    │
│  Immutable records of every session and transaction.            │
│  Used for manual recovery / dispute resolution by supervisors.  │
└─────────────────────────────────────────────────────────────────┘
```

---

### 5.3 tab_statuses — The Recovery Heartbeat Table

This is the single most important table for failure recovery. **One row per tab, always current.**

```sql
tab_statuses
  tab_id              PK, FK → tabs.id
  status              ENUM('idle','active','locked','break','disabled')
  lock_status         ENUM('unlocked','locked','break')

  -- Session snapshot
  current_session_id  FK → tab_sessions.id (nullable)
  current_otp         VARCHAR(10) nullable        ← active OTP
  session_started_at  TIMESTAMP nullable

  -- Financial snapshot
  current_balance     DECIMAL(12,2) nullable       ← credits on tab RIGHT NOW
  total_buyin         DECIMAL(12,2) default 0      ← session cumulative buyin
  total_cashout       DECIMAL(12,2) default 0      ← session cumulative cashout
  opening_float       DECIMAL(12,2) default 0

  -- Game snapshot
  last_game_no        VARCHAR(50) nullable          ← last completed round ID
  last_game_at        TIMESTAMP nullable
  last_bet_amount     DECIMAL(12,2) nullable        ← last round bet
  last_win_amount     DECIMAL(12,2) nullable        ← last round win

  -- Recovery payload
  recovery_data       JSON nullable                 ← full serialised state (see §5.4)
  last_synced_at      TIMESTAMP                     ← when this row was last written

  -- Hardware
  active_mac          VARCHAR(50) nullable           ← MAC of bound terminal
```

**Update triggers (tab_statuses must be written atomically with the main write):**

| Event | Fields Updated |
|---|---|
| Buyin processed | `status`, `current_session_id`, `current_otp`, `session_started_at`, `current_balance`, `total_buyin`, `recovery_data`, `last_synced_at` |
| Game round saved | `last_game_no`, `last_game_at`, `last_bet_amount`, `last_win_amount`, `current_balance`, `recovery_data`, `last_synced_at` |
| Cashout processed | `status=idle`, `current_session_id=null`, `current_otp=null`, `current_balance=0`, `total_cashout`, `recovery_data`, `last_synced_at` |
| Tab locked | `status=locked`, `lock_status=locked`, `last_synced_at` |
| Tab unlocked | `status=active/idle`, `lock_status=unlocked`, `last_synced_at` |
| MAC registered | `active_mac`, `last_synced_at` |

> **Rule:** `tab_statuses` is ALWAYS written in the same DB transaction as the primary write. If the primary write fails, `tab_statuses` is also rolled back. They are never out of sync.

---

### 5.4 recovery_data JSON Structure

The `recovery_data` JSON field on `tab_statuses` is the complete machine-readable state the terminal needs to restore itself:

```json
{
  "tab_id": 7,
  "tab_name": "Amatic-7",
  "game_type": "BAC",
  "session": {
    "session_id": 142,
    "otp": "482931",
    "started_at": "2026-09-15T10:30:00Z",
    "buyin_amount": 5000.00,
    "current_balance": 4750.00,
    "total_buyin": 5000.00,
    "total_cashout": 0.00
  },
  "last_round": {
    "game_no": "BAC-20260915-0042",
    "result": "banker",
    "bet_position": "banker",
    "bet_amount": 250.00,
    "win_amount": 237.50,
    "completed_at": "2026-09-15T10:45:22Z"
  },
  "config": {
    "min_bet": 100,
    "max_bet": 5000,
    "chip_preset": [100, 500, 1000, 5000, 10000],
    "base_value": 100
  },
  "payout_rules": [
    {"bet_name": "Player", "bet_position": "P", "multiplier": 1.0},
    {"bet_name": "Banker", "bet_position": "B", "multiplier": 0.95}
  ],
  "snapshot_at": "2026-09-15T10:45:22Z",
  "portal_version": "1.0"
}
```

---

### 5.5 Recovery API Endpoint

```
GET /api/v1/tabs/{id}/status
Header: X-Table-ID: {id}        ← middleware validates the requesting terminal
```

**Response (full state for terminal restore):**
```json
{
  "success": true,
  "tab": {
    "id": 7,
    "name": "Amatic-7",
    "status": "active",
    "lock_status": "unlocked"
  },
  "session": { ... },
  "recovery_data": { ... },     ← complete JSON blob (§5.4)
  "last_synced_at": "2026-09-15T10:45:22Z"
}
```

**Terminal reconnection flow:**
```
Terminal boots / reconnects
  → GET /api/v1/tabs/{id}/status
  → Response: recovery_data blob
  → Terminal deserialises state
  → Display: "Resuming session — Balance: $4,750.00 | OTP: 482931"
  → Player confirms OTP → session continues
  → Subscribe to Kafka topic for this tab_id → receive any missed events
```

---

### 5.6 Failure Scenario Playbook

#### Scenario A — Terminal Crashes Mid-Round

```
State before crash:
  tab_statuses: balance = 4,750, last_game_no = BAC-0042, status = active

Terminal restarts:
  → Calls GET /api/v1/tabs/7/status
  → Receives recovery_data with balance + last round info
  → Terminal resumes from after round BAC-0042
  → Kafka consumer delivers any missed round results from the queue

Portal action: none required — state was already persisted
```

#### Scenario B — Network Drops During Buyin POST

```
Cashier submits Buyin with Idempotency-Key: "key-abc-123"

Network drops BEFORE response received:
  → Cashier UI shows timeout/error
  → Cashier retries: POST /api/v1/ledger/txn with same Idempotency-Key: "key-abc-123"
  → IdempotencyMiddleware detects duplicate key → returns original 200 response
  → No second Buyin is created
  → tab_statuses already updated from first write

Result: No duplicate credit. Cashier sees success on retry.
```

#### Scenario C — Portal Server Restarts During Active Sessions

```
Portal crashes at 10:45. 3 tabs have active sessions.

Portal restarts at 10:47:
  → tab_statuses rows still intact (DB was not affected)
  → Tabs that were connected: Kafka consumer resumes → delivers queued round results
  → Tabs reconnecting: GET /api/v1/tabs/{id}/status → full state returned
  → Sessions resume transparently

No manual intervention required for a clean server restart.
```

#### Scenario D — Database Unavailable (Severe)

```
DB goes down. Kafka broker still running.

Terminals:
  → Cannot fetch /status (portal returns 503)
  → Terminal enters "offline mode": accepts no new bets
  → Displays: "System unavailable — contact cashier"

DB comes back up:
  → Portal reconnects to DB
  → tab_statuses intact (InnoDB crash recovery)
  → Kafka consumer replays queued terminal game results
  → tab_statuses updated with any rounds that came in during downtime
  → Terminals reconnect → GET /api/v1/tabs/{id}/status → resume
```

#### Scenario E — Partial Write (Transaction Rollback)

```
Buyin write starts:
  → BEGIN TRANSACTION
  → INSERT INTO tab_ledger ...         ← success
  → UPDATE tab_statuses ...            ← FAILS (e.g. deadlock)
  → ROLLBACK

Result:
  → tab_ledger insert is rolled back
  → tab_statuses unchanged
  → Response: 500 error
  → Cashier retries with same Idempotency-Key → proceeds normally
```

---

### 5.7 Heartbeat System

Terminals send a lightweight heartbeat every 30 seconds:

```
POST /api/v1/tabs/{id}/heartbeat
Header: X-Table-ID: {id}
Body: { "current_balance": 4750.00, "last_game_no": "BAC-0042" }
```

Portal response:
```json
{
  "status": "ok",
  "sync_required": false     ← true if portal's state differs from terminal's report
}
```

If `sync_required: true` → terminal triggers GET /status for full recovery data.

**Portal-side:**
- Updates `tab_statuses.last_synced_at`
- Compares terminal-reported `current_balance` against DB balance → flags discrepancy if gap > configured threshold
- If terminal goes silent for > 2 minutes → mark tab as `disconnected` in tab_statuses

---

### 5.8 Security Aspects

| Concern | Mechanism |
|---|---|
| **Rogue terminal impersonation** | MAC binding — X-Table-ID header validated against `active_mac` in tabs table |
| **Duplicate transactions** | `IdempotencyMiddleware` — 60s TTL keyed on `Idempotency-Key` header |
| **Session hijacking** | OTP is single-use per session; new Buyin invalidates any stale OTP |
| **Balance manipulation** | `current_balance` is calculated server-side only — terminal never sets balance directly |
| **Unauthorised config changes** | Permission middleware (`CheckPermission`) gates all web routes |
| **Replay attacks** | Idempotency-Key body-hash validation — same key + different body = 422 rejection |
| **Data corruption** | DB transactions wrap all multi-table writes; partial writes are rolled back atomically |
| **Audit trail** | Every Buyin/Cashout records `initiated_by` (cashier user ID) + `reference` (cage receipt) |
| **Session audit** | `tab_sessions` preserves full lifecycle — session_id, OTP, start, end, amounts |

---

### 5.9 Implementation Requirements Summary

> These are **not optional** — every write path in the system must follow these rules.

1. **Atomic writes** — `tab_statuses` must be updated in the same `DB::transaction()` as every primary write (Buyin, Cashout, history round, lock/unlock).
2. **Idempotency-Key** — Required on all mutating API calls (`POST /ledger/txn`, `POST /history/{game}`, `POST /heartbeat`).
3. **Recovery data serialised** — `recovery_data` JSON updated on every `tab_statuses` write.
4. **Kafka publish after commit** — Events published ONLY after `DB::transaction` commits. Never inside the transaction.
5. **Status endpoint always available** — `GET /api/v1/tabs/{id}/status` must have no authentication friction so terminals can always recover.
6. **Heartbeat monitoring** — Background job checks for tabs with `last_synced_at` older than 2 minutes and flags them as `disconnected`.
7. **No client-side balance** — Terminal never sends its own balance as authoritative. Balance is always read from `tab_statuses.current_balance`.

---


## 6. Data Model — Target State (xplay)

```
GameType  [Andar Bahar, Baccarat, Roulette]
  └─ has many PayoutRule

Tab  [replaces GameTable]
  ├─ name
  ├─ game_type_id        FK → game_types
  ├─ active_mac          MAC address of bound hardware terminal
  ├─ bet_index
  ├─ status              BOOLEAN — enabled/disabled
  ├─ lock_status         ENUM('unlocked','locked','break')
  ├─ locked_at / locked_by
  └─ has one TabConfig   → morphTo(BaccaratPreset|AndarBaharPreset|RoulettePreset)
  └─ has many TabPayoutRule → PayoutRule
  └─ has one TabStatus   (tab_statuses — current snapshot)
  └─ has many TabSession (tab_sessions — OTP session records)
  └─ has many TabLedger  (ledger — BUYIN/CASHOUT only)

TabSession
  ├─ tab_id, otp, txn_id(buyin), status
  ├─ started_at, completed_at
  ├─ buyin_amount, cashout_amount, player_ref

TabLedger  [simplified from TableLedger]
  ├─ tab_id, txn_type(BUYIN|CASHOUT)
  ├─ amount, tab_balance
  ├─ otp, reference, initiated_by
  ├─ gameday, processed

TabStatus  [snapshot table — one row per tab]
  ├─ tab_id (PK), status, lock_status
  ├─ current_session_id, current_otp, current_balance
  ├─ last_buyin_at, last_cashout_at
  ├─ last_game_no, last_game_at, last_synced_at
  └─ recovery_data (JSON)

Chip (denomination preset)
  └─ chip_1..5_value, base_value, preset_name

BaccaratPreset
  ├─ name, min_bet, max_bet, side_min/max_bet
  ├─ burn_card, commission, baccarat_6_commission
  └─ chip_preset_id

AndarBaharPreset
  ├─ name, min_bet, max_bet, burn_card
  ├─ enable_super_andar, enable_super_bahar
  └─ chip_preset_id

RoulettePreset  [🆕 Phase 2]
  ├─ name, roulette_type ('european'|'american')
  ├─ min_bet, max_bet, side_min_bet, side_max_bet
  └─ chip_preset_id

History Tables (per game — keep existing structure, add game_no index):
  baccarat_history
  andarbahar_history
  roulette_history  [🆕 Phase 2]
```

---

## 7. Roulette — Domain Knowledge

### Bet Types & Standard Payouts (European)

| Bet Name | bet_position | Payout |
|---|---|---|
| Straight Up | `STRAIGHT` | 35:1 |
| Split | `SPLIT` | 17:1 |
| Street | `STREET` | 11:1 |
| Corner | `CORNER` | 8:1 |
| Six Line | `SIX_LINE` | 5:1 |
| Column | `COLUMN` | 2:1 |
| Dozen | `DOZEN` | 2:1 |
| Red / Black | `RED` / `BLACK` | 1:1 |
| Even / Odd | `EVEN` / `ODD` | 1:1 |
| Low (1–18) / High (19–36) | `LOW` / `HIGH` | 1:1 |

---

## 8. API Endpoints — Full Map

### Tab Management (Phase 3)
```
GET    /api/v1/tabs/active                 ← Active tabs list
GET    /api/v1/tabs/by-mac/{mac}           ← Resolve tab by MAC
GET    /api/v1/tabs/{id}/configuration     ← Tab full config (preset, payout rules, chips)
GET    /api/v1/tabs/{id}/status            ← tab_statuses snapshot (recovery endpoint)
POST   /api/v1/tabs/{id}/register-mac      ← Bind MAC to tab
POST   /api/v1/tabs/{id}/unregister-mac    ← Unbind MAC
POST   /api/v1/tabs/{id}/enable            ← Enable tab
POST   /api/v1/tabs/{id}/disable           ← Disable tab
POST   /api/v1/tabs/{id}/lock              ← Lock tab
POST   /api/v1/tabs/{id}/unlock            ← Unlock tab
GET    /api/v1/tabs/{id}/bet-index         ← Fetch current bet index
POST   /api/v1/tabs/{id}/bet-index         ← Set bet index
```

### Session / OTP (Phase 3)
```
POST   /api/v1/tabs/{id}/session/start     ← Verify OTP, activate session (called by terminal)
GET    /api/v1/tabs/{id}/session/current   ← Get current active session
POST   /api/v1/tabs/{id}/session/end       ← End session on cashout
```

### Ledger (Phase 3 — simplified)
```
POST   /api/v1/ledger/txn                  ← Post BUYIN or CASHOUT (idempotent)
GET    /api/v1/ledger/tab/{tab_id}         ← All txns for a tab
GET    /api/v1/ledger/tab/{tab_id}/summary ← Balance summary
GET    /api/v1/ledger/pending              ← Pending/unprocessed txns
```

### History (Phase 2/3)
```
GET    /api/v1/history/{game}              ← Parameterised fetch
  ?tab_id=TAB-007
  &date=2026-09-13
  &otp=482931
  &limit=100
  &page=1
  &sort=desc
  &winner=banker

POST   /api/v1/history/{game}              ← Terminal posts game round result
GET    /api/v1/history/{game}/{recordId}   ← Single round
```

### Game Day (Evaluate)
```
GET    /api/v1/game-day/current
POST   /api/v1/game-day/start
POST   /api/v1/game-day/close
```

---

## 9. Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel (PHP) |
| Frontend | Blade + Tailwind CSS + Alpine.js |
| Build | Vite |
| Database | SQLite (dev) / MySQL (prod) |
| Auth | Laravel Breeze |
| API Auth | Laravel Sanctum |
| Role/Permission | Custom system |
| Messaging | **Apache Kafka** (`mateusjunges/laravel-kafka`) |
| Kafka Broker (dev) | Docker `bitnami/kafka` |

---

## 10. File Structure (Post Phase 1)

```
app/
  Http/
    Controllers/
      ChipController.php           ✅
      DashboardController.php      ✅ (simplify for tabs)
      GameDayController.php        🔒 evaluate
      GameHistoryController.php    🔧 update gameMap (remove DT/3CP/BJ/MF/CW refs)
      GameTableController.php      🔧 → TabController (Phase 3)
      GameTypeController.php       🔒 keep for API dropdown
      PayoutRuleController.php     ✅
      ProfileController.php        ✅
      ReportController.php         🔒 evaluate
      ResetController.php          ✅
      RoleController.php           ✅
      TableFloatController.php     🔧 → strip down / remove (Phase 3)
      TableLedgerController.php    🔧 → simplify to BUYIN/CASHOUT (Phase 3)
      userController.php           ✅
      TabSessionController.php     🆕 (Phase 3)
    Middleware/
      CheckPermission.php          ✅
      IdempotencyMiddleware.php     ✅
      ResolveTabMiddleware.php      🆕 (Phase 3) — reads X-Tab-ID header
  Models/
    AndarBaharPreset.php           ✅
    AndarbaharHistory.php          ✅
    BaccaratHistory.php            ✅
    BaccaratPreset.php             ✅
    Chip.php                       ✅
    GameDay.php                    🔒
    GameTable.php                  🔧 → Tab (Phase 3)
    GameTableConfig.php            🔧 → TabConfig
    GameTablePayoutRule.php        🔧 → TabPayoutRule
    GameType.php                   ✅
    PayoutRule.php                 ✅
    Permission.php                 ✅
    Role.php                       ✅
    TableFloat.php                 🔧 → remove/simplify (Phase 3)
    TableLedger.php                🔧 → simplify to BUYIN/CASHOUT
    User.php                       ✅
    RoulettePreset.php             🆕 Phase 2
    RouletteHistory.php            🆕 Phase 2
    TabSession.php                 🆕 Phase 3
    TabStatus.php                  🆕 Phase 3

resources/views/
  auth/                            ✅
  chips/                           ✅
  components/                      ✅ (sidebar updated)
  dashboard.blade.php              🔧 (simplify)
  game_tables/                     🔧 → replace with tabs/
  history/                         🔧 (update for 3-game map)
  ledger/                          🔧 (BUYIN/CASHOUT only)
  layouts/                         ✅
  payout_rules/                    ✅
  profile/                         ✅
  reports/                         🔒
  roles/                           ✅
  users/                           ✅
  utilities/                       ✅
  tabs/                            🆕 Phase 3

database/
  migrations/                      🔒 ALL KEPT (revert safety)
  seeders/
    GameTypeSeeder.php             ✅ updated
    PayoutRuleSeeder.php           ✅ updated
    ShoeTypeSeeder.php             ✅ cleared
    PermissionSeeder.php           🔧 rename game_tables → tabs (Phase 3)
```

---

## 11. Permissions Map

| Slug | Module | Status |
|---|---|---|
| view/create/edit/deactivate-users | users | ✅ Active |
| view/create/edit/delete-roles | roles | ✅ Active |
| assign-permissions | roles | ✅ Active |
| create-game_tables → **manage-tabs** | tabs | 🔧 Rename (Phase 3) |
| view/edit/create/delete-chips | chips | ✅ Active |
| view-history | history | ✅ Active |
| view-ledger | ledger | ✅ Active |
| manage-resets | utilities | ✅ Active |
| **lock-tabs** | tabs | 🆕 Phase 3 |
| **manage-sessions** | sessions | 🆕 Phase 3 |

---

## 12. Development Checklist

### Phase 1 — Cleanup ✅ COMPLETE (2026-09-13 → 2026-09-15)
- [x] Remove DT/3CP/BJ/MF/CW preset + history models
- [x] Remove ShoeType, Theme, ThemeController, FormatsGameTable
- [x] Remove themes + game_types views
- [x] Update GameTypeSeeder, PayoutRuleSeeder, ShoeTypeSeeder
- [x] Clean routes (game_types, themes removed)
- [x] Update sidebar → Tab Config at `/tabs`
- [x] **GameHistoryController** — removed all DT/3CP/BJ/MF/CW imports + gameMap entries
- [x] **GameTableController** — removed ShoeType, felt_color, FormatsGameTable, all deleted preset refs
- [x] **TableFloatController** — removed FormatsGameTable trait + all deleted history model imports/gameMap
- [x] **ResetService** — removed all out-of-scope history/preset tables, ShoeTypeSeeder
- [x] **DatabaseSeeder** — removed ShoeTypeSeeder from call list
- [x] **GameTable model** — removed shoeType() relation, felt_color + shoe_type_id from fillable
- [x] **Migrations deleted** (8 files): themes, DT/3CP/BJ/MF/CW presets, shoe_types × 2
- [x] **History migration** — stripped to Baccarat + Andar Bahar only, game_no inlined
- [x] **bet_position migration** — stripped to in-scope tables only, added hasTable guards

### Phase 2 — Roulette ✅ COMPLETE (2026-09-18)
- [x] Migration: `roulette_presets` table (`2026_09_18_000001`)
- [x] Migration: `roulette_history` table (`2026_09_18_000002`)
- [x] Model: `RoulettePreset` — with chipPreset(), tableAssignment(), bet tier helpers
- [x] Model: `RouletteHistory` — with resolveColour(), isWinningBet() result engine helpers
- [x] `GameHistoryController` — Roulette activated in gameMap (ROL + ROULETTE keys)
- [x] `TableFloatController` — Roulette activated in gameMap
- [x] `GameTableController` — ROL added to resolvePresetModel()
- [x] `ResetService` — roulette_history + roulette_presets added to HISTORY/PRESET tables
- [x] `PayoutRuleSeeder` — 13 Roulette rules confirmed by client (payout_ids 15–27)
  - Renamed "Six Line" → "Line" (bet_position: SIX_LINE → LINE) per client spec

### Phase 3 — Tabs, Sessions, Ledger, Status ✅ Backend Complete
- [x] **Migration:** Alter `game_tables` — added `lock_status`, `locked_at`, `locked_by`, `denomination`, `last_seen_at`, `connection_status`
- [x] **Refactor:** `GameTable` model — added all new columns, relations, lock/session helpers
- [x] **Migration:** Created `tab_statuses` table (recovery snapshot, one row per tab)
- [x] **Migration:** Created `tab_sessions` table (OTP session lifecycle)
- [x] **Migration:** Simplified `table_ledgers` — BUYIN/CASHOUT only, added `otp` + `session_id`
- [x] **Model:** `TabStatus` — recovery_data JSON, display_colour helper
- [x] **Model:** `TabSession` — OTP generation, session lifecycle, net result
- [x] **Model:** `TableLedger` — simplified to BUYIN/CASHOUT, session relation
- [x] **Controller:** `TabController` — CRUD, enable/disable, lock/unlock/break, MAC register/unregister
- [x] **Controller:** `TabSessionController` — buyin (OTP issued), cashout (OTP verified), verify-otp, sessions history
- [x] **Controller:** `TabStatusController` — GET /status (recovery blob), POST /heartbeat, GET /statuses (aggregate)
- [x] **Service:** `TabStatusService` — onBuyin, onCashout, onGameRound, onLockChange, onHeartbeat, buildRecoveryData
- [x] **Routes:** Web `/tabs/*` (19 routes) + API `/api/v1/tabs/*` — all resolving, verified with route:list
- [x] **All 28 migrations ran** — confirmed via migrate:status
- [x] **New views:** `resources/views/tabs/` — index (list table), create (form), edit (form + sidebar) ✅
- [x] **Route model binding:** `{tab}` → `GameTable::class` in `AppServiceProvider::boot()`
- [x] **Update:** Sidebar `/tabs` link active (done in Phase 1)
- [ ] **Update:** `GameHistoryController::store` — call `TabStatusService::onGameRound()` after each save ← **NEXT**
- [ ] **Update:** History API — add `limit`, `otp`, `from`, `to`, `page` params

### Phase 3b — Failure Management Implementation ✅ Core Complete
- [x] **Heartbeat endpoint:** `POST /api/v1/tabs/{id}/heartbeat` — balance discrepancy detection
- [x] **Atomic write enforcement:** `TabController` + `TabSessionController` use `DB::transaction()` wrapping primary write + TabStatusService call
- [x] **Idempotency enforcement:** `middleware('idempotent')` on buyin + cashout routes
- [x] **Recovery data on every write:** `TabStatusService::buildRecoveryData()` called in every onBuyin/onCashout/onGameRound
- [x] **Terminal reconnect flow:** `GET /api/v1/tabs/{id}/status` returns full recovery_data blob
- [x] **Balance authority:** Heartbeat reads terminal balance and compares — terminal never sets balance
- [x] **Discrepancy detection:** `onHeartbeat()` compares terminal vs DB balance, returns `sync_required: true`
- [ ] **Heartbeat monitoring job:** Artisan command to flag tabs with `last_synced_at` > 2 min as `disconnected` ← Phase 4
- [ ] **Kafka publish guard:** Events after DB commit — Phase 4

### Phase 4 — Kafka Integration
- [ ] Install `mateusjunges/laravel-kafka`
- [ ] Configure Kafka broker connection (`.env` keys)
- [ ] Create Kafka topics (portal.tab.config, portal.tab.status, portal.tab.session, tab.game.history, tab.game.status)
- [ ] Create KafkaPublisherService (wraps publish logic, fires after DB commit)
- [ ] Hook Kafka publish into: tab enable/disable, lock/unlock, session start/end, config change
- [ ] Create Kafka consumer command for `tab.game.history` (store round results + update tab_statuses)
- [ ] Create Kafka consumer command for `tab.game.status` (heartbeat / sync)
- [ ] Set up Supervisor config for consumer workers
- [ ] Docker Compose entry for Kafka + Zookeeper (dev)

### Phase 5 — Dashboard & Reports
- [ ] Dashboard (Tabs Index Page): tab grid with live balance, status colour, session indicator
- [ ] Header bar: aggregate stats (total in/out, bet/win, active count, credits on floor)
- [ ] Reports: shift and daily buyin/cashout per tab, game type breakdown

---

## 13. Decisions & Notes Log

| Date | Decision | Reason |
|---|---|---|
| 2026-09-13 | Fork from gamemgt | Auth, chip config, AB + Baccarat pre-configured |
| 2026-09-13 | Roulette built from scratch | Not in gamemgt |
| 2026-09-13 | MAC binding **in scope** | Hardware terminal identification required |
| 2026-09-13 | Migrations NOT dropped | Revert safety |
| 2026-09-13 | `X-Table-ID` header from **game station** middleware | Station self-identifies; controller stamps `table_id` — never trusted from request body |
| 2026-09-13 | xplay **calculates winner + payouts** from raw card data | Game station sends raw cards only; xplay is the authoritative result engine |
| 2026-09-13 | `table_id` ≠ `tab_id` — two separate entities | `table_id` = card-drawing station (FK, integer); `tab_id` = player terminal (string, nullable) |
| 2026-09-13 | Only BUYIN + CASHOUT in ledger | Cage-only transactions; no table float in Virtuals |
| 2026-09-13 | OTP issued on Buyin, valid until Cashout | Session security; links player to specific tab session |
| 2026-09-13 | `tab_statuses` as snapshot table | Recovery without expensive joins or re-aggregation |
| 2026-09-13 | Kafka for real-time events | Low-latency terminal updates; guaranteed delivery; replay support |
| 2026-09-13 | History API with `limit`/`otp`/`from`/`to` params | Client requirement for flexible data retrieval |

---

so basically we are building a cage system for cashier to Cashin and Cashout the about to the tab. also configure the tab and generate the reports, maintain
  ledger transactions and history.
  most importantly, the client want to handle the security aspects of the system like failure management. in case of a failure, the current data/session should be
  thoroughly restored to the tabs from where it was left out. this is the most important part he has emphasised on. add this concept to the the phase to be
  implemented


Remaining items:

  1. GameHistoryController::store → hook TabStatusService::onGameRound() after each round save
  2. History API — add limit, otp, from, to, page filter params

*Maintained by AI development assistant. Update after every significant change.*

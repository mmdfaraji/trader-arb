You are a senior backend engineer and systems architect. Build a production-grade arbitrage trading platform with a Trader execution module. Your job: design, scaffold, and implement the core services, APIs, persistence layer, and automated tests. Optimize for correctness, safety, and observability. Provide runnable code, infra-as-code/devcontainers, and end-to-end tests.

Constraints & Non-Negotiables

Safety first: include a global DRY-RUN/SANDBOX mode that never sends live orders; toggle via config/env.

Deterministic idempotency: all order placement paths must use idempotency keys.

Low latency path: target median end-to-end signal→final state latency under the provided ttl_ms.

Observability: structured logs, metrics (Prometheus), health/readiness probes.

Persistence: relational DB with schemas listed below (Postgres preferred).

Concurrency: safe balance reservations and hedge logic (no double-spends).

Backoff & rate limits: exponential backoff with jitter for polling and API retries.

Reproducibility: Docker + devcontainer; make up, make test, make lint, make seed.

High-Level Objective

Implement an arbitrage engine that:

ingests validated signals (JSON) describing multi-leg trades,

performs preflight checks (freshness, balances, market rules, depth, fees, PnL, risk),

executes legs with correct Time-in-Force (IOC/FOK/GTC/day),

monitors fills via websocket and/or status-poll with backoff,

handles partial fills using reverse hedge orders,

produces a final Execution Report with PnL, fees, slippage, deltas.

Supported Strategies (initial)

Spot–Spot arbitrage across exchanges.

Triangular arbitrage within a single exchange.

Spot–Futures basis/hedge and Cross-Exchange Hedging (scaffold interfaces; implement Spot–Spot first).

Funding-rate capture (leave as extension point with interfaces + mocks).

Core References (summarized)

Definitions of arbitrage types and trade-offs, including fees, slippage, and basis/funding.

Trader module responsibilities, inputs/outputs, execution flow, and final states.

Preflight checklist: TTL, balances/reservations, tick/step/pack sizes, min_notional, depth, fees, PnL, risk limits.

Partial fill + reverse hedge handling, labeling PARTIAL+HEDGED.

Logs & Metrics to expose (fill rates, latency histograms, PnL distribution, hedge success).

DB tables and update points through lifecycle.

Sequence diagrams for signal→orders and monitoring/hedge closure.

Glossary for slippage_bps, status-poll, backoff, etc.

Input/Output Contracts
Signal (input)
{
  "signal_id": "uuid",
  "created_at": "2025-09-03T08:30:00Z",
  "ttl_ms": 5000,
  "legs": [
    {"Exchange":"ramzinex","market":"USDT/IRR","Side":"buy","Price":1000000,"Qty":10000,"time_in_force":"IOC"},
    {"Exchange":"nobitex","market":"USDT/IRR","Side":"sell","Price":1010000,"Qty":10000,"time_in_force":"IOC"}
  ],
  "constraints": {
    "Max_slippage_bps": 10,
    "Min_expected_pnl": 5000000,
    "Max_latency_ms": 800
  }
}

Execution Report (output)
{
  "signal_id": "uuid",
  "state": "FILLED",
  "legs": [
    {
      "Client_order_id": "uuid-leg1",
      "Exchange": "ramzinex",
      "status": "FILLED",
      "Filled_qty": 10000,
      "Avg_price": 1000000,
      "Fee": 20000
    }
  ],
  "net_position_delta": {"USDT":0,"IRR":0},
  "pnl_realized": 5000000,
  "timestamps": {"accepted":"...","closed":"..."}
}


(Use these as golden fixtures for tests.)

Services/Modules to Deliver

Signal Ingest API

POST /signals (internal/privileged): validates schema, persists to signals + signal_legs, enqueues execution.

Reject if ttl_ms expired.

Preflight Service

Compute qty_exec per leg from: available balance minus active balance_locks, market constraints (tick_size, step_size, min_notional, pack_size), and signal limits. Fail if expected net PnL after fees and slippage buffer < Min_expected_pnl.

Account Selector

Choose exchange account (support multi-account per exchange now, strategy pluggable later).

Balance Locker

Create balance_locks and adjust balances.reserved. Unlock/adjust on fills/cancel.

Order Router

Build idempotent client order IDs; place orders with correct TIF; record orders (NEW→SENT→PARTIAL/FILLED/CANCELLED), order_fills, order_events; store exchange_order_id.

Fill Monitor

Prefer websocket; fallback to status-poll with exponential backoff + full jitter; cap polling interval; terminate on terminal status.

Hedge Engine

On leg mismatch, compute hedge_qty = |filledA - filledB|, submit hedge order (same or alt exchange), link to hedge_actions, update on fills, cancel residual legs, relock/unlock appropriately; final label PARTIAL+HEDGED.

PnL & Reporting

Compute realized PnL, fees, slippage (bps), net_position_delta; persist execution_reports; update final signals.status (FILLED / PARTIAL+HEDGED / CANCELLED).

Risk & Limits

Per portfolio, per exchange, per market ceilings; circuit breaker for abnormal volatility.

Observability

Logs: signal receipt, preflight rejects, order lifecycle, fills, hedge actions, summary.

Metrics (Prometheus): signals_total, fill_rate, cancel_rate, partial_fill_rate, avg_fill_latency_ms, latency histogram, avg_slippage_bps, pnl_distribution, pnl_realized_sum, hedge_success_rate, hedge_invocations. Provide Grafana dashboards.

Data Model (Postgres)

Create tables exactly as named and linked below; include necessary PK/FK, unique indexes, and helpful composite indexes for hot paths:

exchanges(id, name, api_url, ws_url, status, created_at)

currencies(id, symbol, name)

currency_exchanges(id, exchange_id, currency_id, exchange_symbol, scale_override)

pairs(id, base_currency_id, quote_currency_id, symbol)

pair_exchanges(id, exchange_id, pair_id, exchange_symbol, tick_size, step_size, min_notional, max_order_size, pack_size, maker_fee_bps, taker_fee_bps, status)

exchange_accounts(id, exchange_id, label, api_key_ref, is_primary, created_at)

balances(id, exchange_account_id, currency_id, available, reserved, updated_at)

balance_locks(id, exchange_account_id, currency_id, amount, reason, signal_id, created_at, expires_at)

signals(id, created_at, ttl_ms, status, source, constraints, expected_pnl)

signal_legs(id, signal_id, exchange_id, pair_id, side, price, qty, tif, desired_role)

orders(id, signal_id, exchange_id, exchange_account_id, pair_id, side, type, tif, client_order_id, exchange_order_id, price, qty, qty_exec, notional, status, filled_qty, avg_price, created_at, sent_at, closed_at)

order_fills(id, order_id, fill_seq, filled_qty, price, fee_amount, fee_currency_id, trade_id, filled_at)

hedge_actions(id, signal_id, cause, from_order_id, hedge_order_id, qty, status, result_details, created_at)

execution_reports(id, signal_id, final_state, net_position_delta, pnl_realized, latency_ms, slippage_bps, created_at)

order_events(id, order_id, event, payload, created_at)

signal_events(id, signal_id, event, payload, created_at)

Also implement a lifecycle writer that updates the above tables at the exact phases laid out in the source spec (ingest→validate→reserve→send→monitor→hedge→finalize). Provide unit tests to assert the correct inserts/updates per phase.

Execution Flow (must implement)

Follow the multi-phase flow exactly (A–E): ingest/validate → account select & reserve → send & monitor → partial/hedge → compute & finalize. Include the sequence diagram logic in code comments and integration tests that simulate fills, timeouts, cancels, and hedges.

Algorithms & Calculations

qty_exec: min across (available balances after reservations, market constraints, leg qty, risk caps).

Rounding: apply tick_size, step_size, pack_size before order placement.

Slippage Guard: derive protective prices from Max_slippage_bps; reject if implied PnL after fees/slippage < Min_expected_pnl.

Fees: include maker/taker bps per pair_exchanges.

Backoff: exponential with cap + full jitter for polling/retries.

TIF logic: IOC/FOK/GTC/DAY supported; IOC default for arbitrage legs.

External Exchange Integration

Create an exchange abstraction (placeOrder, cancelOrder, getOrder, getOrderFills, wsSubscribe) and concrete adapters for at least two spot exchanges used in examples (use mocks first; real adapters feature-flagged).

Handle precision, auth, time sync/drift, and rate limits; provide replayable HTTP/WS transcripts in tests.

Deliverables

Codebase (language/framework at your discretion; prefer strong typing). Include:

Services: Signal API, Trader, Preflight, Router, Monitor, Hedge, Risk, Reporting.

Exchange SDK abstraction + 2 mock adapters + adapter scaffolds.

DB migrations and seeds (pairs, fees, constraints).

Config system with DRY_RUN, API keys (vault reference), risk caps.

Prometheus metrics, OpenAPI/Swagger docs for internal APIs.

Tests

Unit tests for preflight math (tick/step/pack, slippage, PnL).

Integration tests for full lifecycle: FILLED, PARTIAL+HEDGED, CANCELLED, timeouts.

Deterministic simulators for order books (depth, fees, slippage).

Ops

Dockerfiles + devcontainer.json + docker-compose.yaml.

Makefile with common targets.

Sample Grafana dashboards (JSON).

Docs

README (runbook, architecture, safety toggles).

Diagrams (plantuml/mermaid) matching sequence flows.

Postman or HTTPie scripts.

Acceptance Criteria (must pass)

Given the provided numeric example (USDT/IRR, fees = 10 bps, slippage buffer), preflight computes expected net PnL ≈ 40,899,000 IRR before buffers (match within tolerance), and rejects if buffers push below Min_expected_pnl.

End-to-end happy path: two-leg Spot–Spot FILLED within ttl_ms, correct lifecycle events persisted, accurate PnL, zero net position delta.

Partial fill scenario triggers reverse hedge, cancels residuals, marks PARTIAL+HEDGED, leaves near-zero exposure.

Observability endpoints expose metrics; dashboards visibly chart fill_rate, latency histogram, and PnL distribution.

DRY-RUN mode proves no live order hits even when adapters are configured.

Nice-to-Haves (if time permits)

Strategy plug-in interface for Triangular, Spot–Futures, Funding-rate modules (enable flags only for now).

Replay runner to simulate historical opportunities from captured books.

Basic web UI for execution reports & metrics.

Notes for the Agent

Use clean architecture with explicit domain boundaries.

Heavily comment critical math (slippage, fees, qty rounding).

Prefer strongly typed DTOs and validation.

Provide migration rollbacks and seed data.

Prioritize deterministic tests and reproducible environments.


Tech Stack Requirements
Laravel & Runtime

Laravel 11, PHP 8.2+.

Laravel Octane with Swoole server.

Queues: redis driver pointing to KeyDB.

Cache & rate limiting: Redis/KeyDB.

HTTP API: /api/signals (internal/privileged), /healthz, /readyz, /metrics.

Datastores

PostgreSQL as primary DB.

KeyDB as Redis-compatible cache/queue/broker.

Containers (docker-compose)

Create the following services: app, postgres, keydb (plus optional prometheus, grafana,Makefile).


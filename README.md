# Trader Arbitrage Platform

A minimal scaffold of an arbitrage trading platform built with Laravel 11. It currently exposes a Signal Ingest API that stores trading signals and their legs.

## Safety Toggle

Set `DRY_RUN=true` in the environment to ensure no live orders are ever sent. This flag is read from `config/trader.php`.

## Usage

```bash
cp .env.example .env
php artisan migrate
make test
```

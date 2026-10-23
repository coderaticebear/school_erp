#!/usr/bin/env bash
# Update the demo server: pull, rebuild, migrate, restart. Run on the EC2 host from the repo root.
set -euo pipefail
cd "$(dirname "$0")/.."

COMPOSE="docker compose -f compose.prod.yaml"

git pull --ff-only
$COMPOSE build
$COMPOSE up -d pgsql

# Migrations run as the schema owner (never the app role); --force is required in production.
$COMPOSE run --rm tools php artisan migrate --database=pgsql_migrations --force

$COMPOSE up -d
$COMPOSE ps

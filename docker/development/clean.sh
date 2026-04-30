#!/bin/bash
# Tears down the dev environment and removes all generated local artifacts.
# Run this for a guaranteed clean slate before rebuilding from scratch.
set -e

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DEV="$ROOT/docker/development"

echo ""
echo "====================================="
echo "  InvoiceShelf Dev — Clean"
echo "====================================="
echo ""
echo "This will:"
echo "  - Stop and remove all dev containers + named volumes (DB data, node_modules)"
echo "  - Delete: .env, vendor/, public/hot, public/storage, bootstrap/cache/*.php"
echo "  - Delete: storage/.docker-setup-done, database/database.sqlite"
echo ""

if [ "${1:-}" != "-y" ]; then
    printf "Continue? [y/N] "
    read -r answer
    case "$answer" in
        [yY]*) ;;
        *) echo "Aborted."; exit 0 ;;
    esac
fi

# Stop containers and remove named volumes for all three variants
for compose in \
    "$DEV/docker-compose.mysql.node.yml" \
    "$DEV/docker-compose.sqlite.node.yml" \
    "$DEV/docker-compose.pgsql.node.yml"
do
    if [ -f "$compose" ]; then
        echo "[clean] docker compose down -v: $(basename "$compose")"
        docker compose -f "$compose" down -v --remove-orphans 2>/dev/null || true
    fi
done

# Local artifacts
echo "[clean] Removing local artifacts..."

rm -f  "$ROOT/.env"
rm -f  "$ROOT/storage/.docker-setup-done"
rm -f  "$ROOT/public/hot"
rm -f  "$ROOT/database/database.sqlite"
rm -rf "$ROOT/vendor"
rm -rf "$ROOT/bootstrap/cache"/*.php

# public/storage is a symlink created by artisan storage:link
if [ -L "$ROOT/public/storage" ]; then
    rm "$ROOT/public/storage"
fi

echo ""
echo "[clean] Done. Rebuild with:"
echo ""
echo "  MariaDB:    docker compose -f docker/development/docker-compose.mysql.node.yml up --build"
echo "  SQLite:     docker compose -f docker/development/docker-compose.sqlite.node.yml up --build"
echo "  PostgreSQL: docker compose -f docker/development/docker-compose.pgsql.node.yml up --build"
echo ""

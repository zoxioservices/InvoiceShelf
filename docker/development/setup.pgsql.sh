#!/bin/sh
set -e

APP_DIR="/var/www/html"
ENV_TEMPLATE="$APP_DIR/docker/development/.env.pgsql"
FLAG_FILE="$APP_DIR/storage/.docker-setup-done"

cd "$APP_DIR"

echo ""
echo "====================================="
echo "  InvoiceShelf Dev Environment Setup"
echo "====================================="
echo ""

# Copy .env from Docker template if it does not exist
if [ ! -f "$APP_DIR/.env" ]; then
    echo "[setup] Creating .env from Docker PostgreSQL template..."
    cp "$ENV_TEMPLATE" "$APP_DIR/.env"
else
    echo "[setup] .env already exists, skipping copy."
fi

# Install Composer dependencies if vendor/ is missing
if [ ! -d "$APP_DIR/vendor" ]; then
    echo "[setup] Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
else
    echo "[setup] vendor/ found, skipping composer install."
fi

# Generate APP_KEY if blank
if ! grep -q "^APP_KEY=base64:" "$APP_DIR/.env"; then
    echo "[setup] Generating application key..."
    php artisan key:generate --no-interaction
else
    echo "[setup] APP_KEY already set, skipping."
fi

# Ensure required directories exist and have correct permissions
echo "[setup] Setting directory permissions..."
mkdir -p "$APP_DIR/storage/framework/sessions" \
         "$APP_DIR/storage/framework/views" \
         "$APP_DIR/storage/framework/cache" \
         "$APP_DIR/storage/logs" \
         "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# Create storage symlink (idempotent)
echo "[setup] Creating storage symlink..."
php artisan storage:link --no-interaction 2>/dev/null || true

# Wait for the database to be ready
echo "[setup] Waiting for database connection..."
until php artisan db:show --no-interaction > /dev/null 2>&1; do
    echo "[setup] Database not ready yet, retrying in 2s..."
    sleep 2
done
echo "[setup] Database is ready."

# First-time setup: migrate + seed
if [ ! -f "$FLAG_FILE" ]; then
    case "${SEED_MODE:-basic}" in
        none)
            echo "[setup] Skipping migrations and seeding (SEED_MODE=none) — complete setup via the installation wizard."
            ;;
        *)
            echo "[setup] First run detected: running migrations..."
            php artisan migrate --no-interaction

            case "${SEED_MODE:-basic}" in
                demo)
                    echo "[setup] Seeding with demo data (SEED_MODE=demo)..."
                    php artisan db:seed --no-interaction
                    php artisan db:seed --class=DemoSeeder --no-interaction
                    ;;
                *)
                    echo "[setup] Seeding (SEED_MODE=basic)..."
                    php artisan db:seed --no-interaction
                    php artisan tinker --execute "App\Models\Setting::setSetting('profile_complete', 'COMPLETED');"
                    ;;
            esac
            ;;
    esac

    touch "$FLAG_FILE"
    echo "[setup] First-time setup complete. Flag written to storage/.docker-setup-done"
else
    echo "[setup] Running pending migrations (if any)..."
    php artisan migrate --no-interaction
fi

echo ""
echo "[setup] Setup finished successfully."
echo "====================================="
echo ""

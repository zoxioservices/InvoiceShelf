#!/bin/bash
# Run this script once per machine before starting the development environment.
# It adds the required hosts entry and, on Linux, configures USRID/GRPID in
# your shell RC so Docker containers run as your user (preventing root-owned files).
set -e

HOSTS_ENTRY="127.0.0.1 invoiceshelf.test"

echo ""
echo "====================================="
echo "  InvoiceShelf Host Setup (one-time)"
echo "====================================="
echo ""

# ── OS detection ────────────────────────────────────────────────────────────
OS="$(uname -s 2>/dev/null)"

# ── /etc/hosts ──────────────────────────────────────────────────────────────
if grep -qF "invoiceshelf.test" /etc/hosts 2>/dev/null; then
    echo "[host-setup] invoiceshelf.test already in /etc/hosts, skipping."
else
    echo "[host-setup] Adding invoiceshelf.test to /etc/hosts (requires sudo)..."
    echo "$HOSTS_ENTRY" | sudo tee -a /etc/hosts > /dev/null
    echo "[host-setup] Done."
fi

# ── USRID / GRPID (Linux only) ───────────────────────────────────────────────
# Docker Desktop on macOS/Windows handles UID mapping transparently;
# the compose files fall back to UID/GID 1000 when these vars are unset,
# which is fine on those platforms.
if [ "$OS" = "Linux" ]; then
    EXPORT_LINE='export USRID=$(id -u) GRPID=$(id -g)'

    # Detect shell RC — only bash and zsh are auto-configured
    case "$SHELL" in
        */zsh)  SHELL_RC="$HOME/.zshrc" ;;
        */bash) SHELL_RC="$HOME/.bashrc" ;;
        *)
            echo "[host-setup] WARNING: Shell '$SHELL' is not auto-configured."
            echo "[host-setup]          Add the following line to your shell's RC file manually:"
            echo "[host-setup]"
            echo "[host-setup]            export USRID=\$(id -u) GRPID=\$(id -g)"
            echo "[host-setup]"
            echo "[host-setup]          Then reload it (e.g. 'source ~/.your_rc_file') before"
            echo "[host-setup]          starting Docker so the variables are available to Compose."
            SHELL_RC=""
            ;;
    esac

    if [ -n "$SHELL_RC" ]; then
        if grep -qF "USRID" "$SHELL_RC" 2>/dev/null; then
            echo "[host-setup] USRID/GRPID export already in $SHELL_RC, skipping."
        else
            echo "[host-setup] Adding USRID/GRPID export to $SHELL_RC..."
            echo "$EXPORT_LINE" >> "$SHELL_RC"
            echo "[host-setup] Done. Run 'source $SHELL_RC' or open a new terminal before"
            echo "[host-setup] starting Docker, so the variables are available to Compose."
        fi
    fi

    # Apply to the current session immediately regardless of RC outcome
    export USRID=$(id -u)
    export GRPID=$(id -g)
else
    echo "[host-setup] $OS detected — skipping USRID/GRPID setup (not needed on this platform)."
fi

echo ""
echo "[host-setup] All done! Host setup complete."
echo ""
echo "Next step — start the dev environment (pick your database):"
echo ""
echo "  MariaDB:    docker compose -f docker/development/docker-compose.mysql.node.yml up --build"
echo "  SQLite:     docker compose -f docker/development/docker-compose.sqlite.node.yml up --build"
echo "  PostgreSQL: docker compose -f docker/development/docker-compose.pgsql.node.yml up --build"
echo ""

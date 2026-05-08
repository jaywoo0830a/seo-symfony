#!/usr/bin/env bash
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$HERE"

if ! docker info >/dev/null 2>&1; then
    echo "✗ Docker daemon is not reachable. Start Docker Desktop / dockerd first." >&2
    exit 1
fi

# Pass host UID/GID to the build so files written by the container (var/cache,
# vendor, node_modules) are owned by the developer on disk.
# (bash makes UID/GID readonly, so we use HOST_UID/HOST_GID and remap inside compose.)
export HOST_UID="$(id -u)"
export HOST_GID="$(id -g)"

# Source .env if present so HTTP_PORT / POSTGRES_PORT overrides apply.
if [[ -f .env ]]; then
    set -a; . ./.env; set +a
fi

HTTP_PORT="${HTTP_PORT:-8080}"
POSTGRES_PORT="${POSTGRES_PORT:-5433}"
MAILPIT_PORT="${MAILPIT_PORT:-8025}"

echo "▶ Building dev images (php)…"
docker compose build

echo "▶ Starting stack: database → php → nginx → node (sass watch) → mailer"
docker compose up -d

echo "▶ Installing composer dependencies inside php container (first run is slowest)…"
docker compose exec -T php composer install --no-interaction

echo "▶ Waiting for database to become healthy…"
for _ in {1..30}; do
    state="$(docker compose ps --format json database 2>/dev/null | grep -oE '"Health":"[^"]*"' | head -1 | cut -d'"' -f4 || true)"
    [[ "$state" == "healthy" ]] && break
    sleep 2
done

echo "▶ Running migrations (idempotent)"
docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo
docker compose ps
echo
cat <<EOF
✓ Dev stack ready.
  App:     http://localhost:${HTTP_PORT}
  Mailpit: http://localhost:${MAILPIT_PORT}
  Postgres: 127.0.0.1:${POSTGRES_PORT}  (user/pass from .env or defaults)

Sass watcher is running in the 'node' service. Tail it with:
  docker compose logs -f node
EOF

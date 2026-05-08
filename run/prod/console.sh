#!/usr/bin/env bash
# Run a Symfony console command inside the prod php container.
#
# Examples:
#   bash ./run/prod/console.sh doctrine:migrations:status
#   bash ./run/prod/console.sh cache:clear
#   bash ./run/prod/console.sh app:create-admin
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$HERE"

EXEC_FLAGS=()
if [[ ! -t 0 || ! -t 1 ]]; then
    EXEC_FLAGS+=(-T)
fi

exec docker compose --env-file .env exec "${EXEC_FLAGS[@]}" php php bin/console "$@"

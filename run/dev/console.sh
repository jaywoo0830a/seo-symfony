#!/usr/bin/env bash
# Run a Symfony console command inside the dev php container.
#
# Examples:
#   bash ./run/dev/console.sh doctrine:fixtures:load -n
#   bash ./run/dev/console.sh app:seed:demo --reset
#   bash ./run/dev/console.sh debug:router
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$HERE"

EXEC_FLAGS=()
if [[ ! -t 0 || ! -t 1 ]]; then
    EXEC_FLAGS+=(-T)
fi

exec docker compose exec "${EXEC_FLAGS[@]}" php php bin/console "$@"

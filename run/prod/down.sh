#!/usr/bin/env bash
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$HERE"

PURGE=0
for arg in "$@"; do
    case "$arg" in
        --purge|-p)
            PURGE=1
            ;;
        -h|--help)
            cat <<'EOF'
Usage: down.sh [--purge]

  (no flag)   Stop and remove containers + network. Volumes preserved
              (database_data, php_cache, caddy_data, caddy_config).
  --purge     Also delete all volumes. Destructive — wipes the database
              AND Caddy's Let's Encrypt cert cache (next start will re-issue).
EOF
            exit 0
            ;;
        *)
            echo "✗ Unknown flag: $arg (try --help)" >&2
            exit 1
            ;;
    esac
done

if [[ $PURGE -eq 1 ]]; then
    echo "⚠ --purge: deletes database AND Caddy cert cache. Press Ctrl+C in 5s to abort."
    sleep 5
    docker compose --env-file .env down --volumes --remove-orphans
    echo "✓ Stack and volumes removed."
else
    docker compose --env-file .env down --remove-orphans
    echo "✓ Stack stopped. Volumes preserved (run with --purge to delete them)."
fi

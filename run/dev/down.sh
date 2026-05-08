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

  (no flag)   Stop and remove containers + network. Volumes (DB, composer cache) preserved.
  --purge     Also delete database_data and composer_cache volumes. Destructive.
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
    echo "⚠ --purge: this will delete the dev database volume. Press Ctrl+C in 5s to abort."
    sleep 5
    docker compose down --volumes --remove-orphans
    echo "✓ Stack and volumes removed."
else
    docker compose down --remove-orphans
    echo "✓ Stack stopped. Volumes preserved (run with --purge to delete them)."
fi

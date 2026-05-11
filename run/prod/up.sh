#!/usr/bin/env bash
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$HERE"

if [[ ! -f .env ]]; then
    echo "✗ run/prod/.env is missing." >&2
    echo "  Copy run/prod/.env.example → run/prod/.env and fill in:" >&2
    echo "    APP_SECRET, POSTGRES_PASSWORD, DOMAIN" >&2
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "✗ Docker daemon is not reachable. Start Docker Desktop / dockerd first." >&2
    exit 1
fi

set -a; . ./.env; set +a
HTTP_PORT="${HTTP_PORT:-80}"
HTTPS_PORT="${HTTPS_PORT:-443}"
DOMAIN="${DOMAIN:-localhost}"

if [[ "$DOMAIN" != "localhost" && -z "${ACME_EMAIL:-}" ]]; then
    echo "ℹ ACME_EMAIL is empty. Let's Encrypt will still issue a cert, but expiry"
    echo "  warnings will not be deliverable. Recommended for real domains."
    echo
fi

echo "▶ Building images (caddy + php)…"
docker compose --env-file .env build

echo "▶ Bringing up: database → php → caddy"
docker compose --env-file .env up -d

echo "▶ Waiting for php-fpm to become healthy…"
for _ in {1..30}; do
    state="$(docker compose ps --format json php 2>/dev/null | grep -oE '"Health":"[^"]*"' | head -1 | cut -d'"' -f4 || true)"
    [[ "$state" == "healthy" ]] && break
    sleep 2
done

echo "▶ Running migrations (idempotent)"
docker compose exec -T php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo
docker compose ps
echo
if [[ "$DOMAIN" == "localhost" ]]; then
    cat <<EOF
✓ Ready.
  HTTP:  http://localhost:${HTTP_PORT}/   (auto-redirects to HTTPS)
  HTTPS: https://localhost:${HTTPS_PORT}/ (Caddy local-CA cert — browser will warn until you trust it)
EOF
else
    cat <<EOF
✓ Ready.
  HTTPS: https://${DOMAIN}/
  Cert : Let's Encrypt — Caddy provisions on first request and auto-renews.
         (DNS for ${DOMAIN} must resolve to this host; ports 80/443 must be public.)
EOF
fi

# ─── 첫 배포 안내 ────────────────────────────────────────────────────────────
# Phase 0 시드(theme/region/author)는 migration 이 자동 처리. 어드민 계정 생성과
# Author 정보 교체만 수동. admin_user 테이블이 비어 있을 때만 안내 출력.
ADMIN_COUNT="$(docker compose exec -T -e PGPASSWORD="$POSTGRES_PASSWORD" database \
    psql -U "${POSTGRES_USER:-app}" -d "${POSTGRES_DB:-app}" -tAc \
    "SELECT COUNT(*) FROM admin_user" 2>/dev/null || echo "0")"

if [[ "${ADMIN_COUNT//[[:space:]]/}" == "0" ]]; then
    cat <<'EOF'

──────────────────────────────────────────────────────────────────────────
  첫 배포 — 다음 두 단계를 실행하세요:

  1) 운영 어드민 계정 생성 (이메일 + 강력한 비밀번호)
     bash run/prod/console.sh app:admin:create me@example.com 'StrongPassword'

  2) Author placeholder 본인 정보로 교체 + verify (브라우저)
     /admin/authors/1/edit   →  본인 실명·경력·bio 입력
     /admin/authors/1/verify

  Phase 0 시드(theme 4 + region 49 + author placeholder)는 이미 migration
  으로 자동 적용됨. fixtures:load 는 더 이상 필요 없음.
──────────────────────────────────────────────────────────────────────────
EOF
fi

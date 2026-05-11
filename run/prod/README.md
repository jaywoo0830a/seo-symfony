# Production Deployment

Caddy + PHP-FPM + PostgreSQL 스택. multi-stage Dockerfile + 자동 SSL.

## 사전 준비

- 서버: Ubuntu 22.04 LTS 권장. Docker + Docker Compose v2.
- 도메인: A/AAAA 레코드를 서버 IP로. 80/443 외부 공개.
- 코드: `git clone` 완료된 상태.

```bash
# Docker 미설치 시
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER && newgrp docker
```

## 첫 배포 (1회만)

### 1. 환경 변수 작성

```bash
cd run/prod
cp .env.example .env
chmod 600 .env
vi .env
```

채워야 할 값:

| 변수 | 값 | 비고 |
|---|---|---|
| `APP_SECRET` | `openssl rand -hex 32` 출력값 | 강력 랜덤 |
| `POSTGRES_PASSWORD` | 강력 비밀번호 | DB 자체 + DATABASE_URL 둘 다 |
| `DOMAIN` | 서비스 도메인 (e.g. `example.com`) | DNS A 레코드 미리 설정 |
| `ACME_EMAIL` | 본인 이메일 | Let's Encrypt 만료 알림 |
| `DEFAULT_URI` | `https://<DOMAIN>` | URL 절대 경로 생성용 |
| `BRAND_PHONE` | 대표 전화번호 | 사이트 노출. 더미 채로 배포 X |
| `BRAND_PHONE_HOURS` | 실제 운영 시간 | final CTA hint |

### 2. 스택 기동

프로젝트 루트에서:

```bash
bash run/prod/up.sh
```

자동 처리:
- Docker 이미지 빌드 (PHP-FPM + Caddy + Postgres + node sass)
- 컨테이너 기동 (database → php → caddy)
- `composer install --no-dev --optimize-autoloader`
- `doctrine:migrations:migrate`
- SCSS → CSS 빌드 (Dockerfile node stage 안에서)
- `cache:warmup --env=prod`
- Caddy 자동 SSL 발급 (DNS + 80/443 공개되어 있으면 첫 요청 시 ACME challenge 통과)

### 3. 운영 어드민 계정 생성

```bash
bash run/prod/console.sh app:admin:create me@example.com 'StrongPass'
```

Phase 0 시드(theme + region + author placeholder)는 migration 이 자동 처리. fixtures:load 명령 *불필요*.

### 4. Author 정보 교체

브라우저에서 `https://<DOMAIN>/admin/authors/1/edit`:
- 본인 실명·경력·bio·photo_url 입력
- `/admin/authors/1/verify` 클릭

`verified_at` 채워지기 전엔 publish gate가 모든 콘텐츠를 거부.

### 5. 첫 콘텐츠 발행

`/admin/nodes/new` → 첫 가이드 또는 매트릭스 노드 작성. publish gate 통과 후 `status=live`.

---

## 운영 중 — 업데이트

```bash
bash run/prod/down.sh        # 컨테이너 정지 (DB 보존)
git pull                     # 코드 업데이트
bash run/prod/up.sh          # 재빌드 + migrate + 기동
```

`up.sh`가 idempotent — composer/migration/cache/assets 모두 자동 재처리.

---

## 명령 레퍼런스

| 명령 | 설명 |
|---|---|
| `bash run/prod/up.sh` | 빌드 + 기동 + migrate + cache. 첫 배포 + 업데이트 공통 |
| `bash run/prod/down.sh` | 컨테이너 정지. DB 볼륨 보존 |
| `bash run/prod/down.sh --purge` | **위험** — DB + Caddy cert cache 모두 삭제 |
| `bash run/prod/console.sh <cmd>` | `php bin/console <cmd>` 실행 (예: `cache:clear`) |

자주 쓰는 console 명령:

```bash
# DB 마이그레이션 상태
bash run/prod/console.sh doctrine:migrations:status

# 캐시 클리어
bash run/prod/console.sh cache:clear

# 운영 어드민 추가 / 비밀번호 변경
bash run/prod/console.sh app:admin:create email@example.com 'NewPass'

# sitemap.xml 수동 재생성 (cron 자동 실행 권장)
# (현재 sitemap은 SitemapController가 SELECT live 그대로 출력 — 재생성 불필요)
```

---

## 트러블슈팅

### Caddy SSL 발급 실패

- `bash run/prod/console.sh dbal:run-sql "SELECT 1"` 으로 컨테이너 살아있는지 확인
- `docker compose --env-file run/prod/.env -f run/prod/compose.yaml logs caddy` — ACME 에러 확인
- DNS 전파: `dig <DOMAIN>` 으로 서버 IP 확인
- 80/443 포트가 외부 접근 가능한지 (`curl http://<DOMAIN>`)

### `permission denied` (PHP 컨테이너 var/cache)

```bash
docker compose --env-file run/prod/.env -f run/prod/compose.yaml \
    exec php sh -c 'chown -R www-data:www-data /var/www/html/var'
```

### `up.sh` 마지막 단계에서 멈춤 (DB healthy 안 됨)

```bash
docker compose --env-file run/prod/.env -f run/prod/compose.yaml logs database
```
보통 첫 기동에서 30초~1분 소요. up.sh가 30회 × 2초 = 60초 대기.

---

## 백업

매일 자정 cron 권장:

```bash
# /etc/cron.d/app-backup
0 0 * * * root docker compose --env-file /opt/app/run/prod/.env \
    -f /opt/app/run/prod/compose.yaml \
    exec -T database pg_dump -U app app | \
    gzip > /var/backups/app-$(date +\%Y\%m\%d).sql.gz
```

`/var/backups/`는 외부 스토리지(S3 등)에 동기화 권장.

---

## 절대 하지 말 것

- ❌ `down.sh --purge` — DB 볼륨 삭제 (운영 데이터 모두 사라짐)
- ❌ `BRAND_PHONE`을 더미값(`010-XXXX-XXXX`)으로 둔 채 배포 — 문의 전화 연결 끊김
- ❌ `.env` 파일 git commit — 비밀번호 노출
- ❌ migration 적용된 후 `doctrine:fixtures:load` 시도 — purge 발동 위험
  (참고: 현재 prod 빌드엔 fixtures bundle 미설치라 명령 자체가 작동 안 함)

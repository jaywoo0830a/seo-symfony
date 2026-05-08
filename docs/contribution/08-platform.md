# 08. 플랫폼 — 보안 / 캐시 / 시드

## 1. 보안 / 인증

[config/packages/security.yaml](../../config/packages/security.yaml):

| 설정 | 위치 | 기본값 |
|---|---|---|
| 로그인 시도 제한 | `firewalls.main.login_throttling` | 5회/분 |
| Remember me 유효기간 | `firewalls.main.remember_me.lifetime` | 30일 |
| 접근 제어 | `access_control` | `/admin` → `ROLE_ADMIN` |

### 관리자 계정 생성

```bash
php bin/console app:admin:create <email> <password>
```

운영 전환 시:

- `APP_SECRET` 강력한 랜덤 값으로 (`.env.local` 또는 `.env.prod.local`)
- HTTPS 강제 (`framework.session.cookie_secure: true` 등)
- 비밀번호 해셔는 Symfony 디폴트(argon2id) 사용 — 별도 설정 X

## 2. 공개 폼 보안 (CTA)

CTA 폼에 적용된 보호 ([03-cta-system.md §보안--스팸](03-cta-system.md#6-보안--스팸) 상세):

| 보호 | 비고 |
|---|---|
| CSRF 토큰 | Symfony Form 자동 (`csrf_protection: true`) |
| Honeypot | `hp` 필드 + `display:none` 격리 |
| `noindex` thank-you | `X-Robots-Tag: noindex, nofollow` |
| 동의 체크박스 | 미동의 시 검증 실패 |
| **Rate limit** | **TODO — 운영 전 추가** |

### Rate limit 추가 (운영 전 필수)

[symfony/rate-limiter](https://symfony.com/doc/current/rate_limiter.html)는 이미 composer.json에 있음. 설정:

```yaml
# config/packages/rate_limiter.yaml (신규)
framework:
    rate_limiter:
        cta_submit:
            policy: 'sliding_window'
            limit: 3
            interval: '1 minute'
```

[CtaSubmitController](../../src/Controller/Public/CtaSubmitController.php)에서 IP별 consume 후 거절 처리.

## 3. 캐시 / 성능

### HTTP 캐시 헤더

[PublicNodeController](../../src/Controller/Public/PublicNodeController.php):

```php
$response->setMaxAge(300);          // 브라우저 5분
$response->setSharedMaxAge(1800);   // CDN 30분
$response->setLastModified($node->getLastReviewAt() ?? $node->getFirstPublishedAt());
```

`If-Modified-Since` 헤더로 304 응답 처리.

### 무효화 트리거

`last_review_at` 변경 = 캐시 무효화. 운영자가 데이터 편집 후 명시적으로 갱신해야 변경 반영:

```sql
UPDATE content_node SET last_review_at = NOW() WHERE status = 'live';
```

자세한 캐시/SCSS 시나리오: [06-brand-and-design.md §캐시-무효화](06-brand-and-design.md#5-캐시-무효화--scss-변경이-안-보일-때).

### sitemap.xml

[SitemapController](../../src/Controller/Public/SitemapController.php) — `WHERE status='live'` SELECT 결과를 그대로 출력.

- 자동: cron 매일 03:00 재생성 권장 (외부 스케줄러로)
- Search Console ping 자동 (운영 단계에서 별도 설정)

## 4. 시드 / 샘플 데이터

[SeedDemoCommand](../../src/Command/SeedDemoCommand.php) — `app:seed:demo [--reset]`.

### 현재 시드되는 17개 페이지

- 1 루트 (`/`)
- 4 테마 허브 (tutoring/academy/contents/guides) + reports/cases는 자동 생성
- 9 지역 매트릭스 (테마 3 × 시도 17 × 일부 시군구)
- 2 가이드 (`/guides/how-to-choose-tutor/`, `/guides/tutoring-vs-academy/`)
- 2 에세이 (`/contents/study-routine-design/`, `/contents/online-vs-offline-learning/`)
- 1 리포트 (`/reports/quarterly-tutoring-rates-2026q1/`)
- 1 사례 (`/cases/middle-school-math-routine/`)

### 도메인 변경 시 손볼 곳

- `buildIntro()`, `buildDataPoints()` — 한국어 카피와 임의 수치
- `GUIDE_VARIANTS`, `ESSAY_DEMOS`, `REPORT_VARIANTS`, `CASE_STUDY_VARIANTS` — 데모 콘텐츠 정의
- `MATRIX_EXCLUDED_SLUGS` — 지역 매트릭스 갖지 않을 테마 slug

### 5초 클린 복원 (개발 단계)

```bash
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
php bin/console app:seed:demo
```

이 워크플로우가 시드 게이트 디버깅 시 가장 빠릅니다.

### 시드가 게이트에 막힐 때

```
개발 단계: 위 5초 워크플로우로 리셋

운영 단계:
  - 시드 데이터의 본문 길이 확인 (visible_len, 공백 정규화 후)
  - DataPoint 수 확인
  - 시드 코드의 게이트 요건 확인
```

자세한 게이트 실패 트러블슈팅: [05-themes-and-content.md §게이트-통과-못-할-때](05-themes-and-content.md#4-게이트-통과-못-할-때).

## 5. 마이그레이션 운영

### 새 마이그레이션 생성

```bash
php bin/console make:migration
```

엔티티 변경 후 호출하면 diff를 자동 감지. PostgreSQL 함수/트리거 변경은 수동 작성 (예: `fn_publish_gate` 변경).

### 운영 환경 마이그레이션

```bash
php bin/console doctrine:migrations:migrate --env=prod --no-interaction
```

- **down() 메서드**: 개발 단계에선 비워둬도 OK. 운영에선 작성 권장
- **데이터 마이그레이션**: 함수 변경 후 row 업데이트 시 *함수 먼저 → row 나중* 순서 ([04-content-types.md §게이트-변경](04-content-types.md#4-게이트-변경))

## 6. 로그

`var/log/dev.log` — 개발 환경 로그. CTA 스텁 모드도 여기에 기록:
```
CTA[telegram:stub] form=consult data={...}
```

운영 환경: `var/log/prod.log` 또는 외부 로그 수집기 (Sentry 등)로 forward.

## 관련 문서

- 시스템 전제 (왜 게이트가 필요한가): [docs/dev/03-publish-gate-design.md](../dev/03-publish-gate-design.md)
- 결정 룰: [09-decision-rules.md](09-decision-rules.md)

# 06. 브랜드 + 디자인

## 1. 브랜드 식별값 (`.env`)

`.env`의 `BRAND_*` 항목:

| 변수 | 용도 | 노출 위치 |
|---|---|---|
| `BRAND_ADMIN_NAME` | 관리자 페이지 이름 | [dashboard/layout.html.twig](../../templates/dashboard/layout.html.twig), [security/login.html.twig](../../templates/security/login.html.twig) |
| `BRAND_PUBLIC_NAME` | 공개 사이트 이름 | [public/layout.html.twig](../../templates/public/layout.html.twig), [public/home.html.twig](../../templates/public/home.html.twig) |
| `BRAND_PHONE` | 메인 CTA 전화번호 | 공개 페이지 모든 전화 버튼 (CTA 시스템이 phone type payload로 사용) |
| `BRAND_PHONE_HOURS` | 전화 운영시간 | footer + final CTA hint |

값은 [PublicNavExtension](../../src/Twig/PublicNavExtension.php)이 주입받아 `brand_admin_name()`, `brand_public_name()`, `brand_phone()`, `brand_phone_link()`, `brand_phone_hours()` Twig 함수로 노출.

### 새 브랜드 변수 추가하기

1. `.env`에 `BRAND_X` 추가
2. [config/services.yaml](../../config/services.yaml) `_defaults.bind`에 `string $brandX: '%env(BRAND_X)%'` 추가
3. `PublicNavExtension` 생성자에 `private readonly string $brandX` + `#[AsTwigFunction('brand_x')]` 메서드

## 2. CTA 알림 채널 (`.env`)

CTA 폼 제출은 외부 채널로 forward됩니다. 비어있으면 logger 스텁 모드:

| 변수 | 용도 | 비고 |
|---|---|---|
| `CTA_TELEGRAM_BOT_TOKEN` | 텔레그램 봇 토큰 | BotFather에서 발급 |
| `CTA_TELEGRAM_CHAT_ID` | 알림 받을 chat_id | 위 §1 참고 |
| `CTA_DISCORD_WEBHOOK_URL` | 디스코드 웹훅 URL | 채널 설정 → 연동 → 웹훅 |

자세한 CTA 시스템과 새 채널 추가 방법: [03-cta-system.md](03-cta-system.md).

## 3. 디자인 토큰

| 위치 | 대상 |
|---|---|
| [assets/scss/brand/_tokens.scss](../../assets/scss/brand/_tokens.scss) | 공개 사이트 (브랜드 컬러, 간격, 타이포) |
| [assets/scss/_tokens.scss](../../assets/scss/_tokens.scss) | 관리자 (별도) |

공개 사이트 토큰은 `--b-` 프리픽스로 CSS 변수 출력 (예: `--b-cta`, `--b-bg-dark`, `--b-space-16`, `--b-text-18`).

수정 후 `npm run build`로 `assets/styles/{app,brand}.css` 재빌드.

### 브랜드 컬러 변경 예시

`_tokens.scss`의 `$brand` 맵에서 색 변경 → 빌드. 모든 `.cta--primary`, hover 상태 등이 일괄 적용됩니다.

## 4. SCSS 빌드 체계

### 두 엔트리 포인트

| 엔트리 | 출력 | 용도 |
|---|---|---|
| [assets/scss/app.scss](../../assets/scss/app.scss) | `assets/styles/app.css` | 관리자 |
| [assets/scss/brand/app.scss](../../assets/scss/brand/app.scss) | `assets/styles/brand.css` | 공개 사이트 |

### 빌드 명령

```bash
npm install         # 1회만
npm run build       # 1회 빌드
npm run watch       # 변경 감지 + 자동 빌드
npm run build:prod  # minified
```

### 새 컴포넌트 추가

`assets/scss/brand/_my-component.scss` 신규 → [brand/app.scss](../../assets/scss/brand/app.scss)에 `@use 'my-component';` 추가 → 빌드.

## 5. 캐시 무효화 — SCSS 변경이 안 보일 때

SCSS 변경했는데 안 보이면 다음을 순서대로:

```bash
npm run build
rm -rf public/assets
php bin/console cache:clear --no-warmup
```

브라우저 캐시 무효화도 필요 — `last_review_at` 갱신:
```sql
UPDATE content_node SET last_review_at = NOW() WHERE status = 'live';
```

이유: HTTP `Last-Modified` 헤더가 `last_review_at` 기반. 데이터가 안 바뀌면 304 Not Modified로 응답이 가서 새 CSS도 반영 안 보일 수 있어요.

자세한 캐시 동작: [08-platform.md §캐시--성능](08-platform.md#캐시--성능).

## 6. 폰트

[layout.html.twig](../../templates/public/layout.html.twig)에서 Google Fonts (Asta Sans) 로드. 변경하려면 `<link rel="stylesheet">` 교체 + 토큰의 `font-family` 변수 업데이트.

## 7. 자산 매퍼 (Asset Mapper)

이 프로젝트는 Symfony Asset Mapper를 사용합니다 — webpack/Vite 같은 번들러 없이도 fingerprinted URL과 importmap 지원.

- 설정: [config/packages/asset_mapper.yaml](../../config/packages/asset_mapper.yaml)
- importmap: [importmap.php](../../importmap.php)
- 캐시 디렉토리: `public/assets/` (`.gitignore`)
- SCSS는 직접 컴파일 (asset-mapper는 `.scss` 자체를 서빙하지 않음 — `excluded_patterns`로 제외)

## 관련 문서

- CTA 시스템 (브랜드 CTA의 정책 결정): [03-cta-system.md](03-cta-system.md)
- 다른 국가/도메인 입양: [07-localization.md](07-localization.md)

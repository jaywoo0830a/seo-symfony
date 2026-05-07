# Customization Guide

새 프로젝트에 이 코드베이스를 입양할 때 **어디를 바꾸면 되는지** 정리한 문서입니다.

---

## 1. 브랜드 식별값 (가장 자주 바꿈)

`.env` 파일의 `BRAND_*` 항목.

| 변수 | 용도 | 노출 위치 |
|---|---|---|
| `BRAND_ADMIN_NAME` | 관리자 페이지 이름 | `templates/dashboard/layout.html.twig`, `templates/security/login.html.twig` |
| `BRAND_PUBLIC_NAME` | 공개 사이트 이름 | `templates/public/layout.html.twig`, `templates/public/home.html.twig` |
| `BRAND_PHONE` | 메인 CTA 전화번호 | 공개 페이지 모든 전화 버튼 |
| `BRAND_PHONE_HOURS` | 전화 운영시간 | footer + 랜딩 페이지 final CTA |

값은 [src/Twig/PublicNavExtension.php](src/Twig/PublicNavExtension.php)가 주입받아 `brand_admin_name()`, `brand_public_name()`, `brand_phone()`, `brand_phone_link()`, `brand_phone_hours()` Twig 함수로 노출.

추가 브랜드 값을 넣고 싶으면:
1. `.env`에 `BRAND_X` 추가
2. [config/services.yaml](config/services.yaml) `_defaults.bind`에 `string $brandX: '%env(BRAND_X)%'` 추가
3. `PublicNavExtension` 생성자에 `private readonly string $brandX` 추가 + `#[AsTwigFunction('brand_x')]` 메서드 작성

---

## 2. 컬러 / 디자인 토큰

| 위치 | 대상 |
|---|---|
| [assets/scss/brand/_tokens.scss](assets/scss/brand/_tokens.scss) `$brand:` 맵 | **공개 사이트** 브랜드 컬러 (현재 파란색 `#2563eb`) |
| [assets/scss/_tokens.scss](assets/scss/_tokens.scss) | **관리자** 컬러 / 폰트 / 간격 토큰 |

수정 후 `npm run build`로 `assets/styles/{app,brand}.css` 재빌드.

---

## 3. 도메인 데이터 (테마 / 지역)

### 테마 세트
[src/DataFixtures/ThemeFixtures.php](src/DataFixtures/ThemeFixtures.php) — 현재는 한국 교육 시장 가정 (`과외`, `학원`, `학습 콘텐츠`, `가이드`).

### 지역 트리
[src/DataFixtures/RegionFixtures.php](src/DataFixtures/RegionFixtures.php) — **한국 행정구역(시도→시군구→동) 트리**. 다른 국가용으로 입양하려면 이 파일 통째로 교체.

연관: [src/Entity/Enum/BodyTemplate.php](src/Entity/Enum/BodyTemplate.php)는 `Hub/Sido/Sigungu/Dong` 4단계 가정.

### URL → 템플릿 매핑
[src/Controller/Public/PublicNodeController.php](src/Controller/Public/PublicNodeController.php) `deriveTemplate()` — region depth (0/1/2/3) → BodyTemplate 매핑. 행정구역 단계가 다르면 여기를 같이 수정.

---

## 4. 보안 / 인증

[config/packages/security.yaml](config/packages/security.yaml):

| 설정 | 위치 | 기본값 |
|---|---|---|
| 로그인 시도 제한 | `firewalls.main.login_throttling` | 5회 / 1분 |
| Remember me 유효기간 | `firewalls.main.remember_me.lifetime` | 2592000초 (30일) |
| 접근 제어 경로 | `access_control` | `/admin` → `ROLE_ADMIN` |

관리자 계정 생성: `php bin/console app:admin:create <email> <password>`

---

## 5. 캐시 / 성능

[src/Controller/Public/PublicNodeController.php](src/Controller/Public/PublicNodeController.php) 내 `$response->setMaxAge(300)` (브라우저 5분) / `setSharedMaxAge(1800)` (CDN 30분). 트래픽·갱신 빈도에 맞게 조정.

---

## 6. 공개 랜딩 페이지 카피

[templates/public/home.html.twig](templates/public/home.html.twig), [templates/public/node.html.twig](templates/public/node.html.twig) — 헤드라인, 섹션 본문, FAQ 기본 문구. 한국어 카피 가정. 다국어 지원이 필요하면 `translations/`로 추출.

---

## 7. 데모 시드 / 샘플 데이터

[src/Command/SeedDemoCommand.php](src/Command/SeedDemoCommand.php) `app:seed:demo [--reset]` — 4 테마 × (허브 + 17 시도 + 5 서울 자치구) = 92 노드 생성. `buildIntro()`, `buildDataPoints()`의 한국어 카피와 임의 수치를 도메인에 맞게 교체.

---

## 첫 입양 체크리스트

1. `.env.local`에 `BRAND_*` 4개 + `DATABASE_URL` 본인 값으로 덮어쓰기
2. 컬러 변경 → `npm run build`
3. `php bin/console doctrine:migrations:migrate`
4. `php bin/console doctrine:fixtures:load` (Theme / Region / Author)
5. `php bin/console app:admin:create me@example.com <pw>`
6. (선택) `php bin/console app:seed:demo --reset`로 데모 콘텐츠 채움
7. 도메인이 한국이 아니면 §3의 지역/테마 fixtures 교체

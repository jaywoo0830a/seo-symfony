# Customization Guide

이 코드베이스를 새 프로젝트에 입양하거나 확장할 때 **어디를 어떻게 바꾸는지** 정리한 문서. 사람과 AI 모두를 독자로 가정하므로 *결정 규칙*과 *파일 경로*를 명시한다.

---

## 0. Quick Start — 5분 입양

```bash
# 1. 환경 변수 설정 (브랜드명/전화번호/DB)
cp .env .env.local && vi .env.local

# 2. DB 초기화 + 시드
php bin/console doctrine:database:drop --force --if-exists
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
php bin/console app:admin:create me@example.com <password>
php bin/console app:seed:demo

# 3. 자산 빌드 + 서버
npm install && npm run build
php -S 127.0.0.1:8000 -t public
```

이렇게 하면 17개 데모 페이지가 뜨는 완성된 사이트가 된다.

---

## 1. 페이지 렌더링 파이프라인 — 핵심 개념

### 1.1 페이지 4가지 유형

URL이 들어오면 다음 중 하나로 분류된다:

| 유형 | URL 패턴 | 좌표 조건 | 컨트롤러 |
|---|---|---|---|
| **루트** | `/` | 없음 | [PublicHomeController](src/Controller/Public/PublicHomeController.php) |
| **테마 허브** | `/{theme-slug}/` | `region IS NULL` AND `theme.parent IS NULL` | [PublicNodeController](src/Controller/Public/PublicNodeController.php) |
| **지역 페이지** | `/{theme}/{sido}/.../` | `region IS NOT NULL` | [PublicNodeController](src/Controller/Public/PublicNodeController.php) |
| **자식 테마 페이지** | `/{parent}/{child}/` | `region IS NULL` AND `theme.parent IS NOT NULL` | [PublicNodeController](src/Controller/Public/PublicNodeController.php) |

`PublicNodeController`는 모든 비-루트 URL을 받고, 좌표(theme + region)를 [PathResolver](src/Service/PathResolver.php)로 ContentNode 행으로 매핑한다. 매칭되는 ContentNode가 없으면 404, `status='dead'`이면 [Redirect](src/Entity/Redirect.php) 테이블 조회 후 301.

### 1.2 디스패처 (외형 결정)

[node.html.twig](templates/public/node.html.twig)는 *분기 로직만 담는 디스패처*다. 어떤 템플릿으로 렌더할지 다음 우선순위로 결정한다:

```
1. template_override 파일 존재?    → 사용 (모든 자동 분기 무시)
   templates/public/hubs/{theme-path}.html.twig
   조건: region IS NULL (지역 페이지는 절대 오버라이드 불가)

2. body_template == guide_*?       → _guide.html.twig
3. body_template == essay?         → _essay.html.twig
4. body_template == report?        → _report.html.twig
5. body_template == case_study?    → _case_study.html.twig

6. 그 외 (지역 페이지 + 오버라이드 없는 매트릭스 허브) → matrix_template
   matrix_template = templates/public/matrix/{root-theme-slug}.html.twig
                     (없으면 templates/public/_matrix.html.twig 제네릭 fallback)
```

이 우선순위가 **시스템 전체의 외형 통제 규칙**이다. 외우거나 적어둘 가치가 있다.

### 1.3 템플릿 컨텍스트 (모든 템플릿이 받는 변수)

`PublicNodeController::buildContext()`가 주입:

| 변수 | 타입 | 설명 |
|---|---|---|
| `node` | [ContentNode](src/Entity/ContentNode.php) | 현재 페이지의 노드 |
| `url` | string | 정규 URL |
| `h1` | string | 타이틀용 키워드 (지역명 + 테마명) |
| `template` | [BodyTemplate](src/Entity/Enum/BodyTemplate.php) | 결정된 템플릿 enum |
| `body_html` | string\|null | Markdown→HTML (prose 변종에서만) |
| `template_override` | string\|null | 매칭된 오버라이드 파일 경로 |
| `matrix_template` | string | 매트릭스 셸 경로 (테마 또는 fallback) |
| `byKind` | array | DataPoint를 kind별로 그룹화 (verified만) |
| `parent`, `siblings`, `children` | ContentNode\|list | 지역 트리 탐색 |
| `theme_children`, `theme_siblings` | list | 테마 트리 탐색 |
| `urls` | [UrlBuilder](src/Service/UrlBuilder.php) | URL 생성 헬퍼 |
| `crumbs` | list | 빵 부스러기 |
| `json_ld`, `breadcrumbs_jsonld` | string | Schema.org JSON-LD |

오버라이드 파일은 이 컨텍스트를 그대로 받는다. 추가 변수가 필요하면 `buildContext()`를 확장.

---

## 2. 오버라이드 메커니즘 — 5단계 자율도

자율도가 가장 큰 것부터:

### 2.1 단일 페이지 오버라이드 (가장 자유)

**경로**: `templates/public/hubs/{theme-path}.html.twig`

`{theme-path}`는 URL의 테마 부분과 동일. 예:
- `/tutoring/` → `templates/public/hubs/tutoring.html.twig`
- `/guides/how-to-choose-tutor/` → `templates/public/hubs/guides/how-to-choose-tutor.html.twig`
- `/reports/quarterly-tutoring-rates-2026q1/` → `templates/public/hubs/reports/quarterly-tutoring-rates-2026q1.html.twig`

**제약**: `region IS NULL`인 노드에만 적용. 즉 *지역 페이지(`/tutoring/seoul/`)는 절대 오버라이드 불가*. 이는 의도된 제한 — 수백~수천 개 지역 페이지의 균일성이 SEO 자산이기 때문.

**자율도**: HTML/CSS 100% 자유. 컨텍스트(`node`, `byKind`, `body_html` 등)는 그대로 받지만 *어떻게 렌더할지는 운영자 마음대로*.

**예시**:
```twig
{# templates/public/hubs/my-special-page.html.twig #}
{% include 'public/_partials/breadcrumb.html.twig' %}

<section class="hub-splash">
    <h1>{{ h1 }}</h1>
    {% if body_html %}{{ body_html|raw }}{% endif %}
</section>

{% include 'public/_partials/final_cta.html.twig' with {
    title: '여기서 결정하세요',
} only %}
```

작동 원리는 [PublicNodeController::findTemplateOverride()](src/Controller/Public/PublicNodeController.php) 참고.

### 2.2 테마 단위 매트릭스 셸

**경로**: `templates/public/matrix/{root-theme-slug}.html.twig`

매트릭스 페이지(지역 페이지 + 오버라이드 없는 허브)의 셸. 같은 *루트 테마* 내 모든 매트릭스 페이지가 이걸 공유.

**현재 보유**:
- [matrix/tutoring.html.twig](templates/public/matrix/tutoring.html.twig) — 과외 (행동 유도 톤)
- [matrix/academy.html.twig](templates/public/matrix/academy.html.twig) — 학원 (디렉토리 톤)
- [matrix/contents.html.twig](templates/public/matrix/contents.html.twig) — 학습 콘텐츠 (다크 디지털 톤)

**없으면** [_matrix.html.twig](templates/public/_matrix.html.twig) (제네릭 fallback)로 자동 폴백.

**원칙**:
- *테마 안*: 모든 지역 페이지가 같은 셸 (균일성 → SEO 자산)
- *테마 사이*: 셸이 달라야 함 (near-duplicate 방지)

작동 원리는 [PublicNodeController::findMatrixTemplate()](src/Controller/Public/PublicNodeController.php) 참고.

### 2.3 Prose 템플릿 (가이드/에세이/리포트/사례)

**경로**: 고정. body_template 값으로 자동 매핑.

| body_template | 템플릿 |
|---|---|
| `guide_longform`, `guide_comparison`, `guide_faq` | [_guide.html.twig](templates/public/_guide.html.twig) (변종별 내부 분기) |
| `essay` | [_essay.html.twig](templates/public/_essay.html.twig) |
| `report` | [_report.html.twig](templates/public/_report.html.twig) |
| `case_study` | [_case_study.html.twig](templates/public/_case_study.html.twig) |

이 파일을 직접 수정하면 *해당 콘텐츠 타입의 모든 페이지*에 일괄 반영.

### 2.4 파셜 라이브러리

**경로**: `templates/public/_partials/`

재사용 블록. 매트릭스 셸과 오버라이드에서 자유롭게 조합:

| 파셜 | 인자 (모두 선택) |
|---|---|
| [breadcrumb](templates/public/_partials/breadcrumb.html.twig) | `crumbs` |
| [hero](templates/public/_partials/hero.html.twig) | `eyebrow`, `title_main`, `title_highlight`, `title_tail`, `lede`, `cta_label`, `assurance`, `stats` |
| [feature_grid](templates/public/_partials/feature_grid.html.twig) | `eyebrow`, `title`, `lede`, `features` (3-card 기본값) |
| [callouts](templates/public/_partials/callouts.html.twig) | `items` (DataPoint), `eyebrow`, `title` |
| [listing](templates/public/_partials/listing.html.twig) | `items`, `eyebrow`, `title` |
| [theme_children](templates/public/_partials/theme_children.html.twig) | `items`, `urls`, `eyebrow`, `title` |
| [faq](templates/public/_partials/faq.html.twig) | `items`, `eyebrow`, `title`, `alt` |
| [final_cta](templates/public/_partials/final_cta.html.twig) | `eyebrow`, `title`, `lede` |
| [jsonld](templates/public/_partials/jsonld.html.twig) | `article`, `breadcrumbs` |

모든 파셜은 `is defined` 디폴트가 있어서 인자 누락에 안전. `with {...} only`로 호출 권장 (외부 변수 누수 방지).

### 2.5 루트 페이지

**경로**: [templates/public/home.html.twig](templates/public/home.html.twig)

이미 자유 템플릿. 별도 컨트롤러([PublicHomeController](src/Controller/Public/PublicHomeController.php))가 렌더하므로 ContentNode 시스템 밖. 그냥 편집하면 됨.

같은 파셜을 가져다 쓸 수 있다.

---

## 3. 콘텐츠 타입 (BodyTemplate)

### 3.1 변종 카탈로그

[BodyTemplate enum](src/Entity/Enum/BodyTemplate.php) 10개:

| 변종 | 자동/수동 | 발행 게이트 | 톤 |
|---|---|---|---|
| `Hub` | 자동 (region 깊이 0) | data_count ≥ 5 | 매트릭스 |
| `Sido` | 자동 (region 깊이 1) | data_count ≥ 5 | 매트릭스 |
| `Sigungu` | 자동 (region 깊이 2) | data_count ≥ 5 | 매트릭스 |
| `Dong` | 자동 (region 깊이 3) | data_count ≥ 5 | 매트릭스 |
| `GuideLongform` | 수동 | body 3,000자+ AND verified FAQ ≥ 3 | how-to 심층 |
| `GuideComparison` | 수동 | 위와 동일 | how-to 비교 |
| `GuideFaq` | 수동 | 위와 동일 | how-to FAQ 중심 |
| `Essay` | 수동 | body 3,000자+ | 에디토리얼 |
| `Report` | 수동 | body 5,000자+ | 데이터 보고서 |
| `CaseStudy` | 수동 | body 1,500자+ | 사례 연구 |

발행 게이트는 [Version20260507163246](migrations/Version20260507163246.php)의 `fn_publish_gate` PostgreSQL 함수에 정의. 모든 prose 변종은 추가로 `intro_text` 비어있지 않음 + verified author 필수.

### 3.2 자동 vs 수동 — 작동 원리

[PublicNodeController::deriveTemplate()](src/Controller/Public/PublicNodeController.php):

```
if node.bodyTemplate is set explicitly:
    return node.bodyTemplate                  // 운영자 명시 우선
else:
    return match (region.depth):              // 자동 결정 (매트릭스만)
        null/0 → Hub
        1      → Sido
        2      → Sigungu
        3      → Dong
        _      → Hub
```

매트릭스 변종은 region 깊이로 자동 결정되므로 운영자가 직접 설정할 필요 없음. Prose 변종(guide/essay/report/case_study)은 *반드시 명시*해야 함 — 그렇지 않으면 region=null 노드가 자동으로 `Hub`이 되어 매트릭스 게이트를 받음.

### 3.3 게이트 분기 — DB 함수가 강제

`fn_publish_gate` 트리거가 *코드 외부에서* 게이트를 강제. 운영자가 SQL 콘솔에서 직접 INSERT해도 막힌다. 이는 의도된 안전장치 — Month 2~3 충돌 시기에 "AI로 동 페이지 3,500개 한 번에 만들기" 같은 우회를 차단한다.

게이트를 변경하려면 새 마이그레이션으로 함수를 통째로 `CREATE OR REPLACE`. 가장 최근 게이트는 [Version20260507163246](migrations/Version20260507163246.php) 참조.

---

## 4. 새 콘텐츠 타입 추가하기 (확장 패턴)

새 BodyTemplate 변종(예: `Interview`)을 추가하려면 7곳을 만진다. 순서대로:

### 4.1 enum
[src/Entity/Enum/BodyTemplate.php](src/Entity/Enum/BodyTemplate.php):
```php
case Interview = 'interview';

public function isInterview(): bool {
    return $this === self::Interview;
}

public function isProse(): bool {
    return $this->isGuide() || $this->isEssay() || $this->isReport()
        || $this->isCaseStudy() || $this->isInterview();
}
```

### 4.2 마이그레이션 (게이트)
새 마이그레이션 파일 → `fn_publish_gate`의 CASE 문에 추가:
```sql
WHEN 'interview' THEN
    gate_kind := 'interview';
    min_len := 2000;          -- 인터뷰는 Q&A라 짧을 수 있음
    require_faq := FALSE;
```

순서: **함수를 먼저 교체, 그 다음 row 업데이트** (옛 트리거가 새 값을 매트릭스 게이트로 잘못 분류해 강등시키는 함정 방지).

### 4.3 디스패처
[templates/public/node.html.twig](templates/public/node.html.twig):
```twig
{% set is_interview = template.isInterview() %}

...
{% elseif is_interview %}
    {% include 'public/_interview.html.twig' %}
```

### 4.4 템플릿
`templates/public/_interview.html.twig` 신규. 컨텍스트 그대로 받음 (`node`, `byKind`, `body_html`, `theme_siblings` 등).

### 4.5 SCSS
`assets/scss/brand/_interview.scss` 신규 + [app.scss](assets/scss/brand/app.scss)에 `@use 'interview';` 추가.

### 4.6 폼 라벨
[src/Form/ContentNodeType.php](src/Form/ContentNodeType.php) `bodyTemplate`의 `choice_label` match 문에 추가:
```php
BodyTemplate::Interview => '인터뷰 (interview)',
```

### 4.7 시드 (선택)
[src/Command/SeedDemoCommand.php](src/Command/SeedDemoCommand.php) — 데모 데이터를 시드하려면 `INTERVIEW_DEMOS` 상수 + `seedInterviews()` 메소드 추가. 패턴은 `seedEssays()` 참조.

### 가벼운 대안 — enum 안 만지기

새 콘텐츠 *타입*이 시각적으로 비슷하면 enum 안 늘리고 **자식 테마 + 기존 변종**으로 해결:
- `contents` 아래 자식 테마 `parent-tip` 만들고 `body_template = essay` 사용
- 모든 page-level 자유는 `hubs/contents/parent-tip.html.twig` 오버라이드로

**enum 추가가 정당한 경우**: 발행 게이트 규칙이 다를 때, 또는 *셸 자체*가 시각적으로 완전히 달라야 할 때. 그 외엔 자식 테마로 충분.

---

## 5. 새 테마 추가하기

### 5.1 루트 테마 (depth=0)

대시보드 `/admin/themes/new` 또는 [ThemeFixtures](src/DataFixtures/ThemeFixtures.php)에 추가.

루트 테마가 *지역 매트릭스*를 갖지 않아야 한다면 (예: 새 가이드 컨테이너) [SeedDemoCommand::MATRIX_EXCLUDED_SLUGS](src/Command/SeedDemoCommand.php)에 slug 추가.

### 5.2 자식 테마 (depth=1, 가이드/리포트/사례용)

대시보드 `/admin/themes/new` → parent 드롭다운에서 부모 선택 → depth는 자동 계산 ([ThemeNewController:27](src/Controller/ThemeNewController.php#L27)).

URL: `/{parent-slug}/{child-slug}/`로 자동 생성됨.

### 5.3 테마 자식 페이지 자동 노출

`/parent/`에서 자식 카드를 보여주려면 [_partials/theme_children.html.twig](templates/public/_partials/theme_children.html.twig)을 매트릭스 셸이나 오버라이드에 포함. 이미 [matrix/contents.html.twig](templates/public/matrix/contents.html.twig)와 [hubs/guides.html.twig](templates/public/hubs/guides.html.twig)에 들어가 있음.

---

## 6. 운영 워크플로우 (CRUD)

대시보드에서 5개 엔티티 모두 풀 CRUD 지원:

| 엔티티 | URL prefix | 신규 폼 | 비고 |
|---|---|---|---|
| Theme | `/admin/themes` | parent 선택 → depth 자동 | [ThemeType](src/Form/ThemeType.php) |
| Region | `/admin/regions` | 행정구역 트리 | [RegionType](src/Form/RegionType.php) |
| Author | `/admin/authors` | verified는 별도 [verify](src/Controller/AuthorVerifyController.php) | [AuthorType](src/Form/AuthorType.php) |
| ContentNode | `/admin/nodes` | bodyTemplate 드롭다운에 10개 변종 | [ContentNodeType](src/Form/ContentNodeType.php) |
| DataPoint | `/admin/nodes/{nodeId}/datapoints` | 노드 안에서 추가 | [DataPointType](src/Form/DataPointType.php) |

DataPoint URL은 *노드에 nested* — 부모 노드 컨텍스트 안에서만 생성/편집 가능. 대시보드 노드 show 페이지에 "+ 추가" 버튼.

운영용 보조 화면:
- [`/admin/matrix`](http://localhost:8000/admin/matrix) — 매트릭스 ([MatrixController](src/Controller/MatrixController.php))
- `/admin/queue` — 갱신 큐 ([RefreshQueueController](src/Controller/RefreshQueueController.php))
- `/admin/silo` — 사일로 무결성 ([SiloController](src/Controller/SiloController.php))
- `/admin/search` — 콘텐츠 검색 ([AdminSearchController](src/Controller/AdminSearchController.php))

---

## 7. 브랜드 식별값

`.env`의 `BRAND_*` 항목:

| 변수 | 용도 | 노출 위치 |
|---|---|---|
| `BRAND_ADMIN_NAME` | 관리자 페이지 이름 | [dashboard/layout.html.twig](templates/dashboard/layout.html.twig), [security/login.html.twig](templates/security/login.html.twig) |
| `BRAND_PUBLIC_NAME` | 공개 사이트 이름 | [public/layout.html.twig](templates/public/layout.html.twig), [public/home.html.twig](templates/public/home.html.twig) |
| `BRAND_PHONE` | 메인 CTA 전화번호 | 공개 페이지 모든 전화 버튼 |
| `BRAND_PHONE_HOURS` | 전화 운영시간 | footer + final CTA |

값은 [PublicNavExtension](src/Twig/PublicNavExtension.php)이 주입받아 `brand_admin_name()`, `brand_public_name()`, `brand_phone()`, `brand_phone_link()`, `brand_phone_hours()` Twig 함수로 노출.

새 브랜드 변수 추가하려면:
1. `.env`에 `BRAND_X` 추가
2. [config/services.yaml](config/services.yaml) `_defaults.bind`에 `string $brandX: '%env(BRAND_X)%'` 추가
3. `PublicNavExtension` 생성자에 `private readonly string $brandX` + `#[AsTwigFunction('brand_x')]` 메서드

---

## 8. 디자인 토큰

| 위치 | 대상 |
|---|---|
| [assets/scss/brand/_tokens.scss](assets/scss/brand/_tokens.scss) `$brand:` 맵 | 공개 사이트 브랜드 컬러 |
| [assets/scss/_tokens.scss](assets/scss/_tokens.scss) | 관리자 토큰 (별도) |

수정 후 `npm run build`로 `assets/styles/{app,brand}.css` 재빌드.

**중요**: 개발 환경에서 SCSS 변경했는데 안 보이면 `rm -rf public/assets`로 Asset Mapper 캐시 무효화 후 캐시 클리어.

```bash
npm run build
rm -rf public/assets
php bin/console cache:clear --no-warmup
```

브라우저 캐시 무효화도 필요 — `last_review_at` 갱신:
```sql
UPDATE content_node SET last_review_at = NOW() WHERE status = 'live';
```

---

## 9. 도메인 데이터 — 다른 국가/시장으로 입양

[RegionFixtures](src/DataFixtures/RegionFixtures.php)는 한국 행정구역 가정. 다른 국가용으로 바꾸려면 통째로 교체.

깊이 단계가 한국과 다르면 (예: 미국 = 주/카운티 2단계) [BodyTemplate enum](src/Entity/Enum/BodyTemplate.php)의 `Sido/Sigungu/Dong` 변종도 의미가 안 맞을 수 있다. 다음을 같이 손볼 것:

1. [BodyTemplate.php](src/Entity/Enum/BodyTemplate.php) — 변종 이름 변경 또는 단순화
2. [PublicNodeController::deriveTemplate()](src/Controller/Public/PublicNodeController.php) — region.depth → BodyTemplate 매핑

---

## 10. 보안 / 인증

[config/packages/security.yaml](config/packages/security.yaml):

| 설정 | 위치 | 기본값 |
|---|---|---|
| 로그인 시도 제한 | `firewalls.main.login_throttling` | 5회/분 |
| Remember me 유효기간 | `firewalls.main.remember_me.lifetime` | 30일 |
| 접근 제어 | `access_control` | `/admin` → `ROLE_ADMIN` |

관리자 계정 생성: `php bin/console app:admin:create <email> <password>`

---

## 11. 캐시 / 성능

[PublicNodeController](src/Controller/Public/PublicNodeController.php)에서:
- `setMaxAge(300)` — 브라우저 5분
- `setSharedMaxAge(1800)` — CDN 30분
- `setLastModified($node->getLastReviewAt())` — 304 처리

**캐시 무효화 트리거**: `last_review_at` 변경. 시드 후 페이지가 안 보이면 이 컬럼을 NOW()로 갱신해 304 캐시를 깨야 함.

---

## 12. 시드 / 샘플 데이터

[SeedDemoCommand](src/Command/SeedDemoCommand.php) — `app:seed:demo [--reset]`.

**현재 시드되는 17개 페이지**:
- 1 루트 (`/`)
- 4 테마 허브 (tutoring/academy/contents/guides) + reports/cases는 자동 생성
- 9 지역 매트릭스 (테마 3 × 시도 17 × 일부 시군구)
- 2 가이드 (`/guides/how-to-choose-tutor/`, `/guides/tutoring-vs-academy/`)
- 2 에세이 (`/contents/study-routine-design/`, `/contents/online-vs-offline-learning/`)
- 1 리포트 (`/reports/quarterly-tutoring-rates-2026q1/`)
- 1 사례 (`/cases/middle-school-math-routine/`)

도메인 변경 시 손볼 곳:
- `buildIntro()`, `buildDataPoints()` — 한국어 카피와 임의 수치
- `GUIDE_VARIANTS`, `ESSAY_DEMOS`, `REPORT_VARIANTS`, `CASE_STUDY_VARIANTS` — 데모 콘텐츠 정의
- `MATRIX_EXCLUDED_SLUGS` — 지역 매트릭스 갖지 않을 테마 slug

**개발 단계 권장**: `php bin/console doctrine:database:drop --force && create && migrate && fixtures:load && app:seed:demo`로 5초 내 클린 상태 복원.

---

## 13. 첫 입양 체크리스트

1. `.env.local`에 `BRAND_*` 4개 + `DATABASE_URL` 본인 값
2. 컬러 변경 → `npm run build`
3. DB 초기화 + 마이그레이션 + 픽스처 (위 0번 참조)
4. `php bin/console app:admin:create me@example.com <pw>`
5. (선택) `php bin/console app:seed:demo`로 데모 콘텐츠
6. 도메인이 한국이 아니면 §9의 RegionFixtures 교체 + BodyTemplate 매핑 검토
7. 브랜드 톤이 다르면 §1.4 매트릭스 셸 변경 + §2의 오버라이드 추가

---

## 14. 파일 레퍼런스 (요약)

### 진입점
- [src/Controller/Public/PublicHomeController.php](src/Controller/Public/PublicHomeController.php) — 루트 `/`
- [src/Controller/Public/PublicNodeController.php](src/Controller/Public/PublicNodeController.php) — 모든 비-루트 (디스패처 + 컨텍스트)

### 도메인 모델
- [src/Entity/](src/Entity/) — 5개 엔티티 (Theme, Region, Author, ContentNode, DataPoint) + Redirect
- [src/Entity/Enum/BodyTemplate.php](src/Entity/Enum/BodyTemplate.php) — 콘텐츠 타입 10개
- [src/Entity/Enum/ContentStatus.php](src/Entity/Enum/ContentStatus.php) — 4상태 (draft/live/noindex/dead)
- [src/Entity/Enum/DataPointKind.php](src/Entity/Enum/DataPointKind.php) — 5종

### 서비스
- [src/Service/PathResolver.php](src/Service/PathResolver.php) — URL → ContentNode
- [src/Service/UrlBuilder.php](src/Service/UrlBuilder.php) — ContentNode → URL
- [src/Service/BreadcrumbBuilder.php](src/Service/BreadcrumbBuilder.php) — 빵 부스러기

### 템플릿 (공개)
- [templates/public/layout.html.twig](templates/public/layout.html.twig) — 베이스 레이아웃
- [templates/public/home.html.twig](templates/public/home.html.twig) — 루트 페이지
- [templates/public/node.html.twig](templates/public/node.html.twig) — 디스패처
- [templates/public/_matrix.html.twig](templates/public/_matrix.html.twig) — 제네릭 매트릭스 fallback
- [templates/public/_guide.html.twig](templates/public/_guide.html.twig) — 가이드 prose
- [templates/public/_essay.html.twig](templates/public/_essay.html.twig) — 에세이 prose
- [templates/public/_report.html.twig](templates/public/_report.html.twig) — 데이터 리포트
- [templates/public/_case_study.html.twig](templates/public/_case_study.html.twig) — 사례 연구
- [templates/public/_partials/](templates/public/_partials/) — 9개 재사용 블록
- [templates/public/matrix/](templates/public/matrix/) — 테마별 매트릭스 셸
- [templates/public/hubs/](templates/public/hubs/) — 페이지 단위 오버라이드

### 시드 / 마이그레이션
- [src/Command/SeedDemoCommand.php](src/Command/SeedDemoCommand.php) — 데모 데이터 생성
- [src/DataFixtures/](src/DataFixtures/) — Theme/Region/Author 초기 데이터
- [migrations/](migrations/) — DB 스키마 + 트리거 (특히 `fn_publish_gate`)

### SCSS
- [assets/scss/brand/](assets/scss/brand/) — 공개 사이트 (`_tokens.scss` 토큰, 컴포넌트별 분리)
- [assets/scss/](assets/scss/) — 관리자 (별도)

---

## 15. 결정 규칙 요약 (AI 친화적)

이 시스템에서 *어디를 만질지*를 결정하는 규칙들:

```
Q: 한 페이지만 다르게 보이게 하고 싶다.
→ templates/public/hubs/{theme-path}.html.twig 만들기.
   조건: region IS NULL (지역 페이지면 불가능).

Q: 한 테마의 모든 지역 페이지 외형을 바꾸고 싶다.
→ templates/public/matrix/{theme-slug}.html.twig 만들기.

Q: 모든 매트릭스 페이지 외형을 바꾸고 싶다.
→ templates/public/_matrix.html.twig 편집 (또는 _partials/* 직접 수정).

Q: 한 콘텐츠 타입(예: 모든 가이드)의 외형을 바꾸고 싶다.
→ templates/public/_guide.html.twig 편집.

Q: 새 콘텐츠 타입을 추가하고 싶다.
→ §4 확장 패턴 따라 7곳 수정 (enum + 마이그레이션 + 디스패처 + 템플릿 + SCSS + 폼 라벨 + 시드).

Q: 발행 요건을 바꾸고 싶다.
→ 새 마이그레이션으로 fn_publish_gate 함수 CREATE OR REPLACE.

Q: 새 가이드/에세이/리포트/사례 페이지를 만들고 싶다.
→ 대시보드 /admin/themes/new (parent 선택) → /admin/nodes/new (body_template 선택).
   또는 SeedDemoCommand에 추가하고 시드 재실행.

Q: 새 테마를 추가하면서 region 페이지 안 만들고 싶다.
→ SeedDemoCommand의 MATRIX_EXCLUDED_SLUGS에 slug 추가.

Q: SCSS 변경했는데 안 보인다.
→ rm -rf public/assets && cache:clear && SQL: UPDATE content_node SET last_review_at = NOW() WHERE status='live'.

Q: 시드 게이트가 자꾸 막힌다.
→ 본문 길이 체크 (visible_len, 공백 정규화 후). 또는 개발 단계니 db:drop && db:create로 리셋.
```

---

이 문서가 입양/확장 작업 중 *어디를 만질지*를 결정하는 단일 진입점이 되도록 의도했다. 빠진 부분이 있으면 PR 환영.

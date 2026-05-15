# 09. 결정 규칙 — Q&A + 파일 레퍼런스

이 챕터는 *어디를 만질지*를 빠르게 결정하기 위한 Q&A입니다. 각 답변은 정확한 챕터/파일로 안내합니다.

## 1. 페이지/외형 변경

### Q: 한 페이지만 다르게 보이게 하고 싶다.

```
templates/public/hubs/{theme-path}.html.twig 만들기.
조건: region IS NULL (지역 페이지면 불가능).
→ 02-template-overrides.md §1
```

### Q: 한 테마의 모든 지역 페이지 외형을 바꾸고 싶다.

```
templates/public/matrix/{theme-slug}.html.twig 만들기.
→ 02-template-overrides.md §2
```

### Q: 모든 매트릭스 페이지 외형을 바꾸고 싶다.

```
templates/public/_matrix.html.twig 편집.
빵부스러기/형제/자식만 손보려면 templates/public/_partials/hierarchy/* 편집.
```

### Q: 한 콘텐츠 타입(예: 모든 가이드)의 외형을 바꾸고 싶다.

```
templates/public/_guide.html.twig 편집.
→ 02-template-overrides.md §3
```

### Q: 계층(부모/자식/형제/조상)을 페이지에 추가하고 싶다.

```
계층 파셜 6종 중에서 골라 include — _partials/hierarchy/breadcrumb,
ancestor_chain, siblings_list, children_grid, descendants_tree, path_summary.
Twig 글로벌 nav/urls는 with … only에서도 접근.
→ 02-template-overrides.md §4.1
```

### Q: 새 계층 질의(예: cousins, 손자 카운트)가 필요하다.

```
src/Service/NodeNavigator.php에 메서드 1개 추가.
모든 템플릿이 nav.새메서드(node)로 즉시 호출 가능.
controller·buildContext 수정 불필요.
```

## 2. CTA

### Q: 가이드 페이지에만 폼 CTA를 띄우고 싶다.

```
config/cta.yaml 의 slots.hero / slots.final 룰에 'guide_*' 매칭 추가.
이미 디폴트로 들어가 있음.
→ 03-cta-system.md §매핑-테이블
```

### Q: 새 폼 타입을 추가하고 싶다.

```
1. src/Form/Cta/MyFormType.php 작성
2. CtaSubmitController::FORM_TYPES에 등록
3. CtaExtension::ctaFormView() match에 추가
4. cta.yaml 에 새 ctas 항목 + slots 룰
→ 03-cta-system.md §새-폼-추가하기
```

### Q: 새 알림 채널(슬랙 등)을 추가하고 싶다.

```
1. src/Notifier/SlackNotifier.php — CtaNotifierInterface 구현
2. CompositeCtaNotifier 생성자에 인자 추가
3. .env + services.yaml bind에 토큰 환경 변수
→ 03-cta-system.md §새-notifier-추가
```

### Q: 모든 페이지에서 CTA를 폼으로 바꾸고 싶다.

```
config/cta.yaml의 slots.* 모두를 form CTA에 매핑.
주의: navbar는 페이지 BodyTemplate을 모르므로 항상 글로벌 fallback.
→ 03-cta-system.md §슬롯-카탈로그
```

### Q: 한 페이지의 hero CTA와 final CTA가 서로 달라야 한다.

```
slots.hero / slots.final 매핑을 따로 설정.
지원됨 — 같은 페이지 안에서 다른 type 가능.
→ 03-cta-system.md §매핑-테이블
```

## 3. 콘텐츠 타입

### Q: 새 콘텐츠 타입(예: Interview)을 추가하고 싶다.

```
7곳 수정:
  enum + 마이그레이션(게이트) + 디스패처 + 템플릿 + SCSS + 폼 라벨 + 시드(선택)
→ 04-content-types.md §새-콘텐츠-타입-추가하기
```

### Q: 새 콘텐츠 타입을 추가해야 하나? 정당성 체크.

```
다음 중 최소 1개 참:
  ✓ 발행 게이트 임계값이 기존과 다른가?
  ✓ 시각 시그니처가 명확히 다른가?
  ✓ 사용처(테마)가 기존으로 표현 불가능한가?

모두 거짓이면: 자식 테마 + 기존 변종 사용.
→ 04-content-types.md §6
```

### Q: 발행 요건을 바꾸고 싶다.

```
새 마이그레이션으로 fn_publish_gate 함수 CREATE OR REPLACE.
순서: 함수 먼저 교체 → row 업데이트 나중.
→ 04-content-types.md §4
```

## 4. 콘텐츠 운영

### Q: 새 가이드/에세이/리포트/사례 페이지를 만들고 싶다.

```
대시보드 /admin/themes/new (parent 선택) → /admin/nodes/new (body_template 선택).
또는 SeedDemoCommand에 추가하고 시드 재실행.
→ 05-themes-and-content.md §3
```

### Q: 새 테마를 추가하면서 region 페이지 안 만들고 싶다.

```
SeedDemoCommand의 MATRIX_EXCLUDED_SLUGS에 slug 추가.
→ 05-themes-and-content.md §1.1
```

### Q: 게이트가 자꾸 막는다.

```
에러 메시지가 정확한 작업 항목.
"data_count >= 5 (have N)" → DataPoint 추가 + verify
"verified author required" → /admin/authors/{id}/verify
→ 05-themes-and-content.md §4
```

## 5. 환경/캐시

### Q: SCSS 변경했는데 안 보인다.

```
npm run build
rm -rf public/assets
php bin/console cache:clear --no-warmup
SQL: UPDATE content_node SET last_review_at = NOW() WHERE status='live';
→ 06-brand-and-design.md §5
```

### Q: 시드 게이트가 자꾸 막힌다.

```
개발 단계: db:drop && db:create로 5초 리셋.
→ 08-platform.md §시드-게이트-막힐-때
```

## 6. 입양 / 확장

### Q: 다른 국가로 입양하고 싶다.

```
RegionFixtures 통째로 교체 + BodyTemplate 깊이 매핑 검토.
→ 07-localization.md
```

### Q: 새 브랜드 변수(예: 카카오톡 채널 ID)를 추가하고 싶다.

```
1. .env에 BRAND_X
2. config/services.yaml _defaults.bind에 string $brandX
3. PublicNavExtension에 #[AsTwigFunction('brand_x')]
→ 06-brand-and-design.md §1
```

---

## 파일 레퍼런스

### 진입점

- [src/Controller/Public/PublicHomeController.php](../../src/Controller/Public/PublicHomeController.php) — 루트 `/`
- [src/Controller/Public/PublicNodeController.php](../../src/Controller/Public/PublicNodeController.php) — 모든 비-루트 (디스패처 + 컨텍스트)
- [src/Controller/Public/CtaSubmitController.php](../../src/Controller/Public/CtaSubmitController.php) — `/cta/submit/{form}`, `/cta/thank-you`

### 도메인 모델

- [src/Entity/](../../src/Entity/) — 5개 엔티티 (Theme, Region, Author, ContentNode, DataPoint) + Redirect
- [src/Entity/Enum/BodyTemplate.php](../../src/Entity/Enum/BodyTemplate.php) — 콘텐츠 타입 5종 (Matrix/Guide/Essay/Report/CaseStudy)
- [src/Entity/Enum/ContentStatus.php](../../src/Entity/Enum/ContentStatus.php) — 4상태 (draft/live/noindex/dead)
- [src/Entity/Enum/DataPointKind.php](../../src/Entity/Enum/DataPointKind.php) — 5종

### CTA 시스템

- [config/cta.yaml](../../config/cta.yaml) — 매핑 테이블
- [src/Cta/](../../src/Cta/) — Cta, CtaSlot, CtaType, CtaResolver
- [src/Form/Cta/](../../src/Form/Cta/) — FormType들
- [src/Notifier/](../../src/Notifier/) — CtaNotifierInterface, Telegram/Discord/Composite
- [src/Twig/CtaExtension.php](../../src/Twig/CtaExtension.php) — `cta()`, `cta_form_view()`
- [templates/public/_partials/cta/](../../templates/public/_partials/cta/) — 디스패처 파셜

### 서비스

- [src/Service/PathResolver.php](../../src/Service/PathResolver.php) — URL → ContentNode
- [src/Service/UrlBuilder.php](../../src/Service/UrlBuilder.php) — ContentNode → URL (Twig 글로벌 `urls`)
- [src/Service/BreadcrumbBuilder.php](../../src/Service/BreadcrumbBuilder.php) — 빵 부스러기 + JSON-LD
- [src/Service/NodeNavigator.php](../../src/Service/NodeNavigator.php) — 계층 질의 (Twig 글로벌 `nav`)
- [src/Twig/PublicNavExtension.php](../../src/Twig/PublicNavExtension.php) — 브랜드 식별값 Twig 함수

### 템플릿 (공개)

- [templates/public/layout.html.twig](../../templates/public/layout.html.twig) — 베이스 레이아웃
- [templates/public/home.html.twig](../../templates/public/home.html.twig) — 루트 페이지
- [templates/public/node.html.twig](../../templates/public/node.html.twig) — 디스패처
- [templates/public/_matrix.html.twig](../../templates/public/_matrix.html.twig) — 제네릭 매트릭스 fallback
- [templates/public/_guide.html.twig](../../templates/public/_guide.html.twig) — 가이드 prose
- [templates/public/_essay.html.twig](../../templates/public/_essay.html.twig) — 에세이 prose
- [templates/public/_report.html.twig](../../templates/public/_report.html.twig) — 데이터 리포트
- [templates/public/_case_study.html.twig](../../templates/public/_case_study.html.twig) — 사례 연구
- [templates/public/_partials/hierarchy/](../../templates/public/_partials/hierarchy/) — 6종 계층 파셜 (breadcrumb·ancestor_chain·siblings_list·children_grid·descendants_tree·path_summary)
- [templates/public/_partials/cta/](../../templates/public/_partials/cta/) — CTA 디스패처 파셜
- [templates/public/_partials/jsonld.html.twig](../../templates/public/_partials/jsonld.html.twig) — JSON-LD 스크립트
- [templates/public/matrix/](../../templates/public/matrix/) — 테마별 매트릭스 셸 (스타터엔 비어 있음)
- [templates/public/hubs/](../../templates/public/hubs/) — 페이지 단위 오버라이드 (스타터엔 비어 있음)
- [templates/public/cta/thank_you.html.twig](../../templates/public/cta/thank_you.html.twig) — CTA 제출 후

### 시드 / 마이그레이션

- [src/Command/SeedDemoCommand.php](../../src/Command/SeedDemoCommand.php) — 데모 데이터 생성
- [src/DataFixtures/](../../src/DataFixtures/) — Theme/Region/Author 초기 데이터
- [migrations/](../../migrations/) — DB 스키마 + 트리거 (특히 `fn_publish_gate`)

### SCSS

- [assets/scss/brand/](../../assets/scss/brand/) — 공개 사이트 (`_tokens.scss` 토큰, 컴포넌트별 분리)
  - [`_cta-form.scss`](../../assets/scss/brand/_cta-form.scss) — CTA 폼 스타일
- [assets/scss/](../../assets/scss/) — 관리자 (별도)

### 환경 / 설정

- [.env](../../.env) — `BRAND_*`, `CTA_TELEGRAM_*`, `CTA_DISCORD_*`
- [config/services.yaml](../../config/services.yaml) — 서비스 정의 + bind
- [config/cta.yaml](../../config/cta.yaml) — CTA 매핑
- [config/packages/twig.yaml](../../config/packages/twig.yaml) — Twig 글로벌 (`nav`, `urls`)
- [config/packages/security.yaml](../../config/packages/security.yaml) — 인증/접근 제어

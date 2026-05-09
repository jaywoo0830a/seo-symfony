# 02. 템플릿 오버라이드 — 5단계 자율도

같은 페이지의 외형을 어디까지 자유롭게 바꿀 수 있는가는 *어느 단계를 건드리는지*에 따라 결정됩니다. 자율도가 가장 큰 것부터 가장 좁은 것까지:

```
단일 페이지       (hubs/{path}.html.twig)        → 1개 URL
테마 매트릭스     (matrix/{theme}.html.twig)      → 한 테마의 N개 페이지
콘텐츠 타입       (_guide.html.twig 등)           → 그 타입의 모든 페이지
공유 파셜          (_partials/*.html.twig)         → 위 모두에 영향
루트 페이지       (home.html.twig)                → "/" 한 페이지
```

## 1. 단일 페이지 오버라이드 (가장 자유)

**경로**: `templates/public/hubs/{theme-path}.html.twig`

`{theme-path}`는 URL의 테마 부분과 동일:
- `/tutoring/` → `templates/public/hubs/tutoring.html.twig`
- `/guides/how-to-choose-tutor/` → `templates/public/hubs/guides/how-to-choose-tutor.html.twig`
- `/reports/quarterly-tutoring-rates-2026q1/` → `templates/public/hubs/reports/quarterly-tutoring-rates-2026q1.html.twig`

**제약**: `region IS NULL`인 노드에만 적용. 즉 *지역 페이지(`/tutoring/seoul/`)는 절대 오버라이드 불가*. 이는 의도된 제한 — 수백~수천 개 지역 페이지의 균일성이 SEO 자산이기 때문입니다.

**자율도**: HTML/CSS 100% 자유. 컨텍스트(`node`, `byKind`, `body_html` 등)는 그대로 받지만 *어떻게 렌더할지는 마음대로*.

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
    body_template: template,
} only %}
```

> **CTA 변경 노트**: `final_cta`/`hero` 파셜이 `body_template` 인자를 받게 됐습니다. 페이지의 BodyTemplate에 따라 CTA가 분기되도록 하려면 `body_template: template`을 전달하세요. 빠뜨리면 글로벌 fallback(`brand_phone`)이 적용됩니다. 자세한 동작은 [03-cta-system.md](03-cta-system.md).

작동 원리는 [PublicNodeController::findTemplateOverride()](../../src/Controller/Public/PublicNodeController.php) 참고.

## 2. 테마 단위 매트릭스 셸

**경로**: `templates/public/matrix/{root-theme-slug}.html.twig`

매트릭스 페이지(지역 페이지 + 오버라이드 없는 허브)의 셸. 같은 *루트 테마* 내 모든 매트릭스 페이지가 이걸 공유합니다.

**현재 보유**:
- [matrix/tutoring.html.twig](../../templates/public/matrix/tutoring.html.twig) — 과외 (행동 유도 톤)
- [matrix/academy.html.twig](../../templates/public/matrix/academy.html.twig) — 학원 (디렉토리 톤)
- [matrix/contents.html.twig](../../templates/public/matrix/contents.html.twig) — 학습 콘텐츠 (다크 디지털 톤)

**없으면** [_matrix.html.twig](../../templates/public/_matrix.html.twig) (제네릭 fallback)로 자동 폴백.

**원칙**:
- *테마 안*: 모든 지역 페이지가 같은 셸 (균일성 → SEO 자산)
- *테마 사이*: 셸이 달라야 함 (near-duplicate 방지)

작동 원리는 [PublicNodeController::findMatrixTemplate()](../../src/Controller/Public/PublicNodeController.php) 참고.

## 3. Prose 템플릿 (가이드/에세이/리포트/사례)

**경로**: 고정. body_template 값으로 자동 매핑.

| body_template | 템플릿 |
|---|---|
| `guide` | [_guide.html.twig](../../templates/public/_guide.html.twig) |
| `essay` | [_essay.html.twig](../../templates/public/_essay.html.twig) |
| `report` | [_report.html.twig](../../templates/public/_report.html.twig) |
| `case_study` | [_case_study.html.twig](../../templates/public/_case_study.html.twig) |

이 파일을 직접 수정하면 *해당 콘텐츠 타입의 모든 페이지*에 일괄 반영됩니다. 새 prose 패밀리가 필요하면 [04-content-types.md §확장 패턴](04-content-types.md#새-콘텐츠-타입-추가하기) 참조.

## 4. 파셜 라이브러리

**경로**: `templates/public/_partials/`

재사용 블록. 매트릭스 셸과 오버라이드에서 자유롭게 조합:

| 파셜 | 인자 (모두 선택) | 비고 |
|---|---|---|
| [breadcrumb](../../templates/public/_partials/breadcrumb.html.twig) | `crumbs` | |
| [hero](../../templates/public/_partials/hero.html.twig) | `eyebrow`, `title_main`, `title_highlight`, `title_tail`, `lede`, `cta_label`, `assurance`, `stats`, **`body_template`** | hero CTA가 body_template 기준 분기 |
| [feature_grid](../../templates/public/_partials/feature_grid.html.twig) | `eyebrow`, `title`, `lede`, `features` | |
| [callouts](../../templates/public/_partials/callouts.html.twig) | `items` (DataPoint), `eyebrow`, `title` | |
| [listing](../../templates/public/_partials/listing.html.twig) | `items`, `eyebrow`, `title` | |
| [theme_children](../../templates/public/_partials/theme_children.html.twig) | `items`, `urls`, `eyebrow`, `title` | |
| [faq](../../templates/public/_partials/faq.html.twig) | `items`, `eyebrow`, `title`, `alt` | |
| [final_cta](../../templates/public/_partials/final_cta.html.twig) | `eyebrow`, `title`, `lede`, **`body_template`** | final CTA가 body_template 기준 분기 |
| [jsonld](../../templates/public/_partials/jsonld.html.twig) | `article`, `breadcrumbs` | |

### CTA 디스패처 파셜 (신규)

`templates/public/_partials/cta/` 아래에 CTA 렌더 전용 파셜이 추가됐습니다 — 직접 호출할 일은 거의 없고, hero/final_cta가 내부적으로 사용합니다:

| 파셜 | 용도 |
|---|---|
| [_button.html.twig](../../templates/public/_partials/cta/_button.html.twig) | 단일 버튼 (hero, navbar, footer) |
| [_block_body.html.twig](../../templates/public/_partials/cta/_block_body.html.twig) | 블록 본문 (final_cta 안쪽) |

각 파셜이 5가지 CTA 타입(`phone`/`form`/`external`/`email`/`messenger`)을 분기 처리. 자세한 구조는 [03-cta-system.md](03-cta-system.md).

### 호출 규칙

- 모든 파셜은 `is defined` 디폴트가 있어 인자 누락에 안전
- `with {...} only`로 호출 권장 (외부 변수 누수 방지)
- BodyTemplate 분기가 필요한 파셜(hero/final_cta)은 `body_template: template`을 명시 전달

## 5. 루트 페이지

**경로**: [templates/public/home.html.twig](../../templates/public/home.html.twig)

이미 자유 템플릿. 별도 컨트롤러([PublicHomeController](../../src/Controller/Public/PublicHomeController.php))가 렌더하므로 ContentNode 시스템 밖. 그냥 편집하면 됩니다.

같은 파셜을 가져다 쓸 수 있어요.

## 어디를 만져야 하는지 결정 — 영향 범위 표

| 변경 의도 | 편집 대상 | 영향 범위 |
|---|---|---|
| 한 페이지만 다르게 | `hubs/{path}.html.twig` | 1개 URL |
| 한 테마의 모든 지역 페이지 | `matrix/{slug}.html.twig` | 한 테마의 N개 |
| 모든 매트릭스 페이지 | `_matrix.html.twig` 또는 `_partials/*` | 전체 매트릭스 |
| 한 콘텐츠 타입 (가이드 전체) | `_guide.html.twig` | 모든 가이드 |
| CTA만 (전체) | `config/cta.yaml` | 전체 (Twig 변경 X) |
| CTA만 (가이드만) | `config/cta.yaml`의 `slots.{slot}` 룰 | 가이드 페이지만 |
| 새 콘텐츠 타입 추가 | [04 §확장 패턴](04-content-types.md#새-콘텐츠-타입-추가하기) | 신규 |
| 발행 요건 변경 | 새 마이그레이션으로 `fn_publish_gate` | 전체 |
| 분기 로직만 | `node.html.twig` (거의 안 건드림) | dispatcher |

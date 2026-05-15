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
{% include 'public/_partials/hierarchy/breadcrumb.html.twig' with { crumbs: crumbs } only %}

<section class="hub-splash">
    <h1>{{ h1 }}</h1>
    {% if body_html %}{{ body_html|raw }}{% endif %}
</section>

{% include 'public/_partials/hierarchy/children_grid.html.twig' with {
    node: node,
    axis: 'theme',
    heading: '하위 페이지',
} only %}

{% set _cta = cta('final', template) %}
{% if _cta %}
    <section class="cta-block">
        {% include 'public/_partials/cta/_block_body.html.twig' with { cta: _cta } only %}
    </section>
{% endif %}
```

> **CTA 노트**: `cta('final', template)` Twig 함수가 BodyTemplate에 따라 CTA를 결정합니다. 빠뜨리면 글로벌 fallback(`brand_phone`)이 적용됩니다. 자세한 동작은 [03-cta-system.md](03-cta-system.md).

작동 원리는 [PublicNodeController::findTemplateOverride()](../../src/Controller/Public/PublicNodeController.php) 참고.

## 2. 테마 단위 매트릭스 셸

**경로**: `templates/public/matrix/{root-theme-slug}.html.twig`

매트릭스 페이지(지역 페이지 + 오버라이드 없는 허브)의 셸. 같은 *루트 테마* 내 모든 매트릭스 페이지가 이걸 공유합니다.

**현재 보유**: 스타터에는 비어 있음 (`templates/public/matrix/` 디렉토리 자체가 없음). [_matrix.html.twig](../../templates/public/_matrix.html.twig)가 모든 매트릭스 페이지의 제네릭 셸. 본인 도메인의 톤을 만들고 싶으면 `templates/public/matrix/{root-theme-slug}.html.twig`를 추가하세요.

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

### 4.1 계층 파셜 — `_partials/hierarchy/*` (가장 자주 씀)

빵부스러기/형제/자식 표현. [NodeNavigator](../../src/Service/NodeNavigator.php) (Twig 글로벌 `nav`)을 소비. 컨트롤러가 미리 5개 변수를 계산해 주입하던 방식 대신, 템플릿이 *필요한 시점*만 lazy로 부름.

| 파셜 | 인자 | 옵션 |
|---|---|---|
| [breadcrumb](../../templates/public/_partials/hierarchy/breadcrumb.html.twig) | `crumbs` | `separator` |
| [ancestor_chain](../../templates/public/_partials/hierarchy/ancestor_chain.html.twig) | `node` | `separator` |
| [siblings_list](../../templates/public/_partials/hierarchy/siblings_list.html.twig) | `node` | `axis` (region\|theme), `heading`, `empty_message` |
| [children_grid](../../templates/public/_partials/hierarchy/children_grid.html.twig) | `node` | `axis` (region\|theme), `heading` |
| [descendants_tree](../../templates/public/_partials/hierarchy/descendants_tree.html.twig) | `node` | `axis` (region\|theme) — depth 2까지 |
| [path_summary](../../templates/public/_partials/hierarchy/path_summary.html.twig) | `node` | `separator`, `include_self` |

호출 예:
```twig
{% include 'public/_partials/hierarchy/breadcrumb.html.twig' with { crumbs: crumbs } only %}

{% include 'public/_partials/hierarchy/siblings_list.html.twig' with {
    node: node,
    axis: 'theme',
    heading: '다른 가이드',
} only %}

{% include 'public/_partials/hierarchy/children_grid.html.twig' with {
    node: node,
    axis: 'theme',
    heading: '하위 페이지',
} only %}
```

새 계층 시점이 필요하면 (`uncles`, `cousins`, 깊이별 자식 등) [NodeNavigator](../../src/Service/NodeNavigator.php)에 메서드 1개 추가 → 모든 템플릿이 `nav.새메서드(node)`로 즉시 호출 가능. controller·context 안 건드림.

### 4.2 DataPoint 파셜 — `_partials/datapoint/*`

DataPoint kind별 렌더. value JSONB의 *시각 의도가 kind마다 다르므로* (수치는 큰 figure, 정성은 불릿, FAQ는 Q/A 등) 한 파셜이 모두 처리하지 않고 분기.

| 파셜 | 권장 value schema |
|---|---|
| [_dispatcher.html.twig](../../templates/public/_partials/datapoint/_dispatcher.html.twig) | `dp.kind.value`로 동적 include |
| [quantitative.html.twig](../../templates/public/_partials/datapoint/quantitative.html.twig) | `{figure, label?}` |
| [qualitative.html.twig](../../templates/public/_partials/datapoint/qualitative.html.twig) | `string[]` |
| [comparison.html.twig](../../templates/public/_partials/datapoint/comparison.html.twig) | `{baseline?, body}` 또는 string |
| [case.html.twig](../../templates/public/_partials/datapoint/case.html.twig) | `{quote, attribution?}` 또는 string |
| [faq.html.twig](../../templates/public/_partials/datapoint/faq.html.twig) | string (답변; title이 질문) |

호출 예 (kind에 무관하게 항상 디스패처):
```twig
{% for dp in byKind.quantitative %}
    {% include 'public/_partials/datapoint/_dispatcher.html.twig' with { dp: dp } only %}
{% endfor %}
```

섹션 헤딩에는 [DataPointKind::label()](../../src/Entity/Enum/DataPointKind.php) 사용:
```twig
<h2>{{ datapoint_kind_label(kind) }}</h2>   {# 'quantitative' → '수치 데이터' #}
```

- schema는 *권장* — 다른 형식 들어와도 파셜이 방어적으로 fallback
- 새 kind 추가 시: enum case + 동명 파셜 추가 → 디스패처 수정 불필요
- 상세 schema는 [docs/ai/02-entities.md §5.3](../ai/02-entities.md#53-kind별-권장-value-schema)

### 4.3 CTA 파셜 — `_partials/cta/*`

| 파셜 | 용도 |
|---|---|
| [_block_body.html.twig](../../templates/public/_partials/cta/_block_body.html.twig) | CTA 블록 본문 (5타입 분기) |

5가지 CTA 타입(`phone`/`form`/`external`/`email`/`messenger`)을 분기 처리. 자세한 구조는 [03-cta-system.md](03-cta-system.md).

### 4.4 SEO 파셜

| 파셜 | 용도 |
|---|---|
| [jsonld](../../templates/public/_partials/jsonld.html.twig) | Article + BreadcrumbList JSON-LD 스크립트 (디스패처 `node.html.twig`가 사용) |

### 4.5 호출 규칙

- 모든 파셜은 `is defined` 또는 `|default` 디폴트가 있어 인자 누락에 안전
- `with {...} only`로 호출 (외부 변수 누수 방지)
- `nav`와 `urls`는 Twig 글로벌 — `only`에서도 접근 가능, 인자로 안 넘겨도 됨

## 5. 루트 페이지

**경로**: [templates/public/home.html.twig](../../templates/public/home.html.twig)

이미 자유 템플릿. 별도 컨트롤러([PublicHomeController](../../src/Controller/Public/PublicHomeController.php))가 렌더하므로 ContentNode 시스템 밖. 그냥 편집하면 됩니다.

같은 파셜을 가져다 쓸 수 있어요.

## 어디를 만져야 하는지 결정 — 영향 범위 표

| 변경 의도 | 편집 대상 | 영향 범위 |
|---|---|---|
| 한 페이지만 다르게 | `hubs/{path}.html.twig` | 1개 URL |
| 한 테마의 모든 지역 페이지 | `matrix/{slug}.html.twig` | 한 테마의 N개 |
| 모든 매트릭스 페이지 | `_matrix.html.twig` | 전체 매트릭스 |
| 한 콘텐츠 타입 (가이드 전체) | `_guide.html.twig` | 모든 가이드 |
| 계층 표현 (빵부스러기/형제/자식) | `_partials/hierarchy/*` | 사용하는 모든 페이지 |
| 새 계층 질의 추가 (cousins 등) | `src/Service/NodeNavigator.php`에 메서드 1개 | 모든 페이지 |
| CTA만 (전체) | `config/cta.yaml` | 전체 (Twig 변경 X) |
| CTA만 (가이드만) | `config/cta.yaml`의 `slots.{slot}` 룰 | 가이드 페이지만 |
| 새 콘텐츠 타입 추가 | [04 §확장 패턴](04-content-types.md#새-콘텐츠-타입-추가하기) | 신규 |
| 발행 요건 변경 | 새 마이그레이션으로 `fn_publish_gate` | 전체 |
| 분기 로직만 | `node.html.twig` (거의 안 건드림) | dispatcher |

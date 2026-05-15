# 04. 페이지 시스템 아키텍처 — 왜 디스패처 + 오버라이드인가

## 1. 풀어야 할 두 가지 모순

페이지 렌더링 시스템은 *서로 모순되는 두 요구*를 동시에 만족해야 합니다:

### 1.1 균일성 요구 (지역 페이지)

같은 테마의 *수백 개 지역 페이지*는 셸이 **거의 동일**해야 합니다. 강남구 과외 페이지와 서초구 과외 페이지가 레이아웃이 다르면:
- Google이 *프로그래매틱 SEO*로 분류 → 일괄 디인덱스
- 사용자가 페이지 간 비교 어려움
- 사이트 전체 일관성 깨짐

균일성 = SEO 자산.

### 1.2 차별성 요구 (허브, 가이드, 권위 콘텐츠)

루트 허브, 테마 허브, 가이드, 리포트, 사례 연구는 **서로 완전히 달라야** 합니다:
- 가이드는 *how-to 톤* (체크리스트, FAQ)
- 에세이는 *에디토리얼 톤* (drop cap, 작가 byline)
- 리포트는 *학술 톤* (요약, 방법론, 인용 안내)
- 사례는 *증거 톤* (Before/After, 인용)

이들이 같은 모양이면 *각 콘텐츠 타입의 의도*가 약해집니다.

### 1.3 두 요구가 충돌하는 지점

이 두 요구를 한 시스템에서 풀려면:

> **균일성의 범위**와 **차별성의 범위**를 *명확히 분리*해야 한다.

이 분리가 시스템 아키텍처의 핵심 설계 결정입니다.

## 2. 5가지 우선순위 디스패처

[node.html.twig](../../templates/public/node.html.twig)는 *분기 로직만* 담는 디스패처입니다. 어떤 템플릿으로 렌더할지 다음 우선순위로 결정:

```
1. template_override 파일 존재?  → 사용
   templates/public/hubs/{theme-path}.html.twig

2. body_template == guide_*?     → _guide.html.twig
3. body_template == essay?       → _essay.html.twig
4. body_template == report?      → _report.html.twig
5. body_template == case_study?  → _case_study.html.twig

6. 그 외 → matrix_template
   templates/public/matrix/{root-theme-slug}.html.twig
   (없으면 _matrix.html.twig 제네릭 fallback)
```

### 2.1 왜 5가지 우선순위인가

각 단계가 *다른 자율도 영역*을 표현합니다:

| 우선순위 | 자율도 | 적용 범위 | 의도 |
|---|---|---|---|
| 1. 페이지 오버라이드 | 무한 (HTML 자유) | 1개 URL | "이 페이지만 완전히 다르게" |
| 2~5. Prose 템플릿 | 중간 (구조 고정) | 콘텐츠 타입 전체 | "모든 가이드가 같은 톤" |
| 6. 매트릭스 셸 | 낮음 (셸 공유) | 테마의 모든 매트릭스 페이지 | "한 테마는 일관된 외형" |

이 *피라미드*가 **균일성↔차별성 트레이드오프를 운영자가 케이스별로 결정**할 수 있게 합니다.

### 2.2 우선순위가 강한 것이 약한 것을 이긴다

핵심 룰: *위에서 아래로* 검사. 매칭되는 첫 번째에서 멈춥니다.

이는 *오버라이드가 디폴트를 항상 이긴다*는 의미. 운영자가 명시적으로 오버라이드 파일을 만들었다면, 그 의도가 어떤 자동 분기보다 우선합니다.

## 3. 우선순위와 region 노드의 제약

### 3.1 가장 중요한 제약 — 지역 페이지는 절대 오버라이드 불가

`findTemplateOverride()`의 첫 줄:

```php
if ($node->getRegion() !== null) {
    return null;
}
```

지역 페이지(region IS NOT NULL)는 *영원히 오버라이드 후보가 안 됨*. 이는 의도된 제약입니다.

### 3.2 왜 이 제약을 코드 레벨에 박아두는가

운영자(또는 미래의 당신)가 *특정 지역 페이지만 다르게* 만들고 싶은 충동이 들 수 있습니다:

> "강남구는 워낙 중요하니 좀 더 화려하게 만들자"

이 충동에 굴복하면:
- 강남구가 다른 자치구와 셸이 달라짐 → 균일성 깨짐
- Google이 *프로그래매틱 신호*로 인식 (의도적 일관성 vs 우연한 차이)
- 다음 자치구도 "우리도 다르게"가 시작 → 결국 모든 페이지가 다름 → 도메인 전체 약화

이 함정을 *코드 레벨로 차단*합니다. 운영자가 `hubs/tutoring/seoul/gangnam-gu.html.twig` 파일을 만들어도 `findTemplateOverride()`가 `region !== null`이라 무시.

지역 페이지의 차별화는 *데이터(DataPoint)에서만* 나와야 한다 — 이게 SEO 원칙입니다.

### 3.3 차별화가 필요하면 어떻게

지역 페이지가 *실제로 달라야 할 이유*가 있다면:

| 옵션 | 사용처 |
|---|---|
| **데이터 더 채우기** | 강남구 DataPoint 30개, 외곽 시 5개 — *데이터의 깊이*로 차별 |
| **테마 매트릭스 셸 변경** | 모든 과외 페이지 셸은 같지만, 학원 페이지는 다른 셸 — `matrix/{theme}.html.twig` |
| **새 BodyTemplate 변종** | "프리미엄 매트릭스" 같은 새 타입 (강한 정당성 필요) |

지역 페이지 *개별 오버라이드*는 *영원히* 안 되는 옵션입니다.

## 4. 매트릭스 셸의 두 단계

### 4.1 테마별 매트릭스 (matrix/{slug}.html.twig)

같은 *루트 테마* 내 모든 매트릭스 페이지가 공유:

| 파일 | 적용 범위 |
|---|---|
| [matrix/tutoring.html.twig](../../templates/public/matrix/tutoring.html.twig) | `/tutoring/`, `/tutoring/seoul/`, `/tutoring/seoul/gangnam-gu/` 등 |
| [matrix/academy.html.twig](../../templates/public/matrix/academy.html.twig) | `/academy/*` 모두 |
| [matrix/contents.html.twig](../../templates/public/matrix/contents.html.twig) | `/contents/*` 모두 |

원칙:
- *같은 테마, 다른 지역* = 같은 셸, 다른 데이터 (균일성)
- *다른 테마, 같은 지역* = 다른 셸, 다른 데이터 (차별성)

### 4.2 제네릭 fallback (`_matrix.html.twig`)

테마별 셸이 없으면 [_matrix.html.twig](../../templates/public/_matrix.html.twig)로 fallback. 의도적으로 *테마 색채 없는* 카피로 작성.

이 fallback이 있어 *새 테마를 추가해도 망가지지 않음*. 운영자가 매트릭스 셸을 만들 시간이 있을 때까지 안전한 외형 유지.

### 4.3 왜 두 단계인가 (테마별 + fallback)

대안 A: 테마별 셸 *필수* — 새 테마 추가 시 항상 셸 만들어야 → 진입 장벽
대안 B: 제네릭 셸만 — 테마별 차별 불가 → near-duplicate 위험
**선택**: 둘 다 — 운영자가 *원하는 만큼* 차별화

## 5. 파셜 라이브러리

[templates/public/_partials/](../../templates/public/_partials/)는 두 카테고리:

```
_partials/
├── jsonld.html.twig                          # JSON-LD 스크립트 (Article + BreadcrumbList)
├── cta/_block_body.html.twig                 # CTA 5종 (phone/form/external/email/messenger) 디스패처
└── hierarchy/                                # 계층 표현 카탈로그 (아래 §5.3)
    ├── breadcrumb.html.twig
    ├── ancestor_chain.html.twig
    ├── siblings_list.html.twig
    ├── children_grid.html.twig
    ├── descendants_tree.html.twig
    └── path_summary.html.twig
```

### 5.1 왜 파셜인가

매트릭스 셸과 페이지 오버라이드가 *같은 시각 컴포넌트*를 쓸 수 있어야 일관성이 유지됩니다. 파셜이 없으면:
- 같은 빵부스러기 마크업이 5개 prose 템플릿에 복사됨
- 한 곳 수정하면 다른 4곳 까먹음
- SCSS 클래스 이름 불일치

### 5.2 파셜의 방어적 디폴트

모든 파셜은 인자가 *누락되어도 안전*하게 디폴트 처리:

```twig
{% set node = node is defined ? node : null %}
{% set axis = axis is defined ? axis : 'region' %}
```

이 패턴이 없으면 `with {} only` 호출 시 *strict_variables 모드에서 500 에러*. 한 곳에서 빠뜨려도 페이지가 안 죽습니다.

### 5.3 계층 파셜 (_partials/hierarchy/*)

[NodeNavigator](../../src/Service/NodeNavigator.php) (Twig 글로벌 `nav`)을 소비하는 6종 파셜. 컨트롤러가 미리 계산해 넘기지 않아도 *템플릿이 직접 질의*. 새 계층 질의가 필요하면 Navigator에 메서드 1개만 추가 → controller·context 안 건드림.

| 파셜 | 인자 | 용도 |
|---|---|---|
| `breadcrumb.html.twig` | `crumbs` | label/url 평면 리스트 (BreadcrumbBuilder 출력 소비) |
| `ancestor_chain.html.twig` | `node` | ContentNode 객체 체인 — author/dataCount 등 데이터 활용 가능 |
| `siblings_list.html.twig` | `node`, `axis` | 형제 (region 또는 theme 축) |
| `children_grid.html.twig` | `node`, `axis` | 자식 (region 또는 theme 축) |
| `descendants_tree.html.twig` | `node`, `axis` | 자식 + 손자 (depth 2) |
| `path_summary.html.twig` | `node` | "과외 › 서울 › 강남구" 인라인 한 줄 |

설계 결정:
- **계층 질의는 controller가 아니라 Twig에서** — buildContext가 5~6개 변수를 미리 계산해 넘기던 방식은 *6번째 시점*이 필요할 때마다 controller 수정을 강요. Navigator는 lazy 호출이라 안 쓰면 0 쿼리, 쓰면 그때 1쿼리 (요청 스코프 메모이즈).
- **`with … only` 강제** — 파셜은 명시적 인자 계약. `nav`/`urls`는 글로벌이라 인자로 안 넘겨도 접근 가능.
- **depth 2까지만** — Twig 동적 재귀가 불편해 의도적으로 얕음. 더 깊은 트리는 어드민 목록 UI(MatrixController)에서 다룸.

## 6. 오버라이드 파일의 디렉토리 구조

```
templates/public/hubs/
├── tutoring.html.twig                       # /tutoring/ 오버라이드
├── academy.html.twig                        # /academy/ 오버라이드
├── guides.html.twig                         # /guides/ 오버라이드
└── guides/
    └── how-to-choose-tutor.html.twig        # /guides/how-to-choose-tutor/ 오버라이드
```

### 6.1 디렉토리가 URL 경로를 미러링

`templates/public/hubs/{theme-path}.html.twig`의 `{theme-path}`는 URL의 테마 부분과 동일.

| URL | 오버라이드 파일 |
|---|---|
| `/tutoring/` | `hubs/tutoring.html.twig` |
| `/guides/` | `hubs/guides.html.twig` |
| `/guides/how-to-choose-tutor/` | `hubs/guides/how-to-choose-tutor.html.twig` |

이 미러링이 *멘탈 모델을 단순화*. 운영자는 URL을 보고 어디 파일을 만들지 즉시 압니다.

### 6.2 왜 nested 구조인가 (flat 대신)

대안 A: `hubs/guides_how-to-choose-tutor.html.twig` (flat with separator)
- 단점: 슬러그에 separator가 들어가면 충돌

대안 B (선택): `hubs/guides/how-to-choose-tutor.html.twig` (nested)
- 장점: 파일 시스템이 *URL 트리와 동형*

## 7. 콘텐츠 타입의 prose 템플릿

각 prose 패밀리는 *고정된 템플릿 파일*에 1:1 매핑됩니다:

| BodyTemplate | 템플릿 파일 | 핵심 시각 시그니처 |
|---|---|---|
| `guide` | [_guide.html.twig](../../templates/public/_guide.html.twig) | 체크리스트, FAQ 강조, 강한 CTA |
| `essay` | [_essay.html.twig](../../templates/public/_essay.html.twig) | drop cap, byline, 차분한 reading flow |
| `report` | [_report.html.twig](../../templates/public/_report.html.twig) | 시리즈 헤더, 핵심 수치 박스, 인용 안내 |
| `case_study` | [_case_study.html.twig](../../templates/public/_case_study.html.twig) | Before/After, 학생 프로필 박스, 증언 인용 |

### 7.1 가이드는 sub-kind 없이 단일 패밀리

이전엔 `guide_longform` / `guide_comparison` / `guide_faq` 3종으로 갈렸으나 *통합*. sub-kind는 의도적으로 두지 않고, 차이는 *작성자 보이스와 Markdown 본문*이 만든다. 시각 구조는 단일 흐름:

```
hero → body → 보조 수치 → 비교 콜아웃 → FAQ → 형제 가이드 → CTA
```

비교 데이터(comparison DataPoint)나 FAQ DataPoint가 없으면 해당 섹션은 자동 생략. 슬러그별로 시각이 크게 달라야 하면 [`hubs/guides/{slug}.html.twig`](../../templates/public/hubs/guides/) 오버라이드로 처리.

**왜 sub-kind 안 두는가**: 분류는 끝없이 갈라지고(checklist/decision/pitfall/workflow/primer 등) 각 sub-kind는 결국 시각·구조가 거의 같다. 분류 enum은 *시각 구조가 실제로 다른* 경우에만 정당하고, 가이드 안에서는 그 기준을 만족하지 못한다고 판단했다. 필요해지면 그때 도입.

## 8. 컨텍스트 변수 — 모든 템플릿이 받는 것

두 갈래로 분리됩니다.

### 8.1 페이지 단위 컨텍스트 (buildContext)

[PublicNodeController::buildContext()](../../src/Controller/Public/PublicNodeController.php)가 주입하는 *페이지마다 달라지는* 값:

```php
[
    'node', 'url', 'h1', 'template',
    'body_html',                            // markdown→HTML (prose 변종만)
    'template_override', 'matrix_template',
    'byKind',                                // DataPoint 종류별 그룹
    'crumbs',                                // BreadcrumbBuilder 출력
    'json_ld', 'breadcrumbs_jsonld',
]
```

### 8.2 Twig 글로벌 (config/packages/twig.yaml)

페이지 *무관한* 헬퍼는 글로벌로:

| 글로벌 | 서비스 | 용도 |
|---|---|---|
| `nav` | [NodeNavigator](../../src/Service/NodeNavigator.php) | parent/children/siblings/ancestors/themeChain/regionChain |
| `urls` | [UrlBuilder](../../src/Service/UrlBuilder.php) | URL 생성 |

글로벌의 장점:
- `with … only` 인클루드(`_partials/hierarchy/*`)에서도 접근 가능 — *인자로 일일이 넘기지 않아도 됨*
- buildContext가 *5개 hierarchy 변수를 미리 precompute*하던 부담 제거 (lazy)

### 8.3 왜 hierarchy를 컨텍스트가 아니라 글로벌로 옮겼나

이전엔 buildContext가 `parent`, `siblings`, `children`, `theme_children`, `theme_siblings` 5개를 미리 계산해 모든 페이지에 주입했습니다. 문제:

1. **6번째 시점**(예: uncles, cousins, countChildren)이 필요하면 controller 수정 강요 → Twig만으로 새 계층 표현 추가 불가
2. **안 쓰는 페이지도 5개 쿼리 발생** — essay/report 같은 prose는 형제 정도만 쓰는데도 region children/parent 등을 다 계산
3. **`siblings`와 `theme_siblings` 두 이름의 의미 모호** — 둘 다 "형제"인데 축이 다름. Navigator는 `axis` 인자로 명시.

Navigator + Twig 글로벌로 옮긴 결과:
- 새 계층 시점 = Navigator 메서드 1개 추가 (controller·context 무관)
- 쓰는 만큼만 쿼리 (요청 스코프 메모이즈로 중복 방지)
- 축이 명시적 (`nav.siblings(node, 'theme')`)

### 8.4 어떻게 새 컨텍스트 변수를 추가하나

페이지마다 다른 값이라면 `buildContext()`에 키 추가. 페이지 무관한 헬퍼라면 [twig.yaml](../../config/packages/twig.yaml) `globals:`에 등록.

## 9. 자동 도출 — 디스패처가 하는 일

디스패처는 *결정만* 합니다. 도출은 컨트롤러/리포지토리가:

| 자동 도출 | 어디서 |
|---|---|
| URL | [UrlBuilder::build()](../../src/Service/UrlBuilder.php) |
| 빵부스러기 | [BreadcrumbBuilder](../../src/Service/BreadcrumbBuilder.php) |
| 부모/자식/형제 | [ContentNodeRepository](../../src/Repository/ContentNodeRepository.php) |
| matrix_template 결정 | [PublicNodeController::findMatrixTemplate](../../src/Controller/Public/PublicNodeController.php) |
| template_override 결정 | [PublicNodeController::findTemplateOverride](../../src/Controller/Public/PublicNodeController.php) |
| Markdown → HTML | `renderMarkdown()` (league/commonmark) |
| JSON-LD | `buildJsonLd()` |

이 모든 자동 도출이 *데이터에서 페이지로 가는 함수*를 구성합니다. 함수의 *입력*은 5개 엔티티의 상태, *출력*은 HTML.

## 10. 새 콘텐츠 타입 추가의 7단계

새 BodyTemplate(예: `interview`)을 추가할 때 만져야 하는 7곳:

1. **enum** — `BodyTemplate.php`에 case 추가 + `isInterview()` 메서드
2. **마이그레이션** — `fn_publish_gate`의 CASE 문에 분기 추가
3. **디스패처** — `node.html.twig`에 `{% elseif is_interview %}` 분기
4. **템플릿** — `_interview.html.twig` 신규 작성
5. **SCSS** — `_interview.scss` + `app.scss`에 `@use`
6. **폼 라벨** — `ContentNodeType.php`의 `choice_label` match에 추가
7. **시드** (선택) — `SeedDemoCommand.php`에 데모 추가

이 *7단계 프로토콜*이 [docs/contribution/04-content-types.md §5](../contribution/04-content-types.md#5-새-콘텐츠-타입-추가하기)에 운영자용으로 정리되어 있습니다.

## 11. 다음 문서

[05. roadmap-and-risks.md](05-roadmap-and-risks.md)에서 *왜 12개월 페이즈로 나눴는지*, *각 페이즈의 위험과 완화책*을 다룹니다.

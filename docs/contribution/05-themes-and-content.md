# 05. 테마 + 콘텐츠 운영

이 챕터는 *새 좌표축을 만들고 페이지를 채우는 방법*입니다. 어드민 CRUD 흐름과 새 테마 추가 패턴을 다룹니다.

## 1. 새 테마 추가하기

### 1.1 루트 테마 (depth=0)

대시보드 `/admin/themes/new` 또는 [ThemeFixtures](../../src/DataFixtures/ThemeFixtures.php)에 추가.

루트 테마가 *지역 매트릭스*를 갖지 않아야 한다면 (예: 새 가이드 컨테이너) [SeedDemoCommand::MATRIX_EXCLUDED_SLUGS](../../src/Command/SeedDemoCommand.php)에 slug 추가.

### 1.2 자식 테마 (depth=1, 가이드/리포트/사례용)

대시보드 `/admin/themes/new` → parent 드롭다운에서 부모 선택 → depth는 자동 계산 ([ThemeNewController:27](../../src/Controller/ThemeNewController.php#L27)).

URL: `/{parent-slug}/{child-slug}/`로 자동 생성됩니다.

### 1.3 사전 질문 3개

새 테마 추가 전에 다음을 자문하세요:

- 자체 데이터를 쌓을 수 있는 영역인가?
- 기존 테마와 키워드 분리되는가?
- 최소 5개 노드를 발행할 수 있는가?

세 질문 모두 ✓이면 진행. 그렇지 않으면 자식 테마로 충분한지 검토.

### 1.4 테마 자식 페이지 자동 노출

`/parent/`에서 자식 카드를 보여주려면 [_partials/hierarchy/children_grid.html.twig](../../templates/public/_partials/hierarchy/children_grid.html.twig)를 매트릭스 셸이나 오버라이드에 포함하세요:

```twig
{% include 'public/_partials/hierarchy/children_grid.html.twig' with {
    node: node,
    axis: 'theme',
    heading: '하위 페이지',
} only %}
```

내부적으로 `nav.children(node, 'theme')`을 호출 — 같은 region 좌표에서 직접 자식 테마들의 노드를 live만 추려서 표시합니다. 기본 [_matrix.html.twig](../../templates/public/_matrix.html.twig)에 이미 들어가 있습니다.

### 1.5 깊이 제약

- `Theme.depth ≤ 2` (DB CHECK 제약)
- 사일로 흩어짐 방지를 위한 의도된 제한
- 3차 이상 깊이가 필요하다고 느껴지면 보통 *테마 분류가 잘못된 것*

## 2. 운영 워크플로우 (CRUD)

대시보드에서 5개 엔티티 모두 풀 CRUD 지원:

| 엔티티 | URL prefix | 신규 폼 | 비고 |
|---|---|---|---|
| Theme | `/admin/themes` | parent 선택 → depth 자동 | [ThemeType](../../src/Form/ThemeType.php) |
| Region | `/admin/regions` | 행정구역 트리 | [RegionType](../../src/Form/RegionType.php) |
| Author | `/admin/authors` | verified는 별도 [verify](../../src/Controller/AuthorVerifyController.php) | [AuthorType](../../src/Form/AuthorType.php) |
| ContentNode | `/admin/nodes` | bodyTemplate 드롭다운에 10개 변종 | [ContentNodeType](../../src/Form/ContentNodeType.php) |
| DataPoint | `/admin/nodes/{nodeId}/datapoints` | 노드 안에서 추가 | [DataPointType](../../src/Form/DataPointType.php) |

DataPoint URL은 *노드에 nested* — 부모 노드 컨텍스트 안에서만 생성/편집 가능. 대시보드 노드 show 페이지에 "+ 추가" 버튼.

운영용 보조 화면:

- `/admin/matrix` — 매트릭스 ([MatrixController](../../src/Controller/MatrixController.php))
- `/admin/queue` — 갱신 큐 ([RefreshQueueController](../../src/Controller/RefreshQueueController.php))
- `/admin/silo` — 사일로 무결성 ([SiloController](../../src/Controller/SiloController.php))
- `/admin/search` — 콘텐츠 검색 ([AdminSearchController](../../src/Controller/AdminSearchController.php))

## 3. 변종별 콘텐츠 작성 흐름

### 3.1 새 매트릭스 페이지 (지역별)

```
1. 대시보드 /admin/nodes/new
2. theme + region 선택 (body_template 비워둠 → 자동 결정)
3. intro_text 작성 (페이지 상단 직접 답변, 2~3문장)
4. /admin/nodes/{id}/datapoints/new → verified DataPoint 5개+ 추가
5. status='live' 변경 → 트리거가 게이트 검증
```

### 3.2 새 가이드

```
1. /admin/themes/new → parent='guides' 선택, slug 입력
2. /admin/nodes/new → theme=새_자식_테마, region=비움
3. body_template = Guide 명시
4. body_markdown 작성 (3,000자+)
5. /admin/nodes/{id}/datapoints/new → kind=faq 3개+ 추가하고 verify
6. status='live' 변경 → 트리거가 게이트 검증
```

### 3.3 새 데이터 리포트

```
1. /admin/themes/new → parent='reports' (없으면 먼저 reports 루트 테마 만들기)
2. /admin/nodes/new → 자식 테마 선택, region=비움, body_template=Report
3. body_markdown 5,000자+ 작성 (분석 + 데이터 + 방법론)
4. quantitative DataPoint 추가 (핵심 수치)
5. comparison DataPoint 추가 (방법론 노트)
6. status='live'
```

### 3.4 새 사례 연구

```
1. /admin/themes/new → parent='cases'
2. /admin/nodes/new → 자식 테마, region=비움, body_template=CaseStudy
3. body_markdown 1,500자+ (서사형)
4. quantitative DataPoint 2개+ (Before/After 수치)
5. qualitative DataPoint 1개 (학생 프로필)
6. case (CaseStudy) DataPoint 1~2개 (인용)
7. status='live'
```

## 4. 게이트 통과 못 할 때

에러 메시지가 정확한 작업 항목입니다:

| 메시지 | 해결 |
|---|---|
| `data_count >= 5 (have N)` | DataPoint N→5 채우기 + verify |
| `non-empty intro_text` | intro_text 작성 |
| `verified author required` | 작성자 검증 ([AuthorVerifyController](../../src/Controller/AuthorVerifyController.php)) |
| `parent live check fail` | 부모 노드 먼저 live로 |
| `body_markdown visible length >= N (have M)` | 본문 보강 |
| `verified FAQ data points >= 3` | FAQ DataPoint 추가 + verify |

게이트는 우회 안 됩니다. 이유 해결만 가능합니다.

## 5. 페이지 폐기 / 통합

### 트래픽 0인 페이지가 90일 됐을 때

```
3가지 옵션:
  A. 데이터 보강 → DataPoint 2~3건 추가 + last_review_at 갱신
  B. 통합     → 인접 페이지에 데이터 머지 → 이 페이지 status=dead → 자동 301
  C. 삭제     → status=dead → 시스템이 부모 찾아 자동 301
```

방치는 옵션 아닙니다. 약한 페이지가 30% 넘으면 사이트 전체 약화.

### 카니발리제이션 (두 페이지 같은 키워드)

```
1. /admin/search에서 후보 페어 식별
2. 트래픽 많은 쪽 (강한 쪽) 결정
3. 약한 쪽의 데이터를 강한 쪽에 머지
4. 약한 쪽 status='dead' → 자동 301
```

## 관련 문서

- 운영자 일일 루틴: [OPERATIONS.md](../../OPERATIONS.md)
- 결정 룰: [docs/ai/05-decision-rules.md](../ai/05-decision-rules.md)
- 페이지 도출 룰: [docs/ai/03-page-derivation.md](../ai/03-page-derivation.md)

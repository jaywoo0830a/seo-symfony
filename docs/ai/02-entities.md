# 02. 엔티티 카탈로그

5개 엔티티의 *완전한 룰*. 각 엔티티에 대해: 필드, 제약, 불변성, 강제 위치를 명시합니다.

## 0. 엔티티 한눈에

| 엔티티 | 페이지가 됨? | 역할 |
|---|---|---|
| Theme | ✕ | 분류 축. 트리의 한 축. |
| Region | ✕ | 분류 축. 트리의 다른 축. |
| Author | △ | 작성자 페이지 1개만 매핑 |
| **ContentNode** | **✓** | **페이지의 추상 단위. 1행 = 1 URL** |
| DataPoint | ✕ | 페이지의 *내용물*. 페이지 안 섹션으로 렌더 |

ContentNode만 페이지를 만듭니다. 다른 4개는 *좌표축이거나 내용물*.

## 1. Theme — 테마 트리

### 1.1 필드

| 필드 | 타입 | 설명 |
|---|---|---|
| id | INT, PK | 식별자 |
| slug | VARCHAR | URL용 (예: `tutoring`) |
| name | VARCHAR | 사람이 읽는 이름 |
| parent_id | INT, FK→Theme | 부모 테마 (NULL=루트) |
| depth | INT | 0=루트, 1=1차, 2=2차 |
| description | TEXT | 허브 페이지의 메타 설명 |
| created_at | TIMESTAMPTZ | |

### 1.2 제약

| 제약 | 강제 |
|---|---|
| `depth <= 2` | DB CHECK 제약 |
| `(parent_id, slug)` UNIQUE | DB UNIQUE 제약 |
| 루트 테마는 `parent_id IS NULL` | 관행 |

### 1.3 불변성

- 같은 부모 아래 동일 slug 불가
- 3차 이상 깊이의 테마 트리 불가 (사일로 흩어짐 방지)

### 1.4 코드

- 엔티티: [src/Entity/Theme.php](../../src/Entity/Theme.php)
- 폼: [src/Form/ThemeType.php](../../src/Form/ThemeType.php)
- 시드: [src/DataFixtures/ThemeFixtures.php](../../src/DataFixtures/ThemeFixtures.php)

## 2. Region — 지역 트리

### 2.1 필드

| 필드 | 타입 | 설명 |
|---|---|---|
| id | INT, PK | |
| slug | VARCHAR | URL용 (예: `seoul`, `gangnam-gu`) |
| name | VARCHAR | 사람이 읽는 이름 |
| parent_id | INT, FK→Region | 부모 지역 |
| depth | INT | 0=전국 루트, 1=시도, 2=시군구, 3=동/읍/면 |
| admin_code | VARCHAR | 행정안전부 코드 |
| lat, lng | DECIMAL | 중심 좌표 (선택) |

### 2.2 제약

| 제약 | 강제 |
|---|---|
| `(parent_id, slug)` UNIQUE | DB UNIQUE |
| `depth >= 0 AND depth <= 3` | DB CHECK |
| `parent_id IS NULL ⟹ depth = 0` | DB 트리거 |

### 2.3 깊이별 개수 (한국 기준)

| depth | 의미 | 개수 |
|---|---|---|
| 0 | 전국 (가상 루트) | 1 |
| 1 | 시도 | 17 |
| 2 | 시군구 | ~228 |
| 3 | 동/읍/면 | ~3,500 |

### 2.4 불변성

- 행정구역은 *시드 단계에서 전체 입력*. 페이지가 모든 좌표에 만들어지는 것은 아님.
- `region_id`가 NULL인 ContentNode = *지역 무관 페이지* (테마 허브, 가이드, 에세이 등)

### 2.5 코드

- 엔티티: [src/Entity/Region.php](../../src/Entity/Region.php)
- 시드: [src/DataFixtures/RegionFixtures.php](../../src/DataFixtures/RegionFixtures.php)

## 3. Author — 작성자

### 3.1 필드

| 필드 | 타입 | 설명 |
|---|---|---|
| id | INT, PK | |
| slug | VARCHAR, UNIQUE | URL용 |
| real_name | VARCHAR | 실명 |
| credentials | TEXT | 자격·경력 (구조화) |
| bio | TEXT | 소개글 |
| photo_url | VARCHAR | 사진 |
| verified_at | TIMESTAMPTZ | 신원 확인 일시 |

### 3.2 제약

| 제약 | 강제 |
|---|---|
| `slug` UNIQUE 전체 | DB UNIQUE |
| `verified_at IS NULL`인 작성자의 노드는 live 불가 | DB 트리거 `fn_publish_gate` |

### 3.3 페이지 매핑

작성자 1명 = `/about/authors/<slug>/` 1 페이지.

이는 *예외적 매핑* (다른 엔티티는 페이지를 안 만듦).

### 3.4 SEO 의미

- E-E-A-T 시그널의 직접 원천
- 익명 SEO 양산 차단 게이트의 일부
- Schema.org Person으로 매핑됨

### 3.5 코드

- 엔티티: [src/Entity/Author.php](../../src/Entity/Author.php)
- 검증 컨트롤러: [src/Controller/AuthorVerifyController.php](../../src/Controller/AuthorVerifyController.php)

## 4. ContentNode — 페이지의 추상 단위

**가장 중요한 엔티티**. 1행 = 1 URL.

### 4.1 필드

| 필드 | 타입 | 설명 |
|---|---|---|
| id | INT, PK | |
| theme_id | INT, FK→Theme | 어느 테마인가 |
| region_id | INT, FK→Region | 어느 지역인가 (NULL=지역 무관) |
| author_id | INT, FK→Author | 책임 작성자 |
| status | ENUM | draft/live/noindex/dead |
| data_count | INT | 자동 계산 (verified DataPoint 수) |
| intro_text | TEXT | 페이지 상단 직접 답변 (2~3문장) |
| body_template | ENUM | 어떤 템플릿으로 렌더 (자동 또는 명시) |
| body_markdown | TEXT | prose 변종의 본문 (Markdown) |
| first_published_at | TIMESTAMPTZ | 최초 발행일 |
| last_review_at | TIMESTAMPTZ | 최근 검토일 (분기 점검용) |
| kpi_snapshot | JSONB | Search Console 데이터 캐시 |

### 4.2 제약

| 제약 | 강제 |
|---|---|
| `(theme_id, region_id)` UNIQUE | DB UNIQUE — 카니발리제이션 차단 |
| `status='live' ⟹ data_count >= 5` (매트릭스) | DB 트리거 `fn_publish_gate` |
| `status='live' ⟹ intro_text 비어있지 않음` | DB 트리거 |
| `status='live' ⟹ author.verified_at IS NOT NULL` | DB 트리거 |
| `status='live' ⟹ parent.status='live'` (region 있을 때) | DB 트리거 `fn_parent_live_check` |
| `status='dead' ⟹ Redirect 행 존재` | DB 트리거 `fn_dead_cascade_redirect` |

### 4.3 상태 전이

```
draft ──► live    : 발행 게이트 통과 (운영자 수동)
draft ──► noindex : 게이트 미달 (자동)
live  ──► noindex : data_count 5 미만 (자동 강등)
noindex ──► live  : 게이트 회복 (운영자 수동)
*     ──► dead    : 분기 정리 (운영자 수동)
dead  ──► (301)   : Redirect 자동 등록
```

### 4.4 자동 계산 필드

| 필드 | 갱신 트리거 |
|---|---|
| `data_count` | DataPoint INSERT/UPDATE/DELETE 시 `fn_refresh_data_count` |
| `first_published_at` | 첫 live 전이 시 자동 설정 |

### 4.5 코드

- 엔티티: [src/Entity/ContentNode.php](../../src/Entity/ContentNode.php)
- 폼: [src/Form/ContentNodeType.php](../../src/Form/ContentNodeType.php)
- 컨트롤러: [src/Controller/Public/PublicNodeController.php](../../src/Controller/Public/PublicNodeController.php)

## 5. DataPoint — 차별성의 단위

ContentNode 한 좌표를 *그 좌표답게* 만드는 고유 데이터 한 조각.

### 5.1 필드

| 필드 | 타입 | 설명 |
|---|---|---|
| id | INT, PK | |
| node_id | INT, FK→ContentNode (CASCADE) | 어느 노드의 데이터인가 |
| kind | ENUM | quantitative/qualitative/comparison/case/faq |
| title | VARCHAR | 데이터 제목 |
| value | JSONB | 실제 값 (kind마다 다른 스키마) |
| source | VARCHAR | 출처 |
| verified | BOOLEAN | 검증 완료 여부 (기본 false) |
| updated_at | TIMESTAMPTZ | |

### 5.2 5가지 kind

| kind | 의미 | 예시 |
|---|---|---|
| `quantitative` | 정량 데이터 | "강남구 매칭 가능 강사 12명" |
| `qualitative` | 정성 데이터 | 학교 목록, 학원가 분포 |
| `comparison` | 인접 지역과의 차이 | "역삼동 대비 평균 시급 8% 높음" |
| `case` (DataPointKind::CaseStudy) | 매칭 사례, 후기 | 동의받은 실명/익명 사례 |
| `faq` | 지역/테마 특화 Q&A | 분야별 질문 |

### 5.3 kind별 권장 value schema

`DataPoint.value`는 JSONB 자유형이지만, 공개 페이지 렌더 파셜([_partials/datapoint/*](../../templates/public/_partials/datapoint/))이 다음 schema를 기대합니다. 스키마에서 벗어나도 파셜이 방어적으로 fallback하지만, *일관된 입력* = *일관된 렌더*.

| kind | 권장 schema | 예 | 렌더 |
|---|---|---|---|
| `quantitative` | `{figure: string, label?: string}` | `{"figure": "142명", "label": "검증 완료"}` | figure를 큰 숫자, label은 보조 |
| `qualitative` | `string[]` | `["초등학교", "학원가", "도서관"]` | `<ul>` 불릿 |
| `comparison` | `{baseline?: string, body: string}` 또는 단순 string | `{"baseline": "인접 지역 대비", "body": "약 6% 차이"}` | baseline은 인라인 prefix, body가 본문 |
| `case` | `{quote: string, attribution?: string}` 또는 단순 string | `{"quote": "...", "attribution": "강남구 학부모"}` | `<blockquote>` + 출처 |
| `faq` | 단순 string (답변 본문). title이 질문 | `"네, 방문·화상 모두 지원합니다."` | Q/A 한 쌍 |

스키마 외 fallback 룰:
- iterable인데 권장 키가 없으면: 첫 값을 본문으로 시도
- iterable이 아니면 (scalar): 본문으로 직접 사용
- 빈 값: 해당 섹션 자동 생략

### 5.4 라벨

`DataPointKind::label()` 메서드가 공개용 한국어 라벨 반환 (수치 데이터, 정성 데이터, 인접 비교, 사례·후기, 자주 묻는 질문). 어드민 폼 라벨은 더 길고 안내문 붙음 — [DataPointType::buildForm](../../src/Form/DataPointType.php)에서 inline match로 별도 정의.

Twig에서: `{{ datapoint_kind_label('quantitative') }}` 또는 enum 객체일 때 `{{ kind.label }}`.

### 5.3 제약

| 제약 | 강제 |
|---|---|
| `kind` ENUM 5종만 | DB CHECK |
| `verified=true`만 `data_count`에 반영 | 애플리케이션 + 트리거 |
| 노드 삭제 시 CASCADE 삭제 | DB FK ON DELETE CASCADE |

### 5.4 발행 게이트와의 관계

매트릭스 노드의 `data_count`:

```
data_count = COUNT(DataPoint WHERE verified=true AND node_id=N)
```

이 값이 5 이상이어야 매트릭스 노드가 live.

### 5.5 코드

- 엔티티: [src/Entity/DataPoint.php](../../src/Entity/DataPoint.php)
- enum: [src/Entity/Enum/DataPointKind.php](../../src/Entity/Enum/DataPointKind.php)
- 폼: [src/Form/DataPointType.php](../../src/Form/DataPointType.php)
- 공개 렌더 파셜: [templates/public/_partials/datapoint/](../../templates/public/_partials/datapoint/) (_dispatcher + 5종 kind)
- 라벨 Twig 함수: [src/Twig/DataPointExtension.php](../../src/Twig/DataPointExtension.php)

## 6. Redirect — 무덤 관리

ContentNode가 폐기될 때 자동 생성.

### 6.1 필드

| 필드 | 타입 | 설명 |
|---|---|---|
| id | INT, PK | |
| from_node_id | INT, FK→ContentNode | 원본 (status='dead') |
| to_node_id | INT, FK→ContentNode | 가장 가까운 live 부모 |
| http_status | INT | 301 (기본) |
| created_at | TIMESTAMPTZ | |

### 6.2 자동 생성

운영자가 `status='dead'`로 변경하면 `fn_dead_cascade_redirect` 트리거가:
1. 가장 가까운 live 조상 노드 찾기
2. Redirect 행 생성
3. `to_node_id`로 그 조상 설정

운영자는 *수동으로 등록 안 함*.

## 7. 엔티티 관계도

```
Theme (self-tree, depth ≤ 2)
  │
  └─► ContentNode ◄─── Region (self-tree, depth ≤ 3)
          │            │ (NULL 가능)
          │            
          ├─► Author (verified_at 필수)
          ├─► DataPoint × N (5종 kind, verified 토글)
          └─► Redirect × 0..1 (status=dead 시 자동)
```

## 8. 어떤 변경이 어떤 트리거를 발동시키나

| 변경 | 발동 트리거 | 결과 |
|---|---|---|
| ContentNode INSERT (status=live) | `fn_publish_gate` | 게이트 검증, 미달이면 RAISE |
| ContentNode UPDATE (status=live) | `fn_publish_gate`, `fn_parent_live_check` | 자격 재검증, 부모 체크 |
| ContentNode UPDATE (live → 다른) | `fn_dead_cascade_redirect` (dead로 갈 때) | Redirect 행 생성 |
| DataPoint INSERT/UPDATE/DELETE | `fn_refresh_data_count` | 부모 노드의 data_count 갱신 |
| Theme INSERT/UPDATE | `trg_theme_depth_check` | depth > 2이면 RAISE |
| Region INSERT/UPDATE | `trg_region_depth_check` | parent NULL인데 depth>0이면 RAISE |

## 9. 관련 룰

- 페이지 도출: [03-page-derivation.md](03-page-derivation.md)
- 발행 게이트 상세: [05-decision-rules.md §발행 결정](05-decision-rules.md)
- 트리거의 *왜*: [docs/dev/03-publish-gate-design.md](../dev/03-publish-gate-design.md)

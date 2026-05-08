# 02. 데이터 모델의 근거 — 왜 5개 엔티티인가

## 1. 핵심 주장

이 시스템은 **5개 엔티티**로 모든 것을 표현합니다:

```
Theme    Region    Author    ContentNode    DataPoint
```

이 5개는 *임의의 선택*이 아닙니다. 매트릭스 사이트의 본질을 표현하는 *최소 집합*이고, 더 늘리거나 줄이면 시스템 전체가 망가집니다. 이 문서는 *왜 이 5개여야 하는지*, *각 엔티티가 어떤 결정 책임을 지는지*를 설명합니다.

## 2. 각 엔티티의 책임

### 2.1 `Theme` — 카테고리 축

**무엇을 표현하나**: 사이트가 다루는 *주제 카테고리*. 예: "과외", "학원", "학습 콘텐츠".

**책임**: *키워드 매트릭스의 한 축*. 다른 축은 Region.

**왜 트리 구조인가** (자기 참조 `parent_id`):
- 가이드/리포트/사례 같은 비-지역 콘텐츠는 *자식 테마*로 표현 (예: `guides/how-to-choose-tutor`)
- 트리 구조 없이 평면 enum이면 → 이런 콘텐츠를 표현 못함

**왜 `depth <= 2` 제한인가**:
- 3차 이상으로 가면 사일로가 흩어져 토픽 권위가 약해짐
- 트리 깊이 = SEO 권위 단계, 너무 깊으면 어디에도 권위가 안 잡힘
- DB CHECK 제약으로 코드 외부에서도 강제

**구현**: [src/Entity/Theme.php](../../src/Entity/Theme.php)

### 2.2 `Region` — 지역 축

**무엇을 표현하나**: 검색 의도의 *지리적 차원*.

**왜 4단계 트리** (`depth 0~3`, 한국 기준):
- 0: 전국 (가상 루트)
- 1: 시도 (17개)
- 2: 시군구 (~228개)
- 3: 동/읍/면 (~3,500개)

**왜 미리 다 채워두는가**:
- 시드 단계에서 행정구역 전체를 입력 ([RegionFixtures](../../src/DataFixtures/RegionFixtures.php))
- 페이지를 *만들기 위해서*가 아니라 *어디가 가능한지 좌표를 제공*하기 위해서
- 페이지가 발행되는 곳은 ContentNode가 만들어진 *일부 칸*뿐

**왜 ContentNode가 region 없이도 가능한가** (`region_id` nullable):
- 테마 허브(`/tutoring/`) = 지역 무관
- 가이드/에세이/리포트 = 지역 무관
- 즉 *지역 축에서 벗어난 콘텐츠*가 존재

**구현**: [src/Entity/Region.php](../../src/Entity/Region.php)

### 2.3 `Author` — E-E-A-T 시그널 원천

**무엇을 표현하나**: 콘텐츠 작성자/검증자.

**왜 별도 엔티티인가** (단순 `author_name` 필드 대신):
- Google의 E-E-A-T 평가에서 *작성자 신원*은 핵심 신호
- 작성자 페이지(`/about/authors/<slug>/`)가 따로 필요
- 자격증/경력(`credentials`)이 *Schema.org Person*에 매핑됨

**왜 `verified_at` 필드인가**:
- 운영자 한 명을 잘못 등록하면 *그 작성자의 모든 페이지*가 신뢰 신호 손상
- 검증된 작성자만 발행 가능 → 게이트의 일부

**왜 작성자 미검증 시 발행 불가인가**:
- 익명 SEO 양산을 차단
- DB 트리거(`fn_publish_gate`)가 `author.verified_at IS NULL`이면 거부

**구현**: [src/Entity/Author.php](../../src/Entity/Author.php)

### 2.4 `ContentNode` — 좌표의 점

이것이 **가장 중요한 엔티티**입니다.

**무엇을 표현하나**: *(theme, region) 좌표 위의 한 점*. 1 ContentNode = 1 페이지 = 1 URL.

**왜 별도 엔티티인가** (Theme이나 Region에 직접 콘텐츠 붙이는 대신):
- 같은 테마 안에 *지역별로 다른 콘텐츠*가 있어야 함
- 같은 지역 안에 *테마별로 다른 콘텐츠*가 있어야 함
- 즉 콘텐츠는 *교차점에 매달리는 객체*여야 함

**가장 중요한 제약 — `(theme_id, region_id)` UNIQUE**:
```sql
UNIQUE (theme_id, region_id)
```

이 한 줄이 *카니발리제이션 문제의 80%를 해결*합니다. 같은 좌표에 페이지가 두 개일 수 없으므로, 동일 키워드를 노리는 페이지가 우연히 두 개 생기는 사고가 *DB 레벨에서* 차단됩니다.

**왜 region이 nullable인가**:
- `(theme=tutoring, region=NULL)` = 테마 허브 (`/tutoring/`)
- `(theme=guides/how-to, region=NULL)` = 가이드 페이지

UNIQUE 제약은 *NULL을 다르게 처리*하므로 (PostgreSQL), 한 테마에 region=NULL 노드는 하나만 존재 가능합니다 — 우리가 원하는 의미.

**상태 (`status`)의 4단계**:
- `draft` (초안)
- `live` (발행)
- `noindex` (비색인 — 게이트 미달)
- `dead` (폐기 — 자동 301)

이 4단계는 *Google의 인덱스 의사결정*을 운영자 워크플로우로 직접 매핑한 것입니다.

**`data_count` 자동 컬럼**:
- DataPoint INSERT/UPDATE/DELETE가 `fn_refresh_data_count` 트리거로 자동 갱신
- 운영자가 직접 쓰면 안 됨 (트리거가 덮어씀)
- 발행 게이트는 이 값을 봄 → *DataPoint 검증 = 발행 게이트의 일부*

**구현**: [src/Entity/ContentNode.php](../../src/Entity/ContentNode.php)

### 2.5 `DataPoint` — 차별성의 단위

**무엇을 표현하나**: ContentNode 한 좌표를 *그 좌표답게* 만드는 *고유 데이터 한 조각*.

**5가지 `kind`**:
- `quantitative` — 정량 데이터 (예: "강남구 매칭 가능 강사 12명")
- `qualitative` — 정성 데이터 (예: 학교 목록, 학원가 분포)
- `comparison` — 인접 지역과의 차이
- `case` — 매칭 사례, 실제 후기
- `faq` — 지역/테마 특화 질문-답변

**왜 5가지인가** (더 많거나 적지 않은가):
- 페이지 구조 §3·§4·§5 (정량·정성·차별)이 *발행 게이트의 트리거*
- §6·§7 (가격·FAQ)는 보조
- §8 (인용/후기)는 E-E-A-T 보강
- 즉 5가지가 *페이지의 모든 의미 있는 데이터 유형*을 커버

**왜 `value`가 JSONB인가**:
- 5가지 kind마다 데이터 형식이 다름 (숫자, 문자열, 리스트, 사전)
- 정형 컬럼으로 분리하면 5개 테이블이 됨 → 복잡도 폭발
- JSONB로 *kind별 스키마는 애플리케이션에서 검증*

**왜 `verified` 플래그인가**:
- 검증 안 된 데이터로 발행되면 *잘못된 정보*가 SEO에 박힘
- 운영자가 *검증 토글*을 통해 "이 데이터는 사실"이라고 명시
- `data_count`는 `verified=true`만 카운트 → 검증이 곧 발행 자격

**구현**: [src/Entity/DataPoint.php](../../src/Entity/DataPoint.php)

## 3. 운영자가 만지는 것은 5개 엔티티뿐

이 시스템의 *가장 중요한 운영 원칙*:

> **운영자는 페이지를 만지지 않는다. 5개 엔티티만 만진다.**

URL, HTML, sitemap.xml, 리다이렉트, 내비게이션은 *모두 코드가 자동 생성*합니다. 운영자가 SQL 콘솔에 들어가서 직접 INSERT를 시도해도 트리거가 막습니다.

| 엔티티 | 운영자 만지는 빈도 | 이유 |
|---|---|---|
| `Theme` | 분기 1회 미만 | 테마 추가는 전략 결정 |
| `Region` | 도입 시 한 번 | 행정구역은 안 바뀜 |
| `Author` | 분기 1~2회 | 새 작성자 등록·검증 |
| `ContentNode` | 매일 | 새 좌표에 점 찍기, 상태 전이 |
| `DataPoint` | 매일 (가장 자주) | 5가지 kind로 데이터 입력·검증 |

이 빈도 차이가 [OPERATIONS.md](../../OPERATIONS.md)의 일일/주간/분기 루틴 분배의 근거입니다.

## 4. 자동 도출되는 것들 — "그림자"

5개 엔티티의 상태에서 *자동 도출*되는 것들:

| 자동 도출 결과물 | 도출 방식 | 코드 위치 |
|---|---|---|
| URL | `(theme_path, region_path)` 함수 | [UrlBuilder](../../src/Service/UrlBuilder.php) |
| sitemap.xml | `WHERE status='live'` SELECT | [SitemapController](../../src/Controller/Public/SitemapController.php) |
| 빵부스러기 | region 트리 + theme 트리 walk | [BreadcrumbBuilder](../../src/Service/BreadcrumbBuilder.php) |
| 부모 노드 | `region.parent`로 lookup | [ContentNodeRepository::findParentOf](../../src/Repository/ContentNodeRepository.php) |
| 자식 노드 | 같은 테마 + region.depth+1 | [findChildrenOf](../../src/Repository/ContentNodeRepository.php) |
| 형제 테마 노드 | 테마 부모가 같은 노드 | [findThemeSiblingsOf](../../src/Repository/ContentNodeRepository.php) |
| JSON-LD Schema | author + theme + region + DataPoint | [PublicNodeController::buildJsonLd](../../src/Controller/Public/PublicNodeController.php) |
| 자동 301 리다이렉트 | `status='dead'`로 변경 시 트리거 | `fn_dead_cascade_redirect` |

이 *자동 도출*이 시스템의 핵심 가치입니다. 운영자가 데이터만 만지면 나머지가 따라옵니다 — *그래서 12개월 동안 일관성이 유지*됩니다.

## 5. 5개 엔티티의 보장 — 5가지 불변성

이 데이터 모델이 *설계만으로* 강제하는 5가지 불변성:

| 불변성 | 강제 방법 |
|---|---|
| 같은 (테마, 지역) 좌표 위 페이지는 하나뿐 | `UNIQUE(theme_id, region_id)` |
| 데이터 5개 미만 페이지는 `live` 불가 | `trg_publish_gate` |
| 부모 없는 자식 페이지는 `live` 불가 | `trg_parent_live_check` |
| 작성자 미검증 페이지는 `live` 불가 | `trg_publish_gate` 확장 |
| `dead` 페이지는 자동으로 301 등록 | `trg_dead_cascade_redirect` |

운영자가 어떤 의지로 우회하려 해도 *DB가 막습니다*. 이것이 [03. publish-gate-design.md](03-publish-gate-design.md)에서 깊이 다루는 주제입니다.

## 6. 다른 모델로 안 되는가 — 고려한 대안들

### 대안 A: ContentNode 없이 Theme/Region에 직접 콘텐츠 매달기
**왜 안 되나**: 같은 (테마, 지역) 칸에 콘텐츠가 매달릴 자리가 없음. 매트릭스 표현 불가.

### 대안 B: DataPoint 없이 ContentNode 본문에 모든 데이터
**왜 안 되나**:
- 발행 게이트의 *5개 데이터* 룰을 표현 못함
- 부분 갱신 어려움 (한 데이터만 바꾸려면 전체 본문 편집)
- AI 검색이 *구조화된 데이터*를 선호

### 대안 C: Theme을 enum으로
**왜 안 되나**: 가이드/리포트 같은 *자식 테마*를 표현 못함. 운영자가 새 테마 추가 시 코드 수정 필요.

### 대안 D: Region을 free text
**왜 안 되나**:
- 한 지역에 여러 표기가 생김 (예: "서울", "서울특별시", "Seoul")
- 트리 탐색 불가 → 부모-자식 관계 표현 불가
- 카니발리제이션 자동 차단 안 됨

### 대안 E: Author 대신 단순 author_name
**왜 안 되나**: E-E-A-T를 위한 작성자 페이지(`/about/authors/<slug>/`) 생성 불가. Schema.org Person 매핑 불완전.

각 대안을 검토한 결과, **5개 엔티티가 최소 충분 집합**입니다. 더 줄이면 매트릭스 사이트 본질을 표현 못하고, 더 늘리면 운영 복잡도가 폭증합니다.

## 7. 다음 문서

[03. publish-gate-design.md](03-publish-gate-design.md)에서 *왜 발행 게이트를 DB 트리거로 강제했는지*, *어떤 트레이드오프를 감수했는지*를 다룹니다. 핵심 답: **Month 2~3 충돌 시기에 운영자가 우회 시도를 하기 때문**.

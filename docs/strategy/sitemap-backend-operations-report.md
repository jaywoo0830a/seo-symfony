# 다(多)테마 × 지역 SEO 사이트의 사이트맵 및 백엔드 운영 구조 보고서

> **목표:** 12개월 SEO 전략 보고서를 *지속 가능하게 이어*하기 위한 사이트맵과 백엔드 데이터 모델을 설계한다.
> **전제:** 하나의 웹사이트, 하나의 서버. 백엔드 기술 스택은 미정.
> **작성일:** 2026년 5월
> **선행 보고서:** `seo-12month-strategy-final.md`

---

## 0. 핵심 통찰: "사이트맵은 데이터 모델의 그림자"

이 보고서의 모든 라인은 다음 한 문장에서 출발합니다.

> **사이트맵은 *그리는 것*이 아니라 *데이터 모델로부터 결정론적으로 도출되는 것*으로 설계한다.**

이것 왜 중요한가? 사이트맵을 *손으로 그리면* 6개월 안에 다음이 모두 일어납니다.

| 운영 실패 | 발생 원인 |
|-----------|-----------|
| 같은 페이지가 두 URL로 발행 | 사람이 새 URL을 즉흥적으로 만듦 |
| 데이터가 바뀌었는데 페이지가 안 바뀜 | 데이터와 URL의 연결이 약함 |
| 페이지가 있는데 데이터가 없음 (Soft 404) | 발행 게이트가 사람의 의지에 의존 |
| 새 지역이 추가되는데 어디 넣을지 모름 | 구조의 결정 규칙이 명문화 안 됨 |
| 약한 페이지가 누적되어 도메인 권위 하락 | 정리 트리거가 작동 안 됨 |

12개월 동안 약 350~700개 페이지를 발행하고, 그 후에도 매년 *데이터 추가 → 페이지 갱신 → 약한 페이지 정리*를 반복해야 합니다. 사람이 매일 결정을 내려야 하는 구조라면 *3개월 안에 지칩니다.* 결정론적 자동화가 유일한 답입니다.

---

## 1. 백엔드 데이터 모델 — 5개 핵심 엔티티

운영자가 만지는 것은 *페이지가 아닙니다.* 다음 5개 엔티티입니다. 페이지는 이들로부터 *생성*됩니다.

### 1.1 엔티티 개요

```
┌────────────────┐      ┌──────────────┐      ┌──────────────┐
│   THEME      │      │   REGION     │      │   AUTHOR     │
│   (테마)      │      │   (지역)      │      │   (작성자)    │
└────────┬───────┘      └────────┬─────┘      └────────┬─────┘
       │                     │                     │
       └──────────┬──────────┴─────────────────────┘
                 │
                 ▼
       ┌────────────────────┐      ┌─────────────────┐
       │   CONTENT_NODE      │──────│   DATA_POINT    │
       │   (콘텐츠 노드)      │  N:1 │   (고유 데이터)   │
       └────────────────────┘      └─────────────────┘
```

### 1.2 각 엔티티의 책임

#### THEME (테마)

테마의 트리. 자기 참조로 부모-자식 관계.

| 필드 | 타입 | 설명 |
|------|------|------|
| `id` | INT, PK | 식별자 |
| `slug` | VARCHAR, UNIQUE | URL용 (예: `tutoring`, `academy`) |
| `name` | VARCHAR | 표시명 (예: "과외", "학원") |
| `parent_id` | INT, FK→THEME | 부모 테마 (NULL = 루트) |
| `depth` | INT | 트리 깊이 (0 = 루트) |
| `description` | TEXT | 허브 페이지의 메타 설명 |
| `created_at` | TIMESTAMP | |

**제약:** `depth ≤ 2` (루트, 1차 테마, 2차 세부 테마까지만). 3차 이상은 사일로가 흩어집니다.

#### REGION (지역)

지역의 트리. 행정구역 체계와 1:1.

| 필드 | 타입 | 설명 |
|------|------|------|
| `id` | INT, PK | |
| `slug` | VARCHAR | URL용 (예: `seoul`, `gangnam-gu`) |
| `name` | VARCHAR | 표시명 (예: "서울특별시", "강남구") |
| `parent_id` | INT, FK→REGION | 부모 지역 |
| `depth` | INT | 0=전국, 1=시도, 2=시군구, 3=동 |
| `admin_code` | VARCHAR | 행정안전부 법정동 코드 |
| `lat`, `lng` | DECIMAL | 중심 좌표 (선택) |

**제약:** `(parent_id, slug)` UNIQUE. 같은 부모 아래 동일 slug 금지.

#### AUTHOR (작성자)

E-E-A-T 신호의 원천. *반드시 설명* 기반.

| 필드 | 타입 | 설명 |
|------|------|------|
| `id` | INT, PK | |
| `slug` | VARCHAR, UNIQUE | URL용 |
| `real_name` | VARCHAR | 설명 |
| `credentials` | TEXT | 자격·경력 (구조화) |
| `bio` | TEXT | 소개글 |
| `photo_url` | VARCHAR | 사진 |
| `verified_at` | TIMESTAMP | 신원 확인 일시 |

#### CONTENT_NODE (콘텐츠 노드)

**페이지의 추상 단위.** 이 보고서의 핵심 엔티티입니다.

| 필드 | 타입 | 설명 |
|------|------|------|
| `id` | INT, PK | |
| `theme_id` | INT, FK→THEME | 어느 테마인가 |
| `region_id` | INT, FK→REGION | 어느 지역인가 (NULL=지역 무관 허브) |
| `author_id` | INT, FK→AUTHOR | 책임 작성자 |
| `status` | ENUM | `draft`/`live`/`noindex`/`dead` |
| `data_count` | INT | 자동 계산 (DataPoint 수) |
| `intro_text` | TEXT | 페이지 상단 직접 답변 (2~3문장) |
| `body_template_id` | INT | 어떤 템플릿으로 렌더링할 것인가 |
| `first_published_at` | TIMESTAMP | 최초 발행일 |
| `last_review_at` | TIMESTAMP | 최근 검토일 (분기 점검용) |
| `kpi_snapshot` | JSONB | Search Console 데이터 캐시 |

**제약 (가장 중요):**
- `(theme_id, region_id)` UNIQUE → *같은 테마-지역 위에 노드는 정확히 하나*. 카니발리제이션 원천 차단.
- `status='live'`는 `data_count >= 5`일 때만 가능 (DB 트리거로 강제).

#### DATA_POINT (고유 데이터)

각 노드의 부속 *고유 데이터의 단위*. 이것 페이지를 페이지답게 만드는 재료.

| 필드 | 타입 | 설명 |
|------|------|------|
| `id` | INT, PK | |
| `node_id` | INT, FK→CONTENT_NODE | 어느 노드에 속하는가 |
| `kind` | ENUM | `quantitative`/`qualitative`/`comparison`/`case`/`faq` |
| `title` | VARCHAR | 데이터 제목 |
| `value` | JSONB | 실제 값 (kind마다 다른 스키마) |
| `source` | VARCHAR | 출처 |
| `verified` | BOOLEAN | 검증 완료 여부 |
| `updated_at` | TIMESTAMP | |

**다섯 가지 kind:**

1. **quantitative** — 정량 데이터 (예: "매칭 가능 선생님 12명")
2. **qualitative** — 정성 데이터 (예: 학교 목록, 학원가 분포)
3. **comparison** — 인근 지역과의 차이
4. **case** — 매칭 사례, 후기 (동의받은 것만)
5. **faq** — 지역/테마 특화 질문-답변

**발행 게이트의 정확한 정의:** `data_count = COUNT(verified=true) >= 5`. 단순 5개가 아니라 *검증된 5개*.

### 1.3 ERD 요약

```
THEME ←┐                   AUTHOR
       │self                  │
       └─→THEME               │
                              ▼
THEME ─────────────→ CONTENT_NODE ←───────── REGION
                          │                   │self
                          │                   └──→REGION
                          ▼
                     DATA_POINT
```

---

## 2. URL 구조 — 데이터로부터 결정론적 도출

URL은 *코드가 생성*하며, 다음 함수의 출력입니다.

```
url(node) = "/" + theme_path(node.theme) + "/" + region_path(node.region) + "/"

theme_path(t)  = t의 루트부터 t까지 slug를 "/"로 연결
region_path(r) = r의 시도부터 r까지 slug를 "/"로 연결 (전국 노드는 빈 문자열)
```

### 2.1 결과 패턴

```
/                                            → 사이트 허브 (theme=NULL, region=NULL의 특수 처리)
/tutoring/                                   → 테마 허브 (region_id=NULL)
/tutoring/seoul/                             → 시도
/tutoring/seoul/gangnam-gu/                  → 시군구
/tutoring/seoul/gangnam-gu/daechi-dong/      → 동
/academy/                                    → 다른 테마, 같은 패턴
/academy/seoul/gangnam-gu/
/guides/how-to-choose-tutor/                 → 정보형 (region 없는 테마)
```

### 2.2 이 패턴의 다섯 가지 이점

1. **카니발리제이션 원천 방지** → `(theme, region)` UNIQUE 제약으로 같은 페이지가 두 URL로 생성되는 일이 *DB 레벨에서* 불가능.
2. **계층 권위 흐름** → URL 깊이 = 권위 단계. 부모 URL이 자식 URL의 prefix.
3. **사이트맵 자동 생성** → `status='live'`인 노드를 SELECT하면 그것 sitemap.xml.
4. **운영자 인지 부하 최소화** → URL만 봐도 어느 데이터 행인지 안다.
5. **canonical 문제 소거** → URL이 함수의 출력이므로 다른 표현이 존재할 수 없다.

### 2.3 금지 사항

| 금지 | 이유 |
|------|------|
| `?theme=...&region=...` 쿼리스트링 URL | Google이 다른 페이지로 인지 안 함 |
| `/seoul/tutoring/` (지역 우선 순서) | 테마 허브 권위가 분산됨 |
| 여러 URL이 같은 콘텐츠 가리킴 | canonical 처리해도 권위 누수 |
| 깊이 5 이상 URL | 크롤 깊이 한계, 권위 도달 어려움 |

---

## 3. 사이트맵의 다섯 가지 뷰 — 같은 데이터, 다른 결정

사이트맵은 *하나의 트리*가 아니라 **같은 데이터의 다섯 가지 뷰**입니다. 각 뷰는 다른 결정에 쓰입니다.

### 3.1 발행 트리 뷰 (Public Tree)

사용자와 검색엔진이 보는 것. sitemap.xml과 사이트 내비게이션의 원천.

```
/
├── /tutoring/                        [HUB · live · data:N/A]
│   ├── /tutoring/seoul/               [SIDO · live · data:42]
│   │   ├── /tutoring/seoul/gangnam-gu/    [GU · live · data:18]
│   │   │   ├── /.../daechi-dong/          [DONG · live · data:9]
│   │   │   └── /.../yeoksam-dong/         [DONG · live · data:7]
│   │   └── /tutoring/seoul/seocho-gu/     [GU · live · data:14]
│   └── /tutoring/busan/               [SIDO · noindex · data:3]
├── /academy/                         [HUB · live]
└── /guides/                          [INFO · live]
```

**용도:** 일상 모니터링, 신규 페이지 검토, sitemap.xml 생성.

### 3.2 데이터 매트릭스 뷰

운영자의 일일 작업 현황판. 어느 칸을 채워야 하는지 한눈에 보입니다.

```
            과외     학원     학습콘텐츠    입시도구
서울 강남구  ✅9     ✅12     ✅15        △6
서울 서초구  ✅7     ✅5      ✅8         △3      ← 데이터 보강 우선
서울 송파구  ✅6     △4      △2         ✗0
경기 군포시  ✅8     △3      △1         ✗0
부산 금정구  △2     ✅5      ✗0         ✗0
…

범례: ✅ live (data≥5)  △ noindex (data 1~4)  ✗ dead (data 0)
```

**용도:** 매일 우선순위 결정. *△ → ✅*가 가장 효율적인 작업 (검색 의도는 검증되고, 데이터만 부족한 칸).

### 3.3 KPI 히트맵 뷰

분기 결정용. Search Console 데이터를 노드 트리에 매핑.

```
              인덱스   노출    클릭    평균순위   판단
/tutoring/      ✓     8,200    320     11.2    ▲ 유지
/.../seoul/     ✓     3,400    145     14.8    ▲ 유지
/.../gangnam/   ✓     1,200     62     18.3    ▲ 유지
/.../daechi/    ✗        15      0     62.1    ✗ 6개월 인덱스 안 됨 → 통합 검토
/.../yeoksam/   ✓       380     23     24.5    △ 보강 필요
/academy/       ✓     2,100     89     16.7    ▲ 유지
```

**용도:** 분기 정리 회의. 어릴지 / 통합할지 / 삭제할지 결정.

### 3.4 사일로 무결성 뷰

구조 점검용. 자동 검사기가 다음을 검출.

```
검사: /tutoring/seoul/gangnam-gu/

  ✓ 부모: /tutoring/seoul/ (같은 테마, depth 차이 1)
  ✓ 자식 4개, 모두 같은 테마
  ✗ 외부 링크 1건: /academy/seoul/gangnam-gu/ → 다른 테마로 직접 링크
       → 사일로 침범 경고

검사: /tutoring/jeju/
  ✗ 부모 없음 (theme 트리 위반)
  → 고아 페이지

검사: /tutoring/seoul/seocho-gu/banpo-dong/
  ✗ 자식 0, 부모 자식 목록에서 누락
  → 자동 등록 필요
```

**용도:** 매주 자동 실행, 위반 즉 알림.

### 3.5 갱신 큐 뷰

데일리 작업 목록. 다음 규칙으로 자동 생성.

```
오늘 갱신해야 할 노드:

[우선순위 1: 90일 미갱신]
  • /tutoring/seoul/gangnam-gu/  (마지막 갱신 95일 전)
  • /academy/gyeonggi/anyang-si/ (마지막 갱신 92일 전)

[우선순위 2: 새 데이터 유입]
  • /tutoring/seoul/seocho-gu/   (DataPoint 3건 추가, 미반영)

[우선순위 3: 노출 급증, 보강 기회]
  • /tutoring/incheon/yeonsu-gu/ (지난주 노출 +180%)

[우선순위 4: 약한 페이지 검토]
  • /tutoring/jeju/seogwipo-si/  (90일 트래픽 0)
```

**용도:** 운영자의 매일 작업 목록.

---

## 4. 운영자가 실제로 하는 일 — 만지는 것은 데이터뿐

사이트맵을 *직접 편집하지 않습니다.* 다음 작업만 합니다.

### 4.1 매일 (15~30분)

| 작업 | 만지는 엔티티 | 자동 결과 |
|------|---------------|-----------|
| 새 데이터 입력 | `DataPoint` 추가 | `data_count` 자동 갱신, 5 도달 시 `status` 변경 후보 등록 |
| 데이터 검증 | `DataPoint.verified` 토글 | 검증된 것만 페이지 노출 |
| 갱신 큐 처리 | 해당 노드들 점검 | `last_review_at` 갱신 |

### 4.2 매주 (1시간)

| 작업 | 만지는 엔티티 | 자동 결과 |
|------|---------------|-----------|
| 신규 노드 검토 | `ContentNode.status` `draft → live` | 라우터에 자동 등록, sitemap.xml 갱신 |
| Search Console 동기화 | (자동 cron) | KPI 히트맵 갱신 |
| 사일로 무결성 점검 | (자동 검사) | 위반 알림 처리 |

### 4.3 매분기 (반나절)

| 작업 | 만지는 엔티티 | 자동 결과 |
|------|---------------|-----------|
| 약한 페이지 정리 | `ContentNode.status → dead` 또는 통합 | 301 리다이렉트 자동 등록 |
| 새 테마/지역 추가 | `Theme`/`Region` row 추가 | 매트릭스 뷰에 새 행/열 자동 출현 |
| 키워드 카니발리제이션 점검 | (자동 검출) | 충돌 노드 통합 결정 |

**핵심:** 운영자는 URL이나 HTML을 만지지 않습니다. *데이터만 만집니다.*

---

## 5. 백엔드의 6가지 핵심 기능

사이트맵을 *이어 있게* 유지하는 백엔드 기능들.

### 5.1 발행 게이트 (Publish Gate)

```
function canPublish(node):
    if node.dataCount < 5:                    return false
    if not node.introText:                    return false
    if not node.author or not node.author.verified:  return false
    if node.region != null and node.parent == null:  return false
    if node.parent and node.parent.status != 'live': return false
    if hasCanonicalCollision(node):           return false
    return true
```

**중요:** 이 함수가 *페이지 생성의 유일한 입구*. 사람이 우회할 수 없도록 DB 트리거 + API 레이어 양쪽에서 강제.

### 5.2 자동 sitemap.xml 생성기

```
SELECT url, last_review_at, calc_priority(depth)
FROM content_node
WHERE status = 'live'
ORDER BY depth, theme_id, region_id
```

cron으로 매일 갱신. Google Search Console에 ping.

### 5.3 Search Console API 동기화

매일 새벽 노출·클릭·순위·CTR을 가져와 `kpi_snapshot`에 저장. KPI 히트맵과 갱신 큐 핵심 데이터 소스.

### 5.4 카니발리제이션 검출기

```
같은 키워드를 두 노드가 노릴 때 알림
검출 방법:
  1. 각 노드의 H1 + intro_text를 임베딩
  2. 코사인 유사도 0.85 이상이면 후보
  3. Search Console에서 같은 쿼리에 두 노드가 노출되는지 교차 검증
```

분기 정검의 핵심 도구.

### 5.5 사일로 무결성 검사기

매주 자동 실행하여 다음을 검출.

- 부모 없는 노드 (고아)
- 다른 테마로의 직접 링크
- 끊긴 내부 링크
- 부모 자식 목록에서 누락된 자식
- depth가 부모와 적합하지 않은 노드

### 5.6 자동 리다이렉트 관리

```
노드 status를 'dead'로 바꾸면 자동으로:
  1. 가장 가까운 살아있는 live 노드 찾기
  2. 301 리다이렉트 등록
  3. 내부 링크에서 해당 URL 제거 또는 갱신
  4. sitemap.xml에서 제외
```

---

## 6. 데이터 모델의 안기 강제 — 무결성 트리거

사람이 어기거나 우회하지 못하도록 DB 레벨에서 강제하는 규칙.

| 트리거 | 동작 |
|--------|------|
| `DataPoint INSERT/UPDATE/DELETE` | 부모 `ContentNode.data_count` 자동 재계산 |
| `ContentNode.status = 'live'` 시도 | `data_count >= 5` 검증, 미달 시 거부 |
| `ContentNode INSERT` | `(theme_id, region_id)` UNIQUE 검증 |
| `ContentNode.status = 'dead'` 시도 | 301 리다이렉트 대상 자동 결정 |
| `Region.parent_id` NULL인데 `depth > 0` | 거부 |
| `Theme.depth > 2` | 거부 |

이 트리거들이 *코드 외부에서도* 데이터 무결성을 보장합니다. SQL 콘솔에서 직접 INSERT를 시도해도 막힙니다.

---

## 7. 기술 스택 권장 사항 — 의사 결정 가이드

본 보고서는 특정 스택을 강제하지 않지만, 다음 결정은 *전체 운영 방식을 직결*되므로 신중히 합니다.

### 7.1 데이터베이스

| 후보 | 적합도 | 이유 |
|------|--------|------|
| PostgreSQL | ★★★★★ | JSONB로 `DataPoint.value`, `kpi_snapshot` 자연스럽게 저장. Recursive CTE로 트리 쿼리. 트리거 강력. |
| MySQL/MariaDB | ★★★☆☆ | 가능하지만 Recursive CTE 지원이 약함. JSON 함수 부족. |
| SQLite | ★★☆☆☆ | 작은 운영 가능. 동시성 한계로 5,000+ 페이지 단계에서 부담. |
| MongoDB | ★★☆☆☆ | 트리 구조 + 관계형 무결성 강제가 까다로움. 비추천. |

**권장: PostgreSQL.** Recursive CTE, JSONB, 트리거가 모두 강력합니다.

### 7.2 페이지 렌더링 전략

| 전략 | 적합도 | 이유 |
|------|--------|------|
| SSG (Static Site Generation) | ★★★★★ | SEO 최적, 페이지 수 700개 이하면 빌드 1~3분. 재발행 시 트리거. |
| ISR (Incremental Static Regeneration) | ★★★★★ | 페이지 갱신이 잦으면 유리. Next.js 등에서 표준. |
| SSR (Server-Side Rendering) | ★★★☆☆ | DB 부하 + 응답 속도 부담. 캐싱 잘 안 하면 Core Web Vitals 악화. |
| CSR (Client-Side Rendering) | ★☆☆☆☆ | SEO 불리. *절대 사용 금지.* |

**권장: SSG 또는 ISR.** 페이지 수가 적고 갱신이 분기 단위면 SSG가 단순. 데이터 갱신이 일/주 단위로 잦으면 ISR.

### 7.3 운영 대시보드

| 후보 | 적합도 | 이유 |
|------|--------|------|
| 자체 구축 (React + 백엔드 API) | ★★★★☆ | 5개 뷰를 정확히 구현 가능. 초기 비용 큼. |
| Retool, Appsmith 같은 로우코드 | ★★★★★ | DB 직접 연결, 5개 뷰를 며칠 안에 구현. 운영 단순. |
| Django Admin, Rails Admin | ★★★☆☆ | 빠른 시작. 매트릭스/히트맵 같은 커스텀 뷰는 부족. |
| 직접 SQL + Metabase | ★★☆☆☆ | 시각화는 좋지만 데이터 입력 UI가 약함. |

**권장: Phase 0~1은 로우코드 (Retool/Appsmith) → Phase 3 이후 자체 구축 검토.** 처음부터 자체 구축하면 SEO 시작이 늦어집니다.

---

## 8. 구현 로드맵 — 12개월 SEO 전략과의 동기화

선행 보고서의 Phase 구조에 동기화한 백엔드 구현 일정.

### Phase 0 (Week 1~2) — 데이터 모델 구축

**산출물:**
- 5개 엔티티 DB 스키마 + 트리거
- 발행 게이트 함수
- 최소 운영 대시보드 (매트릭스 뷰만)
- URL 라우터 + 자동 sitemap.xml
- Theme 3~5개, Region 트리 (시도 17개 + 시군구 228개) 시드 데이터

이 단계에서 페이지는 0개. 데이터 골격만 완성.

### Phase 1 (Month 1~3) — 허브 페이지 구축

**산출물:**
- ContentNode 25~35개 (region_id=NULL인 테마 허브 + 정보형)
- DataPoint 입력 UI 안정화
- Search Console API 동기화 cron
- 5개 뷰 중 발행 트리, 매트릭스, 갱신 큐 뷰 완성

### Phase 2 (Month 4~6) — 지역 확장 시작

**산출물:**
- 시도 페이지 50~60개
- KPI 히트맵 뷰 완성
- 사일로 무결성 검사기 자동화
- 데이터 자동 수집 파이프라인 (가능한 경우)

### Phase 3 (Month 7~9) — 시군구 확장

**산출물:**
- 시군구 페이지 110~210개
- 카니발리제이션 검출기 완성
- 자동 리다이렉트 관리 시스템
- 분기 점검 자동화 (약한 페이지 추출 → 결정 화면)

### Phase 4 (Month 10~12) — 동/조합 + 유지보수 모드

**산출물:**
- 동 페이지 100~250개 + 조합 페이지 50~150개
- 모든 5개 뷰 안정 운영
- Year 2 확장 계획 수립
- 자체 데이터 발행 자동화 (분기 리포트 등)

---

## 9. 위험 요인과 백엔드 대응

### 9.1 데이터 입력 병목

가장 흔한 실패. 운영자가 매일 DataPoint를 입력하지 못하면 매트릭스가 비어 있고, 페이지가 발행되지 않습니다.

**대응:**
- 가능한 한 외부 데이터 소스에서 자동 수집 (학교 정보, 학원 데이터 등 공개 API)
- DataPoint 입력 UI를 *5초 안에 1건 추가 가능*하게 설계
- 주간 입력 목표 KPI 설정 (예: 주 50건)

### 9.2 발행 게이트 우회 시도

Month 2~3 충돌 시기에 *"데이터 4개로 발행하면 안 될까?"* 가 반드시 옵니다.

**대응:**
- DB 트리거로 강제 (코드 외부 우회 불가)
- 임시 우회 기능을 *애초에 만들지 않습니다.* 비상 우회 버튼이 곧 일상 우회 버튼이 됩니다.

### 9.3 데이터-페이지 동기화 지연

DataPoint가 추가되는데 페이지가 갱신 안 되면, *Google이 보는 페이지와 실제 데이터가 어긋납니다.*

**대응:**
- ISR 사용 시 `revalidate` 시간을 24시간 이하로
- DataPoint INSERT 시 해당 노드의 `last_review_at` 자동 갱신
- 매일 sitemap.xml의 lastmod 갱신

### 9.4 운영자 이직/교체

12개월 전략을 한 사람이 끝까지 들고 가지 못할 수 있습니다.

**대응:**
- 5개 뷰가 *문서 없이도* 직관적이어야 함
- 모든 결정 규칙이 *코드와 트리거에* 들어 있어야 함 (구두 전승 금지)
- 분기 점검 결과를 자동 기록 (`ContentNode.status` 변경 이력 보존)

---

## 10. 핵심 메시지 정리

1. **사이트맵은 데이터 모델로부터 결정론적으로 도출됩니다.** 손으로 그리지 않습니다.

2. **운영자가 만지는 것은 5개 엔티티(Theme, Region, Author, ContentNode, DataPoint)뿐**입니다. URL이나 HTML을 만지지 않습니다.

3. **사이트맵은 5개 뷰**(발행 트리, 데이터 매트릭스, KPI 히트맵, 사일로 무결성, 갱신 큐)로 이어됩니다. 각 뷰는 다른 결정에 쓰입니다.

4. **6개 백엔드 기능**(발행 게이트, sitemap 생성기, SC 동기화, 카니발리제이션 검출, 사일로 검사, 자동 리다이렉트)이 사이트맵을 이어 있게 유지합니다.

5. **DB 트리거가 사람의 의지를 대체합니다.** Month 2~3 충돌을 굴복하지 않은 시스템적 장치가 코드 레벨에서 강제됩니다.

6. **권장 스택: PostgreSQL + SSG/ISR + 로우코드 대시보드(초기).** 12개월 후 페이지 700개 규모까지 안정 운영 가능합니다.

---

## 11. 즉시 결정해야 할 세 가지

다음이 정해져야 Phase 0 데이터 모델 구현을 진입할 수 있습니다.

1. **백엔드 기술 스택을 무엇으로 할 것인가?**
   추천 조합은 *PostgreSQL + Next.js (ISR) + Retool*입니다. 다른 조합도 가능하지만, 이 보고서의 6개 백엔드 기능을 구현 가능한 스택이어야 합니다.

2. **운영자 인터페이스를 어디까지 자체 구축할 것인가?**
   - 옵션 A: 로우코드 (Retool/Appsmith)로 시작 → Phase 3 이후 자체 구축 검토
   - 옵션 B: 처음부터 자체 React 대시보드
   - 옵션 A를 추천합니다. SEO 시작이 늦어지면 12개월 계획이 지연됩니다.

3. **데이터 자동 수집 범위를 어디까지 잡을 것인가?**
   - 학교 정보, 학원 데이터: 공공 API로 자동 수집 가능
   - 매칭 가능 선생님, 사례, 후기: 이전 데이터에서 자동 추출 가능
   - 인근 지역 차이 분석: 일부 자동화 가능, 일부 수동 작성

이 세 가지가 정해지면 *Phase 0 데이터 모델 ERD + 최소 운영 대시보드 와이어프레임*을 바로 그릴 수 있습니다.

---

## 부록 A — DB 스키마 골격 (PostgreSQL)

```sql
-- THEME
CREATE TABLE theme (
    id          SERIAL PRIMARY KEY,
    slug        VARCHAR(50) NOT NULL,
    name        VARCHAR(100) NOT NULL,
    parent_id   INT REFERENCES theme(id),
    depth       INT NOT NULL CHECK (depth >= 0 AND depth <= 2),
    description TEXT,
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (parent_id, slug)
);

-- REGION
CREATE TABLE region (
    id          SERIAL PRIMARY KEY,
    slug        VARCHAR(50) NOT NULL,
    name        VARCHAR(100) NOT NULL,
    parent_id   INT REFERENCES region(id),
    depth       INT NOT NULL CHECK (depth >= 0 AND depth <= 3),
    admin_code  VARCHAR(20),
    UNIQUE (parent_id, slug)
);

-- AUTHOR
CREATE TABLE author (
    id          SERIAL PRIMARY KEY,
    slug        VARCHAR(50) UNIQUE NOT NULL,
    real_name   VARCHAR(100) NOT NULL,
    credentials TEXT,
    bio         TEXT,
    photo_url   VARCHAR(500),
    verified_at TIMESTAMPTZ
);

-- CONTENT_NODE (핵심)
CREATE TABLE content_node (
    id                 SERIAL PRIMARY KEY,
    theme_id           INT NOT NULL REFERENCES theme(id),
    region_id          INT REFERENCES region(id),
    author_id          INT REFERENCES author(id),
    status             VARCHAR(20) NOT NULL DEFAULT 'draft'
                       CHECK (status IN ('draft','live','noindex','dead')),
    data_count         INT NOT NULL DEFAULT 0,
    intro_text         TEXT,
    body_template_id   INT,
    first_published_at TIMESTAMPTZ,
    last_review_at     TIMESTAMPTZ,
    kpi_snapshot       JSONB,
    UNIQUE (theme_id, region_id)
);

-- DATA_POINT
CREATE TABLE data_point (
    id          SERIAL PRIMARY KEY,
    node_id     INT NOT NULL REFERENCES content_node(id) ON DELETE CASCADE,
    kind        VARCHAR(20) NOT NULL
                CHECK (kind IN ('quantitative','qualitative','comparison','case','faq')),
    title       VARCHAR(200) NOT NULL,
    value       JSONB NOT NULL,
    source      VARCHAR(500),
    verified    BOOLEAN NOT NULL DEFAULT false,
    updated_at  TIMESTAMPTZ DEFAULT NOW()
);

-- 발행 게이트 트리거
CREATE OR REPLACE FUNCTION enforce_publish_gate() RETURNS TRIGGER AS $$
BEGIN
    IF NEW.status = 'live' AND NEW.data_count < 5 THEN
        RAISE EXCEPTION 'Cannot publish node with data_count < 5';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_publish_gate
BEFORE INSERT OR UPDATE ON content_node
FOR EACH ROW EXECUTE FUNCTION enforce_publish_gate();

-- data_count 자동 갱신 트리거
CREATE OR REPLACE FUNCTION refresh_data_count() RETURNS TRIGGER AS $$
BEGIN
    UPDATE content_node
    SET data_count = (
        SELECT COUNT(*) FROM data_point
        WHERE node_id = COALESCE(NEW.node_id, OLD.node_id)
        AND verified = true
    )
    WHERE id = COALESCE(NEW.node_id, OLD.node_id);
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_refresh_data_count
AFTER INSERT OR UPDATE OR DELETE ON data_point
FOR EACH ROW EXECUTE FUNCTION refresh_data_count();
```

## 부록 B — 운영자 일일 체크리스트

매일 출근 후 15~30분.

- [ ] 갱신 큐 뷰 확인 → 우선순위 1~2 처리
- [ ] 이전 추가된 DataPoint 검증 (`verified = true`)
- [ ] 매트릭스 뷰에서 `△` 중 1개 이상의 `✅`로 승급 시도
- [ ] Search Console 알림 점검
- [ ] 사일로 무결성 검사기 결과 확인 → 위반 즉시 처리

## 부록 C — 분기 점검 체크리스트

매 분기 마지막 주 반드시.

- [ ] KPI 히트맵 뷰에서 트래픽 0 페이지 추출
- [ ] 카니발리제이션 검출기 결과 검토 → 통합 결정
- [ ] 90일 이상 미갱신 노드 일괄 검토
- [ ] 새 테마/지역 후보 검토 (시드 데이터 추가)
- [ ] 다음 분기 발행 캘린더 락 (lock)
- [ ] 분기 동안 변경된 status 이력 리뷰
- [ ] 운영 대시보드 5개 뷰의 정확성 점검

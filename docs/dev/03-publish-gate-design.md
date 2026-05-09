# 03. 발행 게이트 설계 — 왜 DB 트리거로 강제하는가

## 1. Month 2~3 함정의 DB 차원 방어

### 1.1 함정의 본질

시스템 설계의 모든 방어 장치는 *하나의 시점*을 막기 위함입니다: **Month 2~3 충돌 시기**.

전략 문서가 명시한 가장 큰 위험:

> 발행 후 두 달이 지나도 트래픽이 거의 없다. 노출은 약간 있지만 클릭은 0에 가깝다.
>
> 이 시점에 운영자(또는 압박받는 임원)가 묻는다:
> *"AI로 동 페이지 3,500개를 한 번에 만들면 어떨까? 노출이라도 늘지 않을까?"*

이 충동에 굴복하면 *6개월 안에 사이트가 60~90% 트래픽 감소*를 겪습니다 (2026년 3월 Google 업데이트로 실제 일어난 일). 이 함정은 *복구 불가능*합니다 — 한 번 약한 페이지를 양산하면 도메인 권위가 깎이고, 약한 페이지를 다 지워도 신호가 회복되는 데 6~12개월 걸립니다.

### 1.2 왜 코드만으로는 부족한가

이 충동을 *애플리케이션 레이어*에서 막는 시도는 다음 이유로 실패합니다:

1. **운영자가 SQL 콘솔에 들어가면** 끝
2. **임시 우회 옵션을 만들면** 그 옵션이 곧 일상이 됨 ("이번만 끄고 발행")
3. **앱 레벨 검증을 끄는 환경 변수**가 있으면 누군가 켬
4. **별도 어드민 권한**으로 우회 가능하게 두면 그 권한이 남용됨

운영 1년차에 모든 우회 경로가 *결국 시도되거나 만들어집니다*. 인간 의지력에 의존하면 안 됩니다.

### 1.3 DB 트리거가 풀어주는 것

발행 게이트를 **PostgreSQL 트리거**로 구현하면:

- 애플리케이션 코드가 무엇을 하든 막힘
- 마이그레이션이 우회 트리거 변경을 *영구히* 기록
- 환경 변수로 끌 수 없음
- DB 직접 INSERT/UPDATE도 차단
- 새 개발자/운영자가 들어와도 같은 보호 적용

비용: PostgreSQL 의존성(다른 DB로 바꾸기 어려움), 트리거 디버깅의 어려움. 이 비용은 *Month 2~3 함정 방지의 가치*보다 훨씬 작습니다.

## 2. `fn_publish_gate` 함수의 구조

### 2.1 함수의 책임

`fn_publish_gate`는 ContentNode에 대한 모든 INSERT/UPDATE에서 실행되며, *`status='live'`로의 전이*를 검증합니다.

검증 규칙은 *콘텐츠 타입별로 다른 게이트 경로*를 통과합니다:

| body_template | 게이트 경로 | 요건 |
|---|---|---|
| `matrix` (또는 NULL) | matrix | `data_count >= 5` |
| `guide` | guide | `body_markdown >= 3000자` AND `verified FAQ >= 3` |
| `essay` | essay | `body_markdown >= 3000자` |
| `report` | report | `body_markdown >= 5000자` |
| `case_study` | case_study | `body_markdown >= 1500자` |

모든 경로는 추가로 `intro_text` 비어있지 않음 + `author.verified_at IS NOT NULL` 요구.

### 2.2 왜 5가지 게이트인가

콘텐츠 타입별로 *발행 의미가 다르기* 때문:

- **매트릭스** (지역 페이지): 데이터의 *수*가 발행 자격. 5개 미만이면 *내용물이 부족*해 사용자에게 가치 없음.
- **가이드**: 본문 길이 + FAQ. how-to 콘텐츠는 *체계적 답변*이 본질이므로 FAQ 필수.
- **에세이**: 본문 길이만. 에디토리얼은 *목소리*가 가치이므로 데이터 점 수가 무의미.
- **리포트**: 본문 길이를 더 높게(5,000+). 권위 콘텐츠는 *깊이*가 핵심.
- **사례**: 본문 길이를 더 낮게(1,500+). 사례는 *짧고 구체적*이 더 강함.

각 콘텐츠 타입의 *SEO 가치*가 다르니 *발행 자격도 달라야* 합니다.

### 2.3 `live → noindex` 자동 강등

게이트의 트리키한 부분: 이미 `live`인 노드의 데이터가 빠지면 어떻게 할까?

**옵션 A**: 예외 던지기 → 운영자가 DataPoint 삭제 시 막힘 → 강등 후 작업 → 재발행. 비효율적.

**옵션 B (선택됨)**: `data_count`가 5 미만으로 떨어지면 *자동으로 `noindex`로 강등*.

```sql
IF TG_OP = 'UPDATE' AND OLD.status = 'live' THEN
    IF gate_kind = 'matrix' AND NEW.data_count < 5 THEN
        NEW.status := 'noindex';   -- 자동 강등
    END IF;
    RETURN NEW;  -- 예외 안 던짐
END IF;
```

이렇게 하면 운영자가 *DataPoint 자유롭게 편집* → 자격 미달 시 시스템이 알아서 노출 중단. 자격 회복하면 운영자가 수동으로 다시 `live`로 승격.

이 결정의 트레이드오프:
- **장점**: 데이터 작업 흐름이 부드러움
- **단점**: 운영자가 "어 왜 강등됐지?" 모를 수 있음 → [/admin/queue](http://localhost:8000/admin/queue)와 KPI 히트맵에서 강등된 노드 추적 가능

### 2.4 게이트 트리거의 마이그레이션 패턴

게이트 함수를 변경할 때 *반드시* 다음 순서:

1. **함수를 먼저 `CREATE OR REPLACE`**
2. **그 다음 row 데이터 업데이트** (있다면)

순서가 거꾸로면 *옛 함수가 새 데이터를 모르는 값으로 보고 잘못 거부/강등*시킵니다. 이 함정은 [Version20260507163246](../../migrations/Version20260507163246.php) 마이그레이션의 코멘트에서 명시됩니다.

## 3. 게이트 외의 트리거들

### 3.1 `fn_refresh_data_count`

**역할**: DataPoint INSERT/UPDATE/DELETE 시 부모 ContentNode의 `data_count` 자동 갱신.

**왜 트리거인가**:
- 애플리케이션 레이어에서 카운트 갱신을 *까먹을 수 있음*
- 검증된(verified=true) 데이터만 카운트해야 함 → 매번 COUNT 쿼리
- 트리거가 *작성 시점*에 강제 실행

```sql
UPDATE content_node
SET data_count = (
    SELECT COUNT(*) FROM data_point
    WHERE node_id = COALESCE(NEW.node_id, OLD.node_id)
    AND verified = true
)
WHERE id = COALESCE(NEW.node_id, OLD.node_id);
```

### 3.2 `fn_parent_live_check`

**역할**: ContentNode가 `live`로 전이할 때, 그 좌표의 *부모 노드도 live*인지 확인.

**왜 필요한가**:
- 권위는 *위에서 아래로* 흐름 (사이트 → 테마 허브 → 시도 → 시군구 → 동)
- 부모가 noindex인데 자식이 live면 권위 흐름이 *단절*된 페이지 (Google이 발견하기 어려움)
- 사일로 무결성 위반

**예외 처리**:
- region이 NULL인 노드(테마 허브)는 부모 체크 면제
- 자식 테마는 *국가 루트 region을 부모로 가지지 않으므로* 트리거가 collapse 처리 ([Version20260506062051](../../migrations/Version20260506062051.php))

### 3.3 `trg_unique_theme_region`

**역할**: ContentNode INSERT 시 `(theme_id, region_id)` 좌표 중복 차단.

**왜 트리거가 아니라 UNIQUE 제약?**: 실제로는 UNIQUE 제약 (`uniq_content_node_theme_region`)이 충분. 트리거 필요 없음.

### 3.4 `fn_dead_cascade_redirect`

**역할**: ContentNode가 `dead`로 전이할 때 자동으로 [Redirect](../../src/Entity/Redirect.php) 행 생성. 가장 가까운 *살아있는 부모*를 찾아 301 등록.

**왜 자동인가**:
- 운영자가 폐기 결정 후 *리다이렉트 목적지를 직접 정하면 실수* 잦음
- 시스템이 *트리에서 가장 가까운 live 조상*을 찾는 것이 일관됨
- 무덤 관리(Cemetery management)가 자동화됨

## 4. 모든 트리거 한눈에

| 트리거 | 발동 시점 | 동작 |
|---|---|---|
| `trg_publish_gate` | BEFORE INSERT/UPDATE ON content_node | 발행 자격 검증, 자동 강등 |
| `trg_refresh_data_count` | AFTER INSERT/UPDATE/DELETE ON data_point | 부모 ContentNode의 data_count 재계산 |
| `trg_parent_live_check` | BEFORE UPDATE ON content_node | live 전이 시 부모도 live인지 |
| `trg_dead_cascade_redirect` | AFTER UPDATE ON content_node | dead 전이 시 Redirect 자동 등록 |
| `trg_region_depth_check` | BEFORE INSERT/UPDATE ON region | parent_id NULL인데 depth>0 거부 |
| `trg_theme_depth_check` | BEFORE INSERT/UPDATE ON theme | depth > 2 거부 |

이 6개가 **데이터 무결성의 모든 책임**을 집니다. 애플리케이션 레이어는 *추가 검증*이지 *유일한 검증*이 아닙니다.

## 5. 왜 PostgreSQL인가 — 다른 DB 검토

DB 선택은 트리거 + Recursive CTE + JSONB 셋이 모두 강력해야 합니다.

| DB | 적합도 | 이유 |
|---|---|---|
| **PostgreSQL** | 매우 적합 | 모든 기능 강력. 트리거 디버깅 도구 풍부. JSONB 인덱싱. |
| MySQL/MariaDB | 부족 | Recursive CTE 지원 약함. JSON 함수 빈약. 트리거 + JSON 조합 디버깅 어려움. |
| SQLite | 매우 부족 | 5,000+ 페이지 단계에서 동시성 한계. 운영 사이트엔 부적합. |
| MongoDB | 부적합 | 트리 구조 + 관계형 무결성 보장 어려움. 트리거 개념 없음. |

PostgreSQL은 *데이터 무결성을 코드 외부에서 보장*하려는 이 시스템 설계와 완벽히 맞습니다. 다른 DB로 마이그레이션하면 트리거 로직을 애플리케이션으로 옮겨야 하고, 그러면 *우회 가능성*이 다시 열립니다.

## 6. 게이트의 한계

DB 트리거가 모든 것을 막지는 못합니다.

### 6.1 막을 수 있는 것
- 운영자가 발행 자격 미달 노드를 `live`로 바꾸려는 시도
- DataPoint 검증 없이 페이지 만드는 시도
- 동일 좌표에 중복 페이지 만드는 시도
- 부모 없는 자식 페이지 만드는 시도
- 작성자 미검증 상태로 발행하는 시도

### 6.2 막을 수 없는 것
- *낮은 품질의* DataPoint 5개 (시스템은 데이터의 *수*만 봄, *질*은 못 봄)
- *템플릿 변수만 다른* 사실상 같은 페이지 (운영자가 진짜 데이터인지 거짓말 가능)
- *카니발리제이션 의심*되는 비슷한 페이지 (다른 좌표지만 같은 키워드 노림)

이 한계들은 *분기 정리* 단계에서 검출/처리됩니다 ([OPERATIONS.md §3](../../OPERATIONS.md)). DB는 *시스템의 첫 번째 방어선*이고, 운영자의 분기 점검이 *두 번째 방어선*입니다.

## 7. 게이트 변경 시 주의사항

게이트 함수를 변경할 때 *반드시 검토해야 할 것*들:

1. **마이그레이션 순서** — 함수 먼저 교체, row 업데이트 나중
2. **기존 live 노드의 강등 위험** — 변경된 게이트에서 기존 노드들이 자동 강등되는지 시뮬레이션
3. **시드 명령어 영향** — `app:seed:demo`가 새 게이트에서 통과하는지
4. **down() 마이그레이션** — 롤백 가능하게 (개발 단계엔 비워도 OK, 운영 단계엔 정확히)
5. **API/폼 메시지** — 사용자에게 보일 에러 메시지가 의미 있게 출력되는지

## 8. 다음 문서

[04. page-system-architecture.md](04-page-system-architecture.md)에서 *데이터에서 페이지가 어떻게 자동 생성되는지*, *왜 디스패처 + 오버라이드 패턴인지*를 다룹니다.

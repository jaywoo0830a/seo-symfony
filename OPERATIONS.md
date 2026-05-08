# 운영 플레이북 (Admin Operations)

이 코드베이스를 *운영자(어드민)* 입장에서 어떻게 관리해야 하는지 정리한 문서. 12개월 SEO 전략 4개 문서(`seo-12month-strategy-final.md`, `sitemap-backend-operations-report.md`, `erd-map.md`, `page-structure-report.md`, `sitemap.md`)에서 도출한 실행 가능한 운영 규칙들.

> 이 문서는 *전략 문서를 어드민의 일상 행동으로 번역*한 것이다. 시스템을 처음 인수받은 운영자가 *오늘 무엇을 해야 하는지*를 알 수 있게 작성했다.

---

## 0. 가장 중요한 한 가지

**운영자는 *페이지*를 만지지 않는다. *데이터*만 만진다.**

URL · HTML · 사이트맵 · 리다이렉트 · 내비게이션은 *모두 코드가 자동 생성*한다. 운영자가 일상에서 만지는 것은 5개 엔티티 중 3개뿐:

| 엔티티 | 일상에서 자주 만지는가 |
|---|---|
| `Theme` | ✕ (분기에 한 번 이하) |
| `Region` | ✕ (도입 시 한 번) |
| `Author` | △ (분기에 한 번) |
| **`ContentNode`** | ◎ (매일) |
| **`DataPoint`** | ◎ (매일) |

이 원칙을 어기면 *시스템이 만든 보호막을 운영자가 우회*하게 되고, 결국 Month 2~3 충돌 시기에 사이트 전체가 무너진다.

---

## 1. 일일 루틴 (15~30분)

매일 출근 후 다음 순서로 처리:

### Step 1 — 갱신 큐 확인 (5분)

URL: [/admin/queue](http://localhost:8000/admin/queue) ([RefreshQueueController](src/Controller/RefreshQueueController.php))

자동으로 우선순위가 매겨진 노드 목록이 뜬다:

| 우선순위 | 트리거 | 처리 방법 |
|---|---|---|
| 1 | 90일 미갱신 | 데이터 1~2건 추가 또는 폐기 결정 |
| 2 | 새 verified DataPoint 있는데 페이지 미반영 | `last_review_at` 갱신 |
| 3 | Search Console 노출 급증 | DataPoint 보강해 인덱스 후보화 |
| 4 | 90일 트래픽 0인 live 노드 | 다음 분기 점검 후보로 마킹 |

**목표**: 우선순위 1, 2를 매일 처리. 3, 4는 주간 단위.

### Step 2 — 매트릭스 빈 칸 채우기 (10~20분)

URL: [/admin/matrix](http://localhost:8000/admin/matrix) ([MatrixController](src/Controller/MatrixController.php))

테마 × 지역의 표가 보인다. 각 칸은 다음 셋 중 하나:
- ●(live, data≥5) — 발행됨
- △(noindex, data 1~4) — 데이터 보강 필요
- ✕(dead, data 0) — 후보 아님

**가장 효율적인 작업**: △ 칸 골라 → ContentNode 안 들어가서 → DataPoint 1~2건 추가 → 검증 토글 → 자동으로 ●로 승격 (DB 트리거가 처리).

지역 페이지의 매트릭스 페이지는 데이터로만 차별화되므로, *DataPoint 한 건이 페이지 한 장의 차별성을 결정*한다.

### Step 3 — DataPoint 검증 (5분)

이전 날 아무가 추가한 DataPoint 중 `verified=false` 항목들. 사실 확인 후 verify 토글.

URL 패턴: `/admin/nodes/{nodeId}/datapoints/{id}/verify` ([DataPointVerifyController](src/Controller/DataPointVerifyController.php))

**중요**: 검증 안 된 DataPoint는 `data_count`에 포함 안 되므로 페이지 게이트도 통과 못 한다. 검증이 발행 병목인 경우가 많다.

---

## 2. 주간 루틴 (월요일 1시간)

### 사일로 무결성 점검 (20분)

URL: [/admin/silo](http://localhost:8000/admin/silo) ([SiloController](src/Controller/SiloController.php))

자동 검사기가 다음을 검출:

| 위반 | 의미 | 대응 |
|---|---|---|
| 부모 없는 노드 | 트리 단절 | 부모 노드 생성 또는 노드 삭제 |
| 다른 테마로 직접 링크 | 사일로 침범 | 링크 제거, 부모 허브 경유로 |
| 깨진 내부 링크 | 404 누적 | URL 갱신 또는 dead 처리 |
| 부모 자식 목록에서 누락된 자식 | 인덱스 트리 불일치 | 자동 등록 트리거 |
| depth 불일치 | 트리 무결성 위반 | 즉시 수정 |

### draft → live 검토 (20분)

[/admin/nodes](http://localhost:8000/admin/nodes)에서 status=draft 필터.

각 draft 노드를 보고:
- DataPoint 5개 이상 검증됐는가?
- intro_text 작성됐는가?
- 작성자가 verified인가?
- 부모 노드가 live인가?

모두 ✓면 status=live로 변경 → DB 트리거가 게이트 검증 → 통과 시 자동 발행.

### Search Console 알림 (20분)

(외부 도구) Search Console에서 다음 확인:
- Soft 404 경고 → 즉시 통합/삭제
- 중복 콘텐츠 경고 → 카니발리제이션 의심, 페이지 페어 검토
- 인덱스 제외 페이지 증가 → 추세 모니터링

---

## 3. 분기 루틴 (분기 마지막 주, 반나절)

가장 중요한 루틴. *코드가 자동 처리하지 못하는 결정*들이 모두 여기 모인다.

### Step 1 — KPI 히트맵 검토 (1시간)

[/admin/matrix](http://localhost:8000/admin/matrix) (또는 별도 KPI 뷰)에서 Search Console 데이터와 매핑된 결과 검토:

| 신호 | 판단 |
|---|---|
| 노출↑ + 클릭↑ + 평균순위↓ | 유지 — 정상 성장 |
| 노출↑ + 클릭= + 평균순위 = | 보강 필요 — 메타 재작성, FAQ 추가 |
| 노출= + 클릭= + 6개월 미인덱스 | 통합 검토 — 다른 노드와 머지 |
| 90일 트래픽 0 | 폐기 검토 (status=dead) |

### Step 2 — 약한 페이지 정리 (1~2시간)

전략 문서의 가장 강한 경고: **"약한 페이지가 30%를 넘으면 도메인 전체 권위가 떨어진다."**

원칙:
1. *방치는 선택지가 아니다.* 살리거나, 통합하거나, 삭제.
2. 통합: 두 약한 페이지의 데이터를 한 페이지로 모으고 다른 하나는 dead → 자동 301
3. 삭제: status=dead로 변경 → 시스템이 가장 가까운 부모를 찾아 자동 301 등록

### Step 3 — 카니발리제이션 점검 (30분)

[/admin/search](http://localhost:8000/admin/search) ([AdminSearchController](src/Controller/AdminSearchController.php))로 같은 키워드 후보 페어 점검.

같은 키워드를 두 페이지가 노릴 때:
- 더 강한 페이지(트래픽 많은 쪽)로 데이터 통합
- 약한 쪽은 dead로 → 301 자동 등록

### Step 4 — 다음 분기 발행 캘린더 락 (30분)

다음 분기에 발행할 페이지 후보 목록을 미리 정리. 이렇게 하면 *분기 중간에 즉흥 결정으로 약한 페이지를 양산하는 것*을 막는다.

### Step 5 — 자체 데이터 리포트 발행 (반나절~하루)

분기 1편 발행이 SEO 전략의 가장 강력한 단일 자산. [/reports/](http://localhost:8000/reports/) 아래 자식 테마 + ContentNode (`body_template=report`) 추가.

리포트 1편이 가이드 10편보다 권위 빌딩에 효과적. *분기마다 거르지 말 것.*

---

## 4. 12개월 페이즈별 우선순위

전략 문서의 Phase 0~4를 *운영자가 어떤 페이지에 집중해야 하는지*로 번역.

| 페이즈 | 기간 | 발행 대상 | 운영자가 집중할 것 |
|---|---|---|---|
| **0** | Week 1~2 | 0개 (인프라만) | DataPoint 5종 입력 UI 숙련, 매트릭스 화면 적응 |
| **1** | Month 1~3 | 25~35개 (허브 + 가이드) | 깊은 본문 작성 (5,000자+ 허브, 3,000자+ 가이드). 지역 페이지 *유혹 차단* — 데이터가 없으면 안 만든다 |
| **2** | Month 4~6 | +51개 (시도 17 × 테마 3) | 시도 단위 보강. Search Console에서 첫 신호 보고 *어떤 시도가 강한지* 식별 |
| **3** | Month 7~9 | +110~210개 (시군구) | **신호 받은 시군구만** 발행. 데이터 5건 미달이면 절대 발행 X. 분기 정리 시작 |
| **4** | Month 10~12 | +150~400개 (동/조합) | 인덱스된 시군구만 동 단위 확장. 약한 페이지 정리 강화 |

**Phase 1의 가장 큰 함정 — 즉시 결정해야 할 것**:
- "Month 2~3에 트래픽이 거의 없을 텐데, 견딜 수 있는가?" — 견디지 못하면 *AI로 동 페이지 3,500개 한 번에* 같은 짓을 한다 → 사이트 사망
- *조급함을 막는 시스템적 장치*: DB 트리거의 발행 게이트. 운영자가 우회하지 못하게 *코드 레벨로* 막혀 있다

---

## 5. 5개 사이트맵 뷰 — 어떤 결정에 어떤 뷰를 쓰는가

전략 문서의 5개 뷰는 *각각 다른 의사결정에 쓰이는 도구*다. 헷갈리지 말 것.

| 뷰 | 용도 | 빈도 | 어디서 |
|---|---|---|---|
| **발행 트리 (Public Tree)** | sitemap.xml 생성, 일상 모니터링 | 자동 | `/sitemap.xml` |
| **데이터 매트릭스** | 매일 작업 우선순위 결정 | 매일 | [/admin/matrix](http://localhost:8000/admin/matrix) |
| **KPI 히트맵** | 분기 정리 (살릴지/죽일지) | 분기 | (Search Console + DB 조인 — 별도 뷰 필요) |
| **사일로 무결성** | 구조 점검, 위반 즉시 알림 | 주간 | [/admin/silo](http://localhost:8000/admin/silo) |
| **갱신 큐** | 일일 작업 목록 | 매일 | [/admin/queue](http://localhost:8000/admin/queue) |

운영자는 매일 *데이터 매트릭스 + 갱신 큐* 두 개만 본다. 주간엔 *사일로 무결성* 추가. 분기엔 *KPI 히트맵* 추가.

---

## 6. KPI — 분기별 목표

전략 문서 §7의 KPI를 운영자 측정 가능한 형태로 정리.

### 선행 지표 (Phase 1~2)

분기마다 측정. 못 미치면 다음 분기 발행 페이스 늦춤.

| 지표 | Q1 목표 | Q2 목표 | 측정 위치 |
|---|---|---|---|
| Google 인덱스 페이지 수 | 발행 80%+ | 발행 85%+ | Search Console |
| Search Console 일 노출 | 100~300 | 1,000~3,000 | Search Console |
| 평균 검색 순위 | 30~50위 | 20~40위 | Search Console |
| 사이트 평균 체류 시간 | 1분+ | 1분 30초+ | GA4 또는 자체 추적 |

### 후행 지표 (Phase 3~4)

| 지표 | Q3 목표 | Q4 목표 |
|---|---|---|
| 자연 검색 일 방문자 | 200~500 | 800~1,500 |
| 키워드 상위 10위 수 | 30~60개 | 80~150개 |
| 키워드 상위 3위 수 | 5~15개 | 20~40개 |
| AI 검색 인용 빈도 (월간) | 측정 시작 | 10~30회 |

### 위험 지표 (모든 분기에서 점검)

이 지표들이 임계치 넘으면 즉시 대응:

| 지표 | 위험 임계값 | 대응 |
|---|---|---|
| 인덱스되지 않은 페이지 비율 | 30%+ | 페이지 품질 점검, 약한 페이지 통합 |
| Soft 404 / 중복 콘텐츠 경고 | 10건+ | 즉시 통합/삭제 |
| 평균 클릭률(CTR) | 1% 미만 | 제목/메타 재작성 |
| Core Web Vitals "Poor" | 페이지 25%+ | 기술 점검 (이미지·JS·캐시) |

---

## 7. 위험 신호와 대응 매뉴얼

### 신호 1 — Month 2~3 트래픽 0의 유혹

**증상**: 발행 후 두 달이 지나도 트래픽이 거의 없음. 노출은 조금 있지만 클릭이 안 옴.

**유혹**: "AI로 한꺼번에 페이지 수백 장을 만들면 노출이 늘지 않을까?"

**대응**:
1. *절대 안 한다.* 이게 2026년 3월 Google 업데이트로 60~90% 트래픽 감소를 겪은 사이트들의 공통 행동.
2. Phase 1에서 *데이터 게이트가 코드 레벨로 막혀 있다*는 사실을 기억.
3. 견딘다 — 지속 발행은 Month 3~4에 첫 클릭이 들어오기 시작.

### 신호 2 — 카니발리제이션

**증상**: 같은 키워드를 두 페이지가 노림. 둘 다 인덱스되지만 어느 쪽도 1페이지에 못 감.

**대응**:
1. [/admin/search](http://localhost:8000/admin/search)에서 후보 페어 식별
2. 트래픽 많은 쪽에 데이터 통합
3. 약한 쪽 status=dead → 자동 301

### 신호 3 — 약한 페이지 누적

**증상**: 트래픽 0 페이지가 30%를 넘음. 도메인 전체 평균 순위 하락.

**대응**: 분기 정리 강화. *방치하면 사이트 전체*가 다친다.

### 신호 4 — 테마 분산

**증상**: 테마 5개 이상으로 늘어남. 어느 테마에서도 권위가 안 잡힘.

**대응**:
1. 테마 *추가는 결정이지 자연스러운 일이 아니다*.
2. 새 테마 추가 전: 기존 테마 중 *가장 깊은 한 개*가 충분히 권위 있는지 먼저 검증.
3. 권위 없는 테마는 *접거나 통합*.

---

## 8. 의사결정 플레이북 (이런 상황엔 이렇게)

### Q: 새 페이지를 만들어야 할 것 같다.

먼저 답해야 할 3가지:
1. *DataPoint 5개를 verified 상태로 채울 수 있는가?* — 못 하면 만들지 말 것.
2. *기존 페이지와 키워드가 겹치지 않는가?* — 겹치면 기존 페이지에 데이터 보강이 답.
3. *부모 노드가 live인가?* — 아니면 부모부터 발행.

세 답이 모두 ✓면: [/admin/themes/new](http://localhost:8000/admin/themes/new) 또는 [/admin/nodes/new](http://localhost:8000/admin/nodes/new).

### Q: 페이지 트래픽이 0인 지 90일 됐다.

옵션 셋:
1. **데이터 보강**: 새 DataPoint 2~3건 + 본문 갱신 → `last_review_at` 갱신
2. **통합**: 인접 페이지에 데이터 머지 → 이 페이지는 dead → 301
3. **삭제**: status=dead → 시스템이 부모 찾아 자동 301

*방치는 선택지가 아니다.*

### Q: 권위 있는 페이지로 만들고 싶다.

- 매트릭스 페이지(`tutoring/seoul/...`): DataPoint 깊이를 늘림. 5개 → 10개 → 15개. *데이터의 깊이가 권위*다.
- 가이드/에세이: 본문 길이 + 작성자 verified + 출처 인용. 페이지 자체로 *citation worthy*하게.
- 리포트: 분기마다 발행. 1년에 4편이면 외부 인용 자석.
- 사례 연구: 매월 1편. E-E-A-T의 가장 강한 신호.

### Q: 새 테마를 추가하고 싶다.

질문 셋:
1. *자체 데이터를 쌓을 수 있는 영역인가?* — 못 만들면 추가하지 말 것.
2. *기존 테마와 키워드 분리되는가?* — 안 되면 기존 테마 자식 테마로.
3. *최소 5개 노드를 발행할 수 있는가?* — 1~2개 페이지만 만들 거면 추가 안 함.

세 답 모두 ✓면 [/admin/themes/new](http://localhost:8000/admin/themes/new).

### Q: 발행 게이트 때문에 페이지가 안 올라간다.

게이트는 *우회하면 안 된다*. 하지만 메시지를 정확히 읽어야:
- `data_count >= 5` 미달 → DataPoint 추가 + verified
- `intro_text empty` → 인트로 작성
- `verified author required` → AuthorVerifyController로 작성자 검증
- `parent live check fail` → 부모 노드 먼저 live로

---

## 9. 백엔드 6대 자동 기능 — 운영자가 알아야 할 것

운영자가 직접 호출하지 않지만 *알아야 할* 자동 동작들:

| 기능 | 트리거 | 운영자 영향 |
|---|---|---|
| 발행 게이트 (`fn_publish_gate`) | status='live' 시도 | 우회 불가. 게이트 통과 못 하면 그 이유가 곧 작업 항목 |
| `data_count` 자동 갱신 | DataPoint INSERT/UPDATE/DELETE | 검증 토글만 해도 자동 반영 |
| sitemap.xml 자동 생성 | cron 03:00 | 직접 편집 X |
| Search Console 동기화 | cron 04:00 | 직접 호출 X. KPI 뷰가 사용 |
| 카니발리제이션 검출 | 분기 자동 실행 | 결과 검토 후 통합 결정 |
| 자동 리다이렉트 | status='dead' 변경 시 | 직접 등록 X. 무덤 관리 자동 |

---

## 10. 운영자 도구 빠른 참조

대시보드 핵심 화면 (모두 `/admin/` 아래):

| URL | 용도 | 빈도 |
|---|---|---|
| [/admin](http://localhost:8000/admin) | 대시보드 홈 | 매일 진입점 |
| [/admin/queue](http://localhost:8000/admin/queue) | 갱신 큐 | 매일 |
| [/admin/matrix](http://localhost:8000/admin/matrix) | 매트릭스 | 매일 |
| [/admin/silo](http://localhost:8000/admin/silo) | 사일로 무결성 | 주간 |
| [/admin/search](http://localhost:8000/admin/search) | 카니발리제이션 검색 | 분기 |
| [/admin/nodes](http://localhost:8000/admin/nodes) | 노드 목록 (필터링 가능) | 자주 |
| [/admin/nodes/new](http://localhost:8000/admin/nodes/new) | 새 페이지 | 분기 1~2회 |
| [/admin/themes](http://localhost:8000/admin/themes) | 테마 관리 | 거의 안 씀 |
| [/admin/regions](http://localhost:8000/admin/regions) | 지역 관리 | 거의 안 씀 |
| [/admin/authors](http://localhost:8000/admin/authors) | 작성자 관리 | 분기 |

CLI 명령:

```bash
# 데모 시드 (개발 단계)
php bin/console app:seed:demo [--reset]

# 관리자 추가
php bin/console app:admin:create <email> <password>

# 마이그레이션
php bin/console doctrine:migrations:migrate

# 캐시 무효화 (콘텐츠 변경 후 페이지가 안 보이면)
# SQL: UPDATE content_node SET last_review_at = NOW() WHERE status='live';
```

---

## 11. 핵심 메시지 5가지

1. **운영자는 데이터만 만진다.** URL · HTML · 사이트맵 · 리다이렉트는 코드가 자동 생성.

2. **매일 두 화면만 본다.** 갱신 큐 + 매트릭스. 그 외엔 *행동 트리거 있을 때만*.

3. **분기 정리가 발행만큼 중요하다.** 약한 페이지 30% 넘으면 사이트 전체가 다친다.

4. **Month 2~3 충돌을 견딘다.** 이 시기에 양산 유혹에 굴복하면 12개월 계획이 무너진다. 발행 게이트가 코드 레벨로 막혀 있다.

5. **자체 데이터 발행이 가장 강력한 단일 자산.** 분기 1편 리포트 + 매월 1편 사례 연구. 거르지 말 것.

---

## 12. 첫 30일 액션 플랜

신규 운영자가 인수받았을 때의 30일 가이드.

### Day 1~3 — 시스템 이해

- [ ] [CUSTOMIZATION.md](CUSTOMIZATION.md) 정독 (구조 이해)
- [ ] [/admin/matrix](http://localhost:8000/admin/matrix) 둘러보기 (현 상태 파악)
- [ ] DataPoint 1건 직접 추가 + 검증 (워크플로우 체험)
- [ ] 시드 데이터로 *어떤 페이지가 나오는지* 17개 URL 직접 방문

### Day 4~14 — 일일 루틴 정착

- [ ] 매일 갱신 큐 처리
- [ ] 매트릭스 △ 칸 매일 1~2개 채우기
- [ ] 새 DataPoint 매일 검증
- [ ] 일주일 끝에 사일로 무결성 점검 한 번

### Day 15~30 — 분기 루틴 학습

- [ ] KPI 히트맵 활용법 익히기 (Search Console 연결 확인)
- [ ] 첫 자체 데이터 리포트 1편 작성 (`/reports/` 아래 자식 테마)
- [ ] 첫 사례 연구 1편 작성 (`/cases/` 아래)
- [ ] 분기 마지막 주의 정리 루틴 시뮬레이션

### Day 30 시점에 가능해야 할 것

운영자 본인이 *오늘 무엇을 해야 하는지*를 시스템 도움 없이 결정 가능해야 함. 매트릭스 화면 한 번 보고 *5분 안에* 작업 우선순위가 잡혀야 정상.

---

이 문서는 *전략을 행동으로 번역*한 것이다. 시스템이 만들어준 보호막 안에서 일하면 12개월 계획이 자동으로 따라온다. 보호막 밖으로 나가려는 유혹이 들 때마다 §0을 다시 읽을 것.

# 전략 문서 — 시스템의 *왜*가 시작된 곳

이 디렉토리는 코드베이스 *이전*에 작성된 5개의 기획 문서입니다. `docs/dev/`(왜 이렇게 설계됐는가)·`docs/contribution/`(어디를 만져야 하는가)·`docs/ai/`(시스템 룰 빠른 조회)·`OPERATIONS.md`(오늘 무엇을 해야 하는가)는 모두 *이 5개 문서의 결정들*에서 도출된 응축본입니다.

> 원래 코드베이스 외부에 있었으나, 매번 LLM 컨텍스트로 업로드하지 않도록 본 저장소에 포함했습니다.

## 5개 문서

| # | 파일 | 다루는 질문 | 길이 |
|---|---|---|---|
| 1 | [seo-12month-strategy-final.md](seo-12month-strategy-final.md) | 12개월 동안 *왜* 이런 순서로 일하는가 — 전체 전략 | 약 450줄 |
| 2 | [erd-map.md](erd-map.md) | 데이터 모델의 *무엇* — 5개 엔티티와 제약 | 약 250줄 |
| 3 | [sitemap.md](sitemap.md) | 사이트맵의 *5가지 뷰* — 트리·매트릭스·KPI·사일로·갱신 큐 | 약 300줄 |
| 4 | [page-structure-report.md](page-structure-report.md) | 페이지 한 장이 *어떻게* 만들어지는가 — 10개 섹션과 4개 템플릿 | 약 350줄 |
| 5 | [sitemap-backend-operations-report.md](sitemap-backend-operations-report.md) | 백엔드의 *6가지 핵심 기능* — 운영을 지속 가능하게 만드는 것들 | 약 400줄 |

## 읽기 순서

처음 보는 사람용:

1. **`seo-12month-strategy-final.md`** — *왜* 이 일을 12개월에 걸쳐 하는가 (전략)
2. **`erd-map.md`** — *무엇*으로 표현하는가 (데이터 모델)
3. **`sitemap.md`** — *어떻게* 운영자가 매일 결정하는가 (5개 뷰)
4. **`page-structure-report.md`** — 페이지 *한 장*이 어떻게 만들어지는가 (구조)
5. **`sitemap-backend-operations-report.md`** — 백엔드가 운영을 *지속 가능*하게 만드는 6가지 기능

전체 읽기 시간: 2~3시간.

## 코드베이스와의 매핑

이 5개 전략 문서의 결정들이 코드베이스의 어디에 응축되었는지:

| 전략 문서의 개념 | 응축된 곳 | 비고 |
|---|---|---|
| Hub-and-Spoke + Geographic Matrix | [docs/dev/01-strategic-context.md](../dev/01-strategic-context.md) | 왜 이 구조인가 |
| 5개 엔티티 (Theme/Region/Author/ContentNode/DataPoint) | [docs/dev/02-data-model-rationale.md](../dev/02-data-model-rationale.md), [docs/ai/02-entities.md](../ai/02-entities.md) | 책임과 제약 |
| 발행 게이트 (DB 트리거) | [docs/dev/03-publish-gate-design.md](../dev/03-publish-gate-design.md), [migrations/](../../migrations/) | `fn_publish_gate` 함수 |
| 5가지 뷰 (트리·매트릭스·KPI·사일로·갱신 큐) | [docs/ai/06-vocabulary.md §7](../ai/06-vocabulary.md), `/admin/matrix`, `/admin/silo`, `/admin/queue` | 어드민 화면 |
| 4가지 페이지 템플릿 (T_HUB/T_SIDO/T_SIGUNGU/T_DONG) | [docs/dev/04-page-system-architecture.md](../dev/04-page-system-architecture.md), [templates/public/matrix/](../../templates/public/matrix/) | 디스패처 + 매트릭스 셸 |
| 12개월 페이즈 | [docs/dev/05-roadmap-and-risks.md](../dev/05-roadmap-and-risks.md) | Phase 0~4 |
| 일일/주간/분기 루틴 | [OPERATIONS.md](../../OPERATIONS.md) | 운영자 워크플로우 |

## 본 디렉토리를 언제 보는가

- 새 팀원 온보딩 — *왜* 이렇게 설계됐는지 가장 깊은 출발점이 필요할 때
- 큰 결정 검토 — 시스템 근본 변경 전 원래 의도 확인
- 12개월 도달 후 Year 2 설계 — 무엇이 충족되고 무엇이 남았는지 점검

일상 개발/운영은 `docs/dev/`, `docs/contribution/`, `OPERATIONS.md`로 충분합니다.

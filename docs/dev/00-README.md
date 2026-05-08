# 개발자 문서 — 왜 이렇게 설계되었나

이 디렉토리는 **이 코드베이스를 인수받은 개발자**가 *왜 이렇게 설계되어 있는지*를 이해할 수 있게 정리한 문서입니다. 단순히 *어디를 만지는지*([CUSTOMIZATION.md](../../CUSTOMIZATION.md)) 또는 *오늘 무엇을 할지*([OPERATIONS.md](../../OPERATIONS.md))가 아니라, **시스템의 결정들이 어떤 제약과 트레이드오프에서 나왔는지** 설명합니다.

## 읽는 순서

이 문서들은 *순서대로 읽을 때* 가장 잘 이해됩니다. 각 문서가 다음 문서의 전제를 깔아 놓습니다.

| # | 문서 | 다루는 질문 |
|---|---|---|
| 01 | [strategic-context.md](01-strategic-context.md) | 왜 이 시스템이 존재하는가? 12개월 SEO 문제는 무엇인가? |
| 02 | [data-model-rationale.md](02-data-model-rationale.md) | 왜 5개 엔티티인가? 다른 모델로 안 되는 이유는? |
| 03 | [publish-gate-design.md](03-publish-gate-design.md) | 왜 DB 트리거로 게이트를 강제하는가? |
| 04 | [page-system-architecture.md](04-page-system-architecture.md) | 왜 디스패처 + 오버라이드 패턴인가? |
| 05 | [roadmap-and-risks.md](05-roadmap-and-risks.md) | 왜 12개월에 걸친 페이즈인가? 가장 위험한 순간은? |

읽기 시간: 전체 약 60~90분.

## 빠른 답이 필요할 때

- **"왜 ContentNode에 (theme, region) UNIQUE인가?"** → [02 §2.4](02-data-model-rationale.md#24-contentnode--좌표의-점)
- **"왜 운영자가 발행을 강제할 수 없게 막혀 있나?"** → [03 §1](03-publish-gate-design.md#1-month-23-함정-의-DB-차원-방어)
- **"왜 지역 페이지는 오버라이드 불가인가?"** → [04 §3](04-page-system-architecture.md#3-우선순위와-region-노드의-제약)
- **"왜 12개월인가? 더 짧으면 안 되나?"** → [05 §2](05-roadmap-and-risks.md#2-왜-12개월인가)

## 관련 문서

| 문서 | 독자 | 용도 |
|---|---|---|
| [CUSTOMIZATION.md](../../CUSTOMIZATION.md) | 개발자 (입양/확장) | 어디를 만져야 하는가 |
| [OPERATIONS.md](../../OPERATIONS.md) | 운영자 (어드민) | 오늘 무엇을 해야 하는가 |
| [docs/ai/](../ai/) | LLM | 시스템 룰 빠른 조회 |
| **본 디렉토리** | **개발자 (구조 이해)** | **왜 이렇게 만들어졌는가** |

## 원본 전략 문서

이 문서들의 *근거*는 다음 5개 외부 문서에서 나옵니다:

1. `seo-12month-strategy-final.md` — 전체 전략
2. `sitemap-backend-operations-report.md` — 백엔드 운영 구조
3. `erd-map.md` — ERD 지도
4. `page-structure-report.md` — 페이지 구조와 엔티티 매핑
5. `sitemap.md` — 사이트맵의 5개 뷰

이 문서들은 *코드베이스 외부*의 기획 문서이므로 본 저장소에 포함되지 않을 수 있습니다. 본 dev/ 디렉토리는 그 결정들을 *코드와 함께* 보존하기 위한 응축본입니다.

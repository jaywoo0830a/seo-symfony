# AI 문서 — 시스템 룰 빠른 조회

이 디렉토리는 **LLM이 이 코드베이스에 대한 질문에 답하거나 변경 결정을 내릴 때** 빠르게 참조할 수 있는 룰북입니다. 자연어 설명보다 *결정 표·룰·매핑*이 우선입니다.

## 디렉토리 구조

| 파일 | 내용 | 사용 시점 |
|---|---|---|
| [01-premise.md](01-premise.md) | 시스템 전제와 5가지 불변성 | 시스템의 *왜*를 답할 때 |
| [02-entities.md](02-entities.md) | 5개 엔티티 + 제약 + 강제 위치 | 엔티티 관련 결정 |
| [03-page-derivation.md](03-page-derivation.md) | 데이터 → 페이지 도출 룰 | 렌더링/오버라이드 결정 |
| [04-content-types.md](04-content-types.md) | 5개 BodyTemplate 카탈로그 | 콘텐츠 타입 선택 |
| [05-decision-rules.md](05-decision-rules.md) | Q&A 형식 결정 룰 | "X일 때 어떻게 해야?" 답변 |
| [06-vocabulary.md](06-vocabulary.md) | 한국어 ↔ 영문 ↔ 코드 ↔ DB 매핑 | 용어 통합 |

## 핵심 불변성 (변하지 않는 것들)

다음 5가지는 *시스템의 절대 룰*입니다. 어떤 변경 결정도 이를 위반하면 안 됩니다.

| # | 불변성 | 강제 위치 |
|---|---|---|
| 1 | 같은 (theme, region) 좌표 위 페이지는 정확히 1개 | DB UNIQUE 제약 |
| 2 | data_count 5 미만 노드는 status='live' 불가 (매트릭스) | DB 트리거 `fn_publish_gate` |
| 3 | 부모 노드가 live 아니면 자식 노드는 live 불가 | DB 트리거 `fn_parent_live_check` |
| 4 | 작성자 미검증 노드는 live 불가 | DB 트리거 `fn_publish_gate` |
| 5 | status='dead' 노드는 자동으로 Redirect 행 생성 | DB 트리거 `fn_dead_cascade_redirect` |

## 핵심 결정 규칙 한눈에

다음 룰들이 *95%의 흔한 질문*에 답합니다. 더 깊은 룰은 [05-decision-rules.md](05-decision-rules.md).

| 질문 | 답 |
|---|---|
| 어떤 엔티티가 페이지를 만드나? | `ContentNode`만. 다른 4개는 좌표축이거나 내용물. |
| URL은 어떻게 생성되나? | `(theme_path, region_path)` 함수의 출력. 손으로 안 만듦. |
| 페이지 타입은 어떻게 결정되나? | `bodyTemplate` 명시 → 그것 사용. 없으면 region.depth로 자동. |
| 오버라이드 가능한 페이지는? | `region IS NULL`인 노드만. 지역 페이지는 영원히 불가. |
| 발행 게이트는 어디서 강제되나? | DB 트리거 `fn_publish_gate`. 코드 외부에서 보장. |
| 5가지 게이트 경로는? | matrix / guide / essay / report / case_study |
| 새 콘텐츠 타입 추가는 몇 단계? | 7단계 (enum, migration, dispatcher, template, scss, form, seed) |

## 관련 문서

| 문서 | 독자 | 용도 |
|---|---|---|
| [docs/dev/](../dev/) | 개발자 | 왜 이렇게 설계됐는가 |
| [docs/contribution/](../contribution/) | 개발자 (입양/확장) | 어디를 만져야 하는가 |
| [OPERATIONS.md](../../OPERATIONS.md) | 운영자 (어드민) | 오늘 무엇을 해야 하는가 |
| **본 디렉토리** | **LLM** | **시스템 룰 빠른 조회** |

## 사용 패턴

LLM이 이 디렉토리를 사용하는 일반적 흐름:

```
사용자 질문 도착
    ↓
[06-vocabulary.md]에서 용어 통합
    ↓
질문 유형 판단:
- "X 엔티티에 대해 알려줘" → [02-entities.md]
- "이 페이지가 어떻게 렌더링돼?" → [03-page-derivation.md]
- "X 콘텐츠 타입은 뭐야?" → [04-content-types.md]
- "Y일 때 어떻게 해야 해?" → [05-decision-rules.md]
- 일반 원칙 → [01-premise.md]
```

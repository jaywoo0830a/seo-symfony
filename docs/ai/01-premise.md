# 01. 시스템 전제

## 1. 정의

이 시스템은 **다(多)테마 × 지역 키워드 매트릭스** SEO 사이트를 12개월에 걸쳐 운영하는 도구입니다.

| 용어 | 정의 |
|---|---|
| 테마 | 사이트가 다루는 카테고리 (예: 과외, 학원, 학습 콘텐츠) |
| 지역 | 검색 의도의 지리적 차원 (시도/시군구/동) |
| 매트릭스 | 테마 × 지역의 좌표 공간 |
| 셀 (cell) | 매트릭스의 한 좌표 = 잠재적 페이지 한 장 |
| 발행 가능한 셀 | DataPoint 5개 이상 채울 수 있는 셀 |

## 2. 핵심 전제 (System Premises)

### 2.1 단일 결정 변수

> 시스템 성공의 결정 변수: **발행 가능한 셀의 개수**

이 숫자가 곧 *최종 도달 가능 페이지 수의 상한*입니다. 다른 모든 설계 결정은 이 변수를 *발견하고 보존하기 위한* 도구입니다.

### 2.2 데이터 우선 (Data-First)

| 명제 | 의미 |
|---|---|
| URL은 데이터의 함수 | `(theme, region)` 좌표가 URL 결정 |
| 사이트맵은 데이터의 그림자 | `WHERE status='live'` SELECT 결과 |
| 페이지 발행은 데이터 충족 시 자동 | `data_count >= 5`가 트리거 |
| 운영자는 5개 엔티티만 만짐 | URL/HTML/사이트맵은 *코드가 자동 생성* |

### 2.3 게이트 우선 (Gate-First)

발행 자격은 *코드 외부*에서 보장되어야 합니다. 사람의 의지력(또는 우회 충동)에 의존하면 Month 2~3 충돌 시기에 무너집니다.

| 게이트 | 강제 위치 |
|---|---|
| 데이터 5개 미만 → 발행 불가 | DB 트리거 `fn_publish_gate` |
| 부모 미발행 → 자식 발행 불가 | DB 트리거 `fn_parent_live_check` |
| 작성자 미검증 → 발행 불가 | DB 트리거 `fn_publish_gate` |
| 좌표 중복 → INSERT 실패 | DB UNIQUE 제약 |

## 3. 5가지 불변성 (Invariants)

이 시스템이 *설계만으로* 영구 보장하는 것들:

| # | 불변성 | 위반 불가능한 이유 |
|---|---|---|
| 1 | 같은 (theme, region) 페이지는 1개 | UNIQUE 제약이 INSERT 거부 |
| 2 | 매트릭스 노드의 live ⟹ data_count >= 5 | trg_publish_gate가 거부 또는 자동 강등 |
| 3 | 자식 노드의 live ⟹ 부모도 live | trg_parent_live_check가 거부 |
| 4 | live 노드 ⟹ 작성자가 verified | trg_publish_gate 확장 |
| 5 | dead 노드 ⟹ Redirect 행 존재 | trg_dead_cascade_redirect가 자동 생성 |

이 5가지는 *애플리케이션 코드가 무엇을 하든* 유지됩니다.

## 4. 도출 함수들 (Derivation Functions)

5개 엔티티 상태에서 자동 도출되는 것들:

| 도출 결과 | 도출 방식 | 코드 |
|---|---|---|
| URL | `theme_path(theme) + region_path(region)` | `UrlBuilder::build()` |
| sitemap.xml | `SELECT WHERE status='live'` | `SitemapController` |
| 빵부스러기 | region 트리 + theme 트리 walk | `BreadcrumbBuilder` |
| 부모 노드 | `findOneBy(theme=t, region=region.parent)` | `findParentOf()` |
| 자식 노드 | `findBy(theme=t, region.parent=region)` | `findChildrenOf()` |
| 형제 테마 노드 | `findBy(theme.parent=t.parent)` | `findThemeSiblingsOf()` |
| 매트릭스 셸 선택 | `matrix/{root_theme.slug}.html.twig` | `findMatrixTemplate()` |
| 페이지 오버라이드 | `hubs/{theme_path}.html.twig` (region IS NULL일 때만) | `findTemplateOverride()` |
| Markdown → HTML | `league/commonmark` | `renderMarkdown()` |
| JSON-LD | author + theme + region + DataPoint 합성 | `buildJsonLd()` |

## 5. 페이지 4가지 유형

| 유형 | 좌표 조건 | 예시 URL | 컨트롤러 |
|---|---|---|---|
| 루트 | (ContentNode 아님) | `/` | `PublicHomeController` |
| 테마 허브 | region=NULL AND theme.parent=NULL | `/tutoring/` | `PublicNodeController` |
| 지역 페이지 | region != NULL | `/tutoring/seoul/` | `PublicNodeController` |
| 자식 테마 페이지 | region=NULL AND theme.parent != NULL | `/guides/how-to-choose-tutor/` | `PublicNodeController` |

## 6. 운영자 워크플로우 빈도

운영자는 5개 엔티티만 만집니다. 빈도:

| 엔티티 | 빈도 | 일상 작업 |
|---|---|---|
| Theme | 분기 1회 미만 | 새 테마 추가 (전략 결정) |
| Region | 도입 시 한 번 | 행정구역 시드 |
| Author | 분기 1~2회 | 새 작성자 등록 + 검증 |
| ContentNode | 매일 | 새 좌표에 점 찍기, 상태 전이 |
| DataPoint | 매일 (가장 자주) | 5가지 kind로 데이터 입력 + 검증 |

URL/HTML/사이트맵은 *절대* 운영자가 만지지 않습니다.

## 7. 12개월 페이즈

| 페이즈 | 기간 | 페이지 발행 | 핵심 결정 |
|---|---|---|---|
| 0 | Week 1~2 | 0개 | 인프라 구축 |
| 1 | Month 1~3 | 25~35 | 깊은 허브로 토픽 권위 시드 |
| 2 | Month 4~6 | +51 | 시도 17 × 테마 매트릭스 row 1 |
| 3 | Month 7~9 | +110~210 | 신호 받은 시군구만 발행 |
| 4 | Month 10~12 | +150~400 | 동 단위 + 약한 페이지 정리 |

**12개월 후 합계: 335~705개**. 이 범위가 시스템의 *현실적 용량*.

## 8. 4가지 위험

| # | 위험 | 시점 | 완화 |
|---|---|---|---|
| 1 | Month 2~3 양산 충동 | Phase 1 종반 | DB 트리거가 코드 레벨 차단 |
| 2 | 카니발리제이션 | 페이즈 2 이후 | UNIQUE 제약 + 분기 검색 점검 |
| 3 | 약한 페이지 누적 | 페이즈 3~4 | 분기 정리 루틴 |
| 4 | 테마 분산 | 임의 시점 | Theme.depth ≤ 2 + Phase 0 제한 |

## 9. 관련 룰

- 엔티티별 상세: [02-entities.md](02-entities.md)
- 페이지 도출 상세: [03-page-derivation.md](03-page-derivation.md)
- 콘텐츠 타입 카탈로그: [04-content-types.md](04-content-types.md)
- 결정 룰 Q&A: [05-decision-rules.md](05-decision-rules.md)

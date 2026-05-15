# 06. 용어 매핑

같은 개념의 *한국어 ↔ 영문 ↔ 코드 ↔ DB* 표기를 통합 정리.

## 1. 엔티티

| 한국어 | 영문 개념 | PHP 클래스 | DB 테이블 | 비고 |
|---|---|---|---|---|
| 테마 | Theme | `App\Entity\Theme` | `theme` | 카테고리 축 |
| 지역 | Region | `App\Entity\Region` | `region` | 지리적 축 |
| 작성자 | Author | `App\Entity\Author` | `author` | E-E-A-T 시그널 |
| 콘텐츠 노드 | ContentNode | `App\Entity\ContentNode` | `content_node` | 페이지의 추상 단위 |
| 데이터 포인트 | DataPoint | `App\Entity\DataPoint` | `data_point` | 페이지 내용물 |
| 리다이렉트 | Redirect | `App\Entity\Redirect` | `redirect` | 폐기 노드 무덤 |

## 2. 상태 (ContentStatus)

| 한국어 | 영문 | enum | DB 값 | 의미 |
|---|---|---|---|---|
| 초안 | Draft | `ContentStatus::Draft` | `'draft'` | 작업 중, 비공개 |
| 발행 | Live | `ContentStatus::Live` | `'live'` | 공개됨, 인덱스 대상 |
| 비색인 | Noindex | `ContentStatus::Noindex` | `'noindex'` | 게이트 미달 |
| 폐기 | Dead | `ContentStatus::Dead` | `'dead'` | 폐기, 자동 301 |

## 3. body_template (BodyTemplate enum)

| 한국어 | 영문 | enum case | DB 값 | 게이트 임계값 |
|---|---|---|---|---|
| 매트릭스 | Matrix | `BodyTemplate::Matrix` | `'matrix'` | data_count ≥ 5 |
| 가이드 | Guide | `BodyTemplate::Guide` | `'guide'` | body 3,000자+ AND FAQ 3+ |
| 에세이 | Essay | `BodyTemplate::Essay` | `'essay'` | body 3,000자+ |
| 데이터 리포트 | Report | `BodyTemplate::Report` | `'report'` | body 5,000자+ |
| 사례 연구 | CaseStudy | `BodyTemplate::CaseStudy` | `'case_study'` | body 1,500자+ |

`bodyTemplate IS NULL` 은 `Matrix` 의 기본값으로 해석됨 (deriveTemplate 의 fallback). 매트릭스 자식 변종(시도/시군구/동)은 enum 값에 박지 않고, 시각 차이는 `region.depth`와 데이터에서 자연스럽게 나옴.

## 4. DataPoint kind (DataPointKind enum)

| 한국어 라벨 | enum case | DB 값 | 의미 | 권장 schema |
|---|---|---|---|---|
| 수치 데이터 | `DataPointKind::Quantitative` | `'quantitative'` | 숫자·통계 | `{figure, label?}` |
| 정성 데이터 | `DataPointKind::Qualitative` | `'qualitative'` | 목록·분포 | `string[]` |
| 인접 비교 | `DataPointKind::Comparison` | `'comparison'` | 인접 지역 차이 | `{baseline?, body}` 또는 string |
| 사례·후기 | `DataPointKind::CaseStudy` ⚠ | `'case'` | 매칭 사례, 후기 | `{quote, attribution?}` 또는 string |
| 자주 묻는 질문 | `DataPointKind::Faq` | `'faq'` | 질문-답변 | string (답변; title이 질문) |

⚠ DB 값은 `'case'`이지만 PHP enum case 이름은 `CaseStudy` (PHP의 `case` 예약어 회피).

한국어 라벨은 `DataPointKind::label()`에서 반환. Twig는 `{{ datapoint_kind_label(kind) }}` 또는 enum 객체일 때 `{{ kind.label }}`.

권장 schema 상세: [02-entities.md §5.3](02-entities.md#53-kind별-권장-value-schema). 렌더 파셜: [_partials/datapoint/*](../../templates/public/_partials/datapoint/).

## 5. 페이지 유형

| 한국어 | 영문 | 좌표 조건 | 예시 URL |
|---|---|---|---|
| 루트 | Root | (ContentNode 아님, 별도 컨트롤러) | `/` |
| 테마 허브 | Theme Hub | region=NULL AND theme.parent=NULL | `/tutoring/` |
| 지역 페이지 | Region Page | region != NULL | `/tutoring/seoul/` |
| 자식 테마 페이지 | Child Theme Page | region=NULL AND theme.parent != NULL | `/guides/how-to-choose-tutor/` |

## 6. 트리거

| 한국어 | 트리거 이름 | 발동 시점 | 책임 |
|---|---|---|---|
| 발행 게이트 | `trg_publish_gate` (function: `fn_publish_gate`) | BEFORE INSERT/UPDATE ON content_node | 발행 자격 검증, 자동 강등 |
| 데이터 카운트 갱신 | `trg_refresh_data_count` (`fn_refresh_data_count`) | AFTER INSERT/UPDATE/DELETE ON data_point | 부모 노드의 data_count 재계산 |
| 부모 라이브 검증 | `trg_parent_live_check` (`fn_parent_live_check`) | BEFORE UPDATE ON content_node | 자식 live 시 부모도 live |
| 폐기 캐스케이드 | `trg_dead_cascade_redirect` (`fn_dead_cascade_redirect`) | AFTER UPDATE ON content_node | dead 시 Redirect 자동 등록 |
| 지역 깊이 검증 | `trg_region_depth_check` | BEFORE INSERT/UPDATE ON region | parent NULL인데 depth>0 거부 |
| 테마 깊이 검증 | `trg_theme_depth_check` | BEFORE INSERT/UPDATE ON theme | depth > 2 거부 |

## 7. 사이트맵 5개 뷰

| 한국어 | 영문 | 용도 | 어디서 |
|---|---|---|---|
| 발행 트리 | Public Tree | sitemap.xml 생성, 일상 모니터링 | `/sitemap.xml` (자동) |
| 데이터 매트릭스 | Data Matrix | 매일 작업 우선순위 결정 | `/admin/matrix` |
| KPI 히트맵 | KPI Heatmap | 분기 정리 결정 (살릴지/죽일지) | (Search Console + DB 조인 — 별도 뷰 필요) |
| 사일로 무결성 | Silo Integrity | 구조 점검, 위반 즉시 알림 | `/admin/silo` |
| 갱신 큐 | Refresh Queue | 일일 작업 목록 | `/admin/queue` |

## 8. 페이즈

| 한국어 | 영문 | 기간 | 페이지 발행 |
|---|---|---|---|
| 페이즈 0 | Phase 0 | Week 1~2 | 0개 (인프라만) |
| 페이즈 1 | Phase 1 | Month 1~3 | 25~35 (허브) |
| 페이즈 2 | Phase 2 | Month 4~6 | +51 (시도) |
| 페이즈 3 | Phase 3 | Month 7~9 | +110~210 (시군구) |
| 페이즈 4 | Phase 4 | Month 10~12 | +150~400 (동/조합) |

## 9. 자주 헷갈리는 용어

| 용어 A | 용어 B | 차이 |
|---|---|---|
| BodyTemplate.Matrix | 테마 허브 | 전자는 enum 값(매트릭스 패밀리), 후자는 페이지 종류(region=NULL). 테마 허브는 보통 Matrix 패밀리로 자동 결정되지만 명시 변경 가능. |
| BodyTemplate.CaseStudy | DataPointKind::CaseStudy | 전자는 페이지 타입(사례 연구 페이지), 후자는 데이터 종류(인용/사례 박스). 다른 개념. |
| status='noindex' | status='dead' | noindex = 일시 강등, 회복 가능. dead = 영구 폐기, Redirect 자동 등록. |
| 카니발리제이션 | 사일로 침범 | 전자는 키워드 충돌 (같은 검색어를 두 페이지가 노림). 후자는 구조 충돌 (다른 테마로 직접 링크). |
| `data_count` | `verified DataPoint 수` | 동일. data_count는 verified=true만 카운트하는 자동 컬럼. |
| body_markdown | body_html | 운영자가 입력하는 것은 markdown. 컨트롤러가 league/commonmark로 HTML로 변환해 `body_html`로 컨텍스트에 주입. |

## 10. 게이트 경로

| 게이트 경로 | 적용 body_template | 임계값 |
|---|---|---|
| matrix | matrix (또는 NULL) | data_count ≥ 5 |
| guide | guide | body 3,000자+ AND FAQ 3+ |
| essay | essay | body 3,000자+ |
| report | report | body 5,000자+ |
| case_study | case_study | body 1,500자+ |

공통: intro_text 비어있지 않음 + author.verified_at IS NOT NULL.

## 11. 외부 시스템 매핑

| 외부 개념 | 우리 시스템 |
|---|---|
| Google Search Console 노출 (impression) | `kpi_snapshot` JSONB 캐시 |
| Schema.org Article | `buildJsonLd()` 출력 |
| Schema.org BreadcrumbList | `buildBreadcrumbsJsonLd()` 출력 |
| Schema.org FAQPage | DataPoint(kind=faq)로 자동 생성 |
| Schema.org Person | Author 엔티티로 매핑 |
| 301 Redirect | Redirect 엔티티 + 자동 등록 트리거 |
| sitemap.xml | `WHERE status='live'` SELECT |
| canonical URL | UrlBuilder 함수의 단일 출력 (다른 표현 없음) |

## 12. 운영자 워크플로우 용어

| 한국어 | 영문 | 의미 |
|---|---|---|
| 일일 루틴 | Daily Routine | 매일 15~30분 작업 |
| 주간 루틴 | Weekly Routine | 주 1회 1시간 작업 |
| 분기 루틴 | Quarterly Routine | 분기 마지막 주 반나절 |
| 승격 | Promotion | 사다리 위로 (draft → live, noindex → live) — 운영자 결정 |
| 강등 | Demotion | 사다리 아래로 (live → noindex) — 대부분 자동 |
| 발행 게이트 통과 | Gate Pass | DB 트리거가 status='live' 허용 |
| 자동 강등 | Auto-demote | data_count<5 등으로 인한 자동 noindex 전이 |
| 정리 (Cleanup) | Cleanup | 약한 페이지 통합·삭제 (분기 루틴) |
| 재매칭 | Rematch | (운영 도메인 — 매칭 서비스 용어, 시스템과 무관) |

## 13. 비-목록 (이 시스템과 무관)

다음 개념들은 *이 시스템에서 다루지 않음*. 혼동 주의:

- 백링크 자동 모니터링 (외부)
- 다국어 (i18n)
- A/B 테스트 인프라
- 광고/유료 트래픽
- 경쟁사 분석 자동화

이 영역에 대한 질문이 들어오면: *이 시스템은 다루지 않는다*고 명시.

## 14. 관련 룰

- 시스템 전제: [01-premise.md](01-premise.md)
- 결정 룰 Q&A: [05-decision-rules.md](05-decision-rules.md)
- 다른 문서들의 색인: [00-README.md](00-README.md)

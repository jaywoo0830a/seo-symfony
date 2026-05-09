# 05. 결정 룰 Q&A

LLM이 받을 흔한 질문에 대한 *직접적 답변 룰*. 자연어 설명보다 *조건 → 결정*의 형태로.

## 1. 페이지 만들기

### Q: 새 페이지를 만들고 싶다.

```
필수 선행 조건 3개 (모두 만족해야):
  ✓ 그 좌표에 verified DataPoint 5개 채울 수 있는가? (매트릭스의 경우)
  ✓ 또는 body_markdown 임계값 만족하는 본문 쓸 수 있는가? (prose의 경우)
  ✓ 부모 좌표가 이미 live 상태인가?

대시보드:
  /admin/themes/new (새 테마 필요한 경우)
  /admin/nodes/new (새 ContentNode)
```

### Q: 새 가이드를 만들고 싶다.

```
1. /admin/themes/new → parent='guides' 선택, slug 입력
2. /admin/nodes/new → theme=새_자식_테마, region=비움
3. body_template = Guide 명시
4. body_markdown 작성 (3,000자+)
5. /admin/nodes/{id}/datapoints/new → kind=faq 3개+ 추가하고 verify
6. status=live 변경 → 트리거가 게이트 검증
```

### Q: 새 데이터 리포트를 만들고 싶다.

```
1. /admin/themes/new → parent='reports' (없으면 먼저 reports 루트 테마 만들기)
2. /admin/nodes/new → 자식 테마 선택, region=비움, body_template=Report
3. body_markdown 5,000자+ 작성 (분석 + 데이터 + 방법론)
4. quantitative DataPoint 추가 (핵심 수치)
5. comparison DataPoint 추가 (방법론 노트)
6. status=live
```

### Q: 새 사례 연구를 만들고 싶다.

```
1. /admin/themes/new → parent='cases'
2. /admin/nodes/new → 자식 테마, region=비움, body_template=CaseStudy
3. body_markdown 1,500자+ (서사형)
4. quantitative DataPoint 2개+ (Before/After 수치)
5. qualitative DataPoint 1개 (학생 프로필 — kind는 qualitative)
6. case (CaseStudy) DataPoint 1~2개 (인용)
7. status=live
```

## 2. 페이지 폐기/통합

### Q: 트래픽 0인 페이지 90일 됐다.

```
3가지 옵션 (선택):
  A. 데이터 보강 → DataPoint 2~3건 추가 + last_review_at 갱신
  B. 통합 → 인접 페이지에 데이터 머지 → 이 페이지 status=dead → 자동 301
  C. 삭제 → status=dead → 시스템이 부모 찾아 자동 301

방치는 옵션 아님. 약한 페이지가 30% 넘으면 사이트 전체 약화.
```

### Q: 두 페이지가 같은 키워드 노린다 (카니발리제이션).

```
1. /admin/search에서 후보 페어 식별
2. 트래픽 많은 쪽 (강한 쪽) 결정
3. 약한 쪽의 데이터를 강한 쪽에 머지
4. 약한 쪽 status=dead → 자동 301
```

### Q: 페이지가 자동 강등됐다 (live → noindex).

```
원인 파악:
  - data_count가 5 미만으로 떨어짐? → DataPoint 검증 해제됐는지 확인
  - 부모가 noindex됐나? → fn_parent_live_check
  - body_markdown 길이가 임계값 미만으로 줄었나? (prose의 경우)

복구:
  1. 원인 해결 (DataPoint 추가/검증, body 보강 등)
  2. status를 수동으로 다시 'live'로 변경
  3. 트리거가 게이트 재검증 → 통과하면 회복
```

## 3. 시스템 확장

### Q: 새 BodyTemplate 변종을 추가해야 하나?

```
정당성 셀프 체크 (최소 1개 참이어야):
  ✓ 발행 게이트 임계값이 기존 변종과 다른가?
  ✓ 시각 시그니처가 기존 템플릿과 명확히 다른가?
  ✓ 사용처(테마)가 기존 변종으로 표현 불가능한가?

모두 거짓이면: 기존 변종 사용. 추가 안 함.
```

### Q: 새 BodyTemplate 변종을 추가하려고 한다. 7단계는?

```
1. src/Entity/Enum/BodyTemplate.php — case 추가 + isXxx() 메서드
2. 마이그레이션 — fn_publish_gate의 CASE 분기 추가
   (순서: 함수 먼저 교체 → row 업데이트 나중)
3. templates/public/node.html.twig — {% elseif is_xxx %} 분기
4. templates/public/_xxx.html.twig — 신규 템플릿
5. assets/scss/brand/_xxx.scss + app.scss에 @use
6. src/Form/ContentNodeType.php — choice_label match에 추가
7. (선택) src/Command/SeedDemoCommand.php — 시드 데모
```

### Q: 새 테마를 추가하려 한다.

```
사전 질문 3개:
  - 자체 데이터를 쌓을 수 있는 영역인가?
  - 기존 테마와 키워드 분리되는가?
  - 최소 5개 노드를 발행할 수 있는가?

모두 ✓면:
  대시보드 /admin/themes/new
  parent 선택 (루트면 비움, 자식이면 부모 테마)
  depth는 자동 계산

매트릭스 region 페이지 안 만들 거면:
  src/Command/SeedDemoCommand.php의 MATRIX_EXCLUDED_SLUGS에 slug 추가
```

### Q: 어디를 만지면 어디가 바뀌나?

| 변경 의도 | 편집 대상 | 영향 범위 |
|---|---|---|
| 한 페이지만 다르게 | hubs/{path}.html.twig | 1개 URL |
| 한 테마의 모든 지역 페이지 | matrix/{slug}.html.twig | 한 테마의 N개 |
| 모든 매트릭스 페이지 | _matrix.html.twig 또는 _partials/* | 전체 매트릭스 |
| 한 콘텐츠 타입 (가이드 전체) | _guide.html.twig | 모든 가이드 |
| 새 콘텐츠 타입 추가 | 위 7단계 | 신규 |
| 발행 요건 변경 | 새 마이그레이션으로 fn_publish_gate | 전체 |
| 분기 로직만 | node.html.twig (거의 안 건드림) | dispatcher |

## 4. 게이트 통과 못 할 때

### Q: 발행 게이트가 자꾸 막는다.

```
에러 메시지가 정확한 작업 항목:
  "data_count >= 5 (have N)" → DataPoint N→5 채우기 + verify
  "non-empty intro_text" → intro_text 작성
  "verified author required" → 작성자 검증 (AuthorVerifyController)
  "parent live check fail" → 부모 노드 먼저 live로
  "body_markdown visible length >= N (have M)" → 본문 보강
  "verified FAQ data points >= 3" → FAQ DataPoint 추가 + verify

게이트는 우회 안 됨. 이유 해결만.
```

### Q: 시드(`app:seed:demo`)가 게이트에 막힌다.

```
개발 단계에서는:
  php bin/console doctrine:database:drop --force
  php bin/console doctrine:database:create
  php bin/console doctrine:migrations:migrate --no-interaction
  php bin/console doctrine:fixtures:load --no-interaction
  php bin/console app:seed:demo

이 5초 워크플로우가 가장 빠른 복구.

운영 단계에서는:
  - 시드 데이터의 본문 길이 확인
  - DataPoint 수 확인
  - 시드 코드의 게이트 요건 확인
```

## 5. 캐시/렌더링

### Q: 페이지 변경했는데 안 보인다.

```
1. SCSS 변경한 경우:
   npm run build
   rm -rf public/assets
   php bin/console cache:clear --no-warmup

2. HTML 캐시 무효화:
   SQL: UPDATE content_node SET last_review_at = NOW() WHERE status='live';

3. 브라우저 강제 새로고침: Ctrl+F5 / Cmd+Shift+R

원인:
  - HTTP Last-Modified 헤더가 last_review_at 기반
  - 데이터 변경하지 않으면 304 Not Modified 응답
  - 그래서 템플릿/SCSS만 바꾸면 last_review_at 갱신 필요
```

### Q: 어떤 페이지가 어떤 템플릿으로 렌더되는가?

```
디스패처 우선순위 (위에서 아래로):
  1. hubs/{theme-path}.html.twig 파일 있고 region IS NULL? → 사용
  2. body_template == guide_*? → _guide.html.twig
  3. body_template == essay? → _essay.html.twig
  4. body_template == report? → _report.html.twig
  5. body_template == case_study? → _case_study.html.twig
  6. (그 외) → matrix/{root-theme.slug}.html.twig 또는 _matrix.html.twig
```

## 6. 운영 결정

### Q: 매일 무엇을 해야 하나?

```
일일 루틴 (15~30분):
  1. /admin/queue 갱신 큐 → 우선순위 1, 2 처리
  2. /admin/matrix 매트릭스 → △ 칸 1~2개 채우기
  3. 어제 추가된 DataPoint verify

자세히: docs/../OPERATIONS.md §1
```

### Q: 분기 정리는 무엇을 하나?

```
분기 마지막 주, 반나절:
  1. KPI 히트맵 검토 → 트래픽 0 페이지 식별
  2. 약한 페이지 정리 (보강/통합/삭제)
  3. 카니발리제이션 점검 (/admin/search)
  4. 다음 분기 발행 캘린더 락
  5. 자체 데이터 리포트 1편 발행

자세히: docs/../OPERATIONS.md §3
```

### Q: 페이즈 전환 시점은 어떻게 판단하나?

```
페이즈 → 다음 페이즈 게이트 (KPI 신호):
  Phase 1 → 2: Search Console 일 노출 100+
  Phase 2 → 3: 시도 페이지 일부가 50위 이내
  Phase 3 → 4: 시군구 일부가 10위 이내
  Phase 4 종료: 자연 검색 일 방문자 800+

미달 시: 다음 페이즈 안 가고 현재 페이즈 보강.
```

## 7. 위험 신호 대응

### Q: Month 2~3에 트래픽이 0이다.

```
이건 정상. 충동에 굴복하지 말 것.

해야 할 것:
  ✓ 노출(impression) 증가 추세 확인 (클릭 X, 노출 O)
  ✓ Phase 1 콘텐츠 보강 (본문 깊이 늘림)
  ✓ Search Console 신호 모니터링

하지 말 것:
  ✗ AI로 페이지 양산
  ✗ 발행 게이트 끄기 (코드 레벨로 차단됨, 시도하지 말 것)
  ✗ 다른 SEO 컨설턴트 의견 들어보기 (모두 같은 답)

이 시기를 견디는 것이 12개월 계획 성공의 단일 결정 변수.
```

### Q: 약한 페이지(트래픽 0)가 30% 넘었다.

```
즉시 발행 동결 + 정리 모드:
  1. 신규 발행 중단
  2. 약한 페이지 일괄 검토
  3. 카테고리별로:
     - 데이터 보강 가능 → 보강 (DataPoint 추가)
     - 인접 페이지로 통합 가능 → 통합 (status=dead)
     - 둘 다 안 됨 → 폐기 (status=dead)
  4. 30% 미만 회복까지 정리만

방치하면 도메인 권위 회복 6~12개월 지연.
```

### Q: 카니발리제이션 의심된다.

```
1. /admin/search에서 키워드 검색 → 후보 페어 식별
2. Search Console에서 같은 쿼리에 두 페이지 노출 확인
3. 결정:
   - 좌표가 다른가? (예: 시도 vs 시군구) → 데이터 보강으로 차별화
   - 좌표가 같은가? → UNIQUE 위반이라 발생 불가 (시스템 보장)
   - 자식 테마끼리 충돌? → 한쪽 폐기 또는 머지
```

## 8. 데이터 모델 변경

### Q: 새 엔티티를 추가해야 하나?

```
기존 5개 (Theme, Region, Author, ContentNode, DataPoint)는 최소 충분 집합.

추가 정당성 셀프 체크:
  ✓ 기존 5개로 표현 불가능한가?
  ✓ DataPoint의 한 kind로 흡수 안 되는가?
  ✓ 좌표축이거나 내용물이 아닌 새 *개념적 차원*인가?

세 조건 모두 ✓ 아니면: 기존 모델 확장으로 해결. 새 엔티티 X.

예: "학년"은 새 엔티티가 아니라 DataPoint 또는 자식 테마로 표현.
```

### Q: 발행 게이트 룰을 변경해야 하나?

```
1. 새 마이그레이션 생성 (fn_publish_gate를 CREATE OR REPLACE)
2. 마이그레이션 순서 주의: 함수 먼저 교체, row 업데이트 나중
3. 기존 live 노드의 강등 위험 시뮬레이션
4. down() 마이그레이션 작성 (개발 단계엔 비워도 OK)
5. 시드(app:seed:demo)가 새 게이트 통과하는지 검증
```

## 9. 자주 헷갈리는 것들

### Q: ContentNode와 페이지의 관계는?

```
1 ContentNode 행 = 1 페이지 = 1 URL

다른 엔티티는 페이지가 아님:
  - Theme = 좌표축 (트리)
  - Region = 좌표축 (트리)
  - Author = 작성자 (예외적으로 /about/authors/<slug>/ 1 페이지)
  - DataPoint = 페이지 안 섹션의 내용물
```

### Q: BodyTemplate.Hub와 테마 허브의 관계는?

```
혼동 주의:
  - "테마 허브" = 페이지 종류 (region IS NULL AND theme.parent IS NULL)
  - "BodyTemplate.Hub" = body_template enum 값 중 하나

테마 허브는 BodyTemplate.Hub로 자동 결정되지만,
body_template은 명시 가능 (예: 가이드 허브 같은 자식 테마는 다른 변종 사용 가능).
```

### Q: status='noindex'와 'dead'의 차이는?

```
noindex:
  - 일시적 또는 자동 강등 상태
  - 데이터 보강하면 다시 live 가능
  - 페이지 자체는 아직 존재 (404 응답)
  - sitemap.xml에는 안 나타남

dead:
  - 운영자 명시적 폐기 결정
  - Redirect 행 자동 생성 (가장 가까운 live 부모로 301)
  - status는 다시 live로 복구 안 함 (운영자가 새 노드 만들어야)
```

## 10. 관련 룰

- 시스템 전제: [01-premise.md](01-premise.md)
- 엔티티 상세: [02-entities.md](02-entities.md)
- 페이지 도출: [03-page-derivation.md](03-page-derivation.md)
- 콘텐츠 타입: [04-content-types.md](04-content-types.md)
- 용어 매핑: [06-vocabulary.md](06-vocabulary.md)
- 운영자 워크플로우: [OPERATIONS.md](../../OPERATIONS.md)
- 입양/확장: [docs/contribution/](../contribution/)

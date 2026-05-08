# 04. 콘텐츠 타입 카탈로그

10개 BodyTemplate 변종의 *완전한 룰*. 각 변종에 대해: 자동 결정 여부, 발행 게이트, 템플릿 파일, 의도된 사용처.

## 0. 카탈로그 한눈에

| 변종 | 자동/수동 | 발행 게이트 | 템플릿 파일 | 톤 |
|---|---|---|---|---|
| `Hub` | 자동 (region=NULL/0) | data_count ≥ 5 | matrix/{theme}.html.twig | 매트릭스 |
| `Sido` | 자동 (region.depth=1) | data_count ≥ 5 | matrix/{theme}.html.twig | 매트릭스 |
| `Sigungu` | 자동 (region.depth=2) | data_count ≥ 5 | matrix/{theme}.html.twig | 매트릭스 |
| `Dong` | 자동 (region.depth=3) | data_count ≥ 5 | matrix/{theme}.html.twig | 매트릭스 |
| `GuideLongform` | **수동** | body 3,000자+ AND verified FAQ ≥ 3 | _guide.html.twig | how-to 심층 |
| `GuideComparison` | **수동** | 위와 동일 | _guide.html.twig | how-to 비교 |
| `GuideFaq` | **수동** | 위와 동일 | _guide.html.twig | how-to FAQ 중심 |
| `Essay` | **수동** | body 3,000자+ | _essay.html.twig | 에디토리얼 |
| `Report` | **수동** | body 5,000자+ | _report.html.twig | 학술 |
| `CaseStudy` | **수동** | body 1,500자+ | _case_study.html.twig | 증거형 |

**공통 게이트** (모든 변종): `intro_text` 비어있지 않음 + `author.verified_at IS NOT NULL`.

## 1. 매트릭스 변종 (Hub/Sido/Sigungu/Dong)

### 1.1 자동 결정 룰

```
region IS NULL OR depth=0  → Hub
region.depth = 1           → Sido
region.depth = 2           → Sigungu
region.depth = 3           → Dong
```

### 1.2 의도된 사용처

| 변종 | URL 예 | 키워드 의도 |
|---|---|---|
| Hub | `/tutoring/` | 광역 ("과외 추천") |
| Sido | `/tutoring/seoul/` | 시도 광역 ("서울 과외") |
| Sigungu | `/tutoring/seoul/gangnam-gu/` | 시군구 ("강남 과외") |
| Dong | `/tutoring/seoul/gangnam-gu/daechi-dong/` | 동 단위 ("대치동 과외") |

### 1.3 발행 게이트

| 게이트 | 강제 |
|---|---|
| `data_count >= 5` (verified DataPoint) | DB 트리거 `fn_publish_gate` |
| `intro_text` 비어있지 않음 | DB 트리거 |
| `author.verified_at IS NOT NULL` | DB 트리거 |
| 부모 노드도 `live` (region 있을 때) | DB 트리거 `fn_parent_live_check` |

### 1.4 자동 강등

`live` 상태에서 `data_count`가 5 미만으로 떨어지면 *자동으로 `noindex`로 강등*. 예외 안 던짐.

### 1.5 렌더링

매트릭스 셸은 `templates/public/matrix/{root-theme.slug}.html.twig` 우선, 없으면 `_matrix.html.twig` fallback.

## 2. 가이드 변종 (Guide*)

### 2.1 3개 변종

| 변종 | 의도 | 시각 시그니처 |
|---|---|---|
| `GuideLongform` | 심층 how-to | 본문 → callouts → FAQ → CTA |
| `GuideComparison` | 비교 분석 | callouts(비교) → 본문 → FAQ → CTA |
| `GuideFaq` | FAQ 중심 | FAQ(첫 항목 펼침) → 본문 → callouts → CTA |

### 2.2 공통 발행 게이트

| 게이트 | 임계값 |
|---|---|
| `body_markdown` 가시 길이 | ≥ 3,000자 |
| verified FAQ DataPoint 수 | ≥ 3개 |
| `intro_text` | 비어있지 않음 |
| `author.verified_at` | NOT NULL |

### 2.3 왜 FAQ가 필수인가

가이드는 *how-to* 콘텐츠. FAQ가 없으면 "이 글이 답해주는 질문"이 불명확 → SEO 가치 약화.

### 2.4 렌더링

[_guide.html.twig](../../templates/public/_guide.html.twig)가 내부적으로 3변종 분기.

### 2.5 사용처

- 정보형 가이드 ("과외 강사 선택법")
- 비교 분석 ("과외 vs 학원")
- 모음 페이지 ("자주 묻는 질문")

테마: `guides/` 아래 자식 테마로.

## 3. Essay (에세이)

### 3.1 의도

에디토리얼 / 칼럼 / 오피니언. *작가의 목소리*가 핵심 가치.

### 3.2 발행 게이트

| 게이트 | 임계값 |
|---|---|
| `body_markdown` 가시 길이 | ≥ 3,000자 |
| `intro_text` | 비어있지 않음 |
| `author.verified_at` | NOT NULL |
| **FAQ 요건 없음** | (가이드와의 차이) |

### 3.3 렌더링 시그니처

[_essay.html.twig](../../templates/public/_essay.html.twig):
- 카테고리 칩 ("에세이")
- 큰 serif 타이틀
- 이탤릭 lede
- 작성자 byline (label/이름/credentials)
- **drop cap** (첫 문단 첫 글자 4.5em)
- 본문 prose
- pull-quote 박스 (comparison DataPoint)
- 형제 에세이 링크

### 3.4 변종 없음

이전엔 `ArticleEssay`/`ArticleColumn` 두 변종이었으나 *통일됨*. 단일 `Essay` 타입. 각 에세이는 독립된 글.

### 3.5 사용처

- `contents/` 아래 자식 테마 (예: `study-routine-design`)
- 분기 1~2편 권장

## 4. Report (데이터 리포트)

### 4.1 의도

권위 콘텐츠. 자체 데이터 발행 → 외부 사이트 인용 자석.

### 4.2 발행 게이트

| 게이트 | 임계값 |
|---|---|
| `body_markdown` 가시 길이 | ≥ 5,000자 (가장 깊음) |
| `intro_text` | 비어있지 않음 (요약 박스) |
| `author.verified_at` | NOT NULL |

### 4.3 렌더링 시그니처

[_report.html.twig](../../templates/public/_report.html.twig):
- 더블 라인 헤더
- 시리즈/번호 인디케이터
- 발행/검토일 크레딧
- **Executive Summary 박스** (intro_text 활용)
- **핵심 수치 박스** (quantitative DataPoint 4개)
- 학술 톤 본문 (drop cap 없음, 표 지원)
- 방법론 노트 (comparison DataPoint)
- **인용 안내 박스** (citation suggestion — 권위 시그니처)
- 시리즈 다른 보고서 링크

### 4.4 사용처

- `reports/` 아래 자식 테마
- **분기 1편 권장** (연 4편)
- 권위 빌딩의 가장 강력한 단일 자산

## 5. CaseStudy (사례 연구)

### 5.1 의도

E-E-A-T의 *Experience* 직격. 익명화된 실제 사례.

### 5.2 발행 게이트

| 게이트 | 임계값 |
|---|---|
| `body_markdown` 가시 길이 | ≥ 1,500자 (가장 짧음) |
| `intro_text` | 비어있지 않음 |
| `author.verified_at` | NOT NULL |

### 5.3 렌더링 시그니처

[_case_study.html.twig](../../templates/public/_case_study.html.twig):
- 오렌지 핫 라벨 ("사례 연구")
- 가운데 정렬 큰 타이틀
- **Before → After 그라디언트 박스** (quantitative 첫 2개)
- **학생 프로필 박스** (qualitative)
- 본문
- **인용 박스** (case kind DataPoint)
- 작성자 byline + 검토일

### 5.4 사용처

- `cases/` 아래 자식 테마
- **매월 1편 권장** (연 12편)
- 학부모/학생 동의 받은 실제 사례만

## 6. body_template 결정 흐름

운영자가 새 ContentNode 만들 때:

```
1. 노드의 좌표 결정 (theme, region)
2. 콘텐츠 타입 결정:
   - 매트릭스 페이지? → bodyTemplate 비워둠 (자동 결정)
   - prose 콘텐츠? → bodyTemplate 명시 (Essay/Report/CaseStudy/Guide*)
3. body_markdown 입력 (prose만)
4. DataPoint 입력 (해당 타입의 게이트 만족하도록)
5. status='live' 시도 → 트리거가 게이트 검증
```

## 7. 변종별 게이트 비교 표

| 변종 | data_count | body_markdown 길이 | FAQ 수 | intro_text | author |
|---|---|---|---|---|---|
| Hub/Sido/Sigungu/Dong | ≥ 5 | — | — | 필수 | verified |
| GuideLongform | — | ≥ 3,000 | ≥ 3 | 필수 | verified |
| GuideComparison | — | ≥ 3,000 | ≥ 3 | 필수 | verified |
| GuideFaq | — | ≥ 3,000 | ≥ 3 | 필수 | verified |
| Essay | — | ≥ 3,000 | — | 필수 | verified |
| Report | — | ≥ 5,000 | — | 필수 | verified |
| CaseStudy | — | ≥ 1,500 | — | 필수 | verified |

**공통**: 부모가 live (region 있을 때).

## 8. 변종 선택 결정 룰

| 콘텐츠 의도 | 선택할 변종 |
|---|---|
| 지역 페이지 (자동) | (비워둠 → 자동) |
| how-to 가이드 (단일 글, 깊음) | GuideLongform |
| 두 옵션 비교 가이드 | GuideComparison |
| 질문 모음 가이드 | GuideFaq |
| 작가의 목소리 (오피니언/에세이) | Essay |
| 자체 데이터 분기 보고서 | Report |
| 익명 사례 연구 | CaseStudy |

## 9. 셀프 검증 체크리스트

새 콘텐츠 타입을 추가하려는 경우, 다음 중 *최소 하나*가 참이어야 정당함:

- [ ] 발행 게이트 임계값이 *기존 변종과 다름* (예: 더 짧거나 다른 데이터 요구)
- [ ] 시각 시그니처가 *기존 템플릿과 명확히 다름* (drop cap vs 인용 박스 vs 표 등)
- [ ] 사용처(테마)가 *기존 변종으로는 표현 불가능*

세 조건 모두 *거짓*이면: 기존 변종을 사용하면 됨. 새 변종 추가는 *시스템 복잡도 증가*이므로 정당화 필요.

## 10. 관련 룰

- 페이지 도출 룰: [03-page-derivation.md](03-page-derivation.md)
- 결정 룰 Q&A: [05-decision-rules.md](05-decision-rules.md)
- 콘텐츠 타입 *왜*: [docs/dev/04-page-system-architecture.md](../dev/04-page-system-architecture.md)

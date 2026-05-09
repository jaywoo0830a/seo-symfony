# 03. 페이지 도출 룰

데이터(5개 엔티티)에서 페이지가 *어떻게 도출되는지*의 완전한 룰. 디스패처 우선순위, 오버라이드 조건, URL 함수.

## 1. 페이지 4가지 유형 식별

URL이 들어오면 다음 룰로 유형 판단:

| 유형 | 좌표 조건 | 컨트롤러 |
|---|---|---|
| 루트 | URL = `/` | `PublicHomeController` |
| 테마 허브 | `region IS NULL AND theme.parent IS NULL` | `PublicNodeController` |
| 지역 페이지 | `region IS NOT NULL` | `PublicNodeController` |
| 자식 테마 페이지 | `region IS NULL AND theme.parent IS NOT NULL` | `PublicNodeController` |

루트(`/`)는 별도 컨트롤러. 나머지 모든 비-루트 URL은 `PublicNodeController`가 처리.

## 2. URL → ContentNode 해석 (PathResolver)

### 2.1 알고리즘

```
1. URL 경로 segments 분리
2. segments[0] → 루트 테마 lookup (parent IS NULL)
3. 그리디 테마 descent: 다음 segment가 자식 테마 slug면 따라감
   - 자식 테마 발견 못 하면 멈춤
4. 남은 segments → region 트리 walk (시도부터 시작)
5. 매칭되는 ContentNode lookup: findByCoordinates(theme, region)
```

### 2.2 예시

| URL | 테마 walk | Region walk | 결과 좌표 |
|---|---|---|---|
| `/tutoring/` | tutoring | (없음) | (tutoring, NULL) |
| `/tutoring/seoul/` | tutoring | seoul | (tutoring, seoul) |
| `/tutoring/seoul/gangnam-gu/` | tutoring | seoul → gangnam-gu | (tutoring, gangnam-gu) |
| `/guides/how-to-choose-tutor/` | guides → how-to-choose-tutor | (없음) | (how-to-choose-tutor, NULL) |
| `/reports/2026q1-tutoring-rates/` | reports → 2026q1-tutoring-rates | (없음) | (2026q1-..., NULL) |

### 2.3 코드

[src/Service/PathResolver.php](../../src/Service/PathResolver.php)

## 3. ContentNode → URL 도출 (UrlBuilder)

### 3.1 함수 정의

```
url(node) = "/" + theme_path(node.theme) + "/" + region_path(node.region) + "/"

theme_path(t)  = t의 루트부터 t까지 slug를 "/"로 연결
region_path(r) = r의 시도부터 r까지 slug를 "/"로 연결 (전국 노드는 빈 문자열)
```

### 3.2 예시

| theme.slug 경로 | region.slug 경로 | 생성 URL |
|---|---|---|
| `tutoring` | (NULL) | `/tutoring/` |
| `tutoring` | `seoul` | `/tutoring/seoul/` |
| `tutoring` | `seoul/gangnam-gu` | `/tutoring/seoul/gangnam-gu/` |
| `guides/how-to-choose-tutor` | (NULL) | `/guides/how-to-choose-tutor/` |

### 3.3 룰

- URL은 *함수의 출력*. 손으로 만들지 않음.
- 같은 (theme, region)에 두 URL 불가 (UNIQUE 제약)
- 다른 형식 URL이 같은 ContentNode를 가리킬 수 없음 (canonical 자동)

### 3.4 코드

[src/Service/UrlBuilder.php](../../src/Service/UrlBuilder.php)

## 4. body_template 결정 룰

### 4.1 우선순위

```
1. node.bodyTemplate 명시되어 있음? → 그것 사용
2. 명시 안 됨 → Matrix (기본값)
```

지역 깊이(시도/시군구/동)는 *enum 값에 박지 않음* — 같은 `Matrix`로 렌더되고 시각 차이는 데이터(region.depth)에서 자연스럽게 나옴.

### 4.2 룰 표

| 조건 | 결과 |
|---|---|
| `node.bodyTemplate` 명시 | 그 값 (운영자 의도 우선) |
| 명시 안 됨 | `Matrix` |

### 4.3 prose 변종은 명시 필수

`Guide`, `Essay`, `Report`, `CaseStudy`는 자동 결정 *안 됨*. 운영자가 명시해야 함. 명시하지 않으면 `Matrix`로 떨어짐.

### 4.4 코드

[src/Controller/Public/PublicNodeController.php](../../src/Controller/Public/PublicNodeController.php) `deriveTemplate()`

## 5. 디스패처 우선순위 (5단계)

[node.html.twig](../../templates/public/node.html.twig)는 다음 우선순위로 렌더 템플릿 결정:

```
1. template_override 파일 있음?
   → templates/public/hubs/{theme-path}.html.twig

2. template.isGuide()?
   → templates/public/_guide.html.twig

3. template.isEssay()?
   → templates/public/_essay.html.twig

4. template.isReport()?
   → templates/public/_report.html.twig

5. template.isCaseStudy()?
   → templates/public/_case_study.html.twig

6. (그 외)
   → matrix_template
   = templates/public/matrix/{root-theme.slug}.html.twig (있으면)
   = templates/public/_matrix.html.twig (제네릭 fallback)
```

매칭되는 첫 단계에서 멈춤. 위가 아래를 이김.

## 6. 오버라이드 룰

### 6.1 페이지 단위 오버라이드 (1순위)

**경로 패턴**: `templates/public/hubs/{theme-path}.html.twig`

**제약**: `region IS NULL`인 ContentNode만 가능. 지역 페이지는 절대 불가.

**예시**:
| URL | 오버라이드 파일 |
|---|---|
| `/tutoring/` | `hubs/tutoring.html.twig` |
| `/guides/` | `hubs/guides.html.twig` |
| `/guides/how-to-choose-tutor/` | `hubs/guides/how-to-choose-tutor.html.twig` |
| `/tutoring/seoul/` | (오버라이드 불가 — region 있음) |

**왜 지역 페이지는 불가**: 균일성이 SEO 자산. 강남구만 다르게 만들면 카니발 위험 + Google이 프로그래매틱으로 인식.

### 6.2 테마별 매트릭스 셸 (5순위)

**경로 패턴**: `templates/public/matrix/{root-theme.slug}.html.twig`

**적용 범위**: 같은 *루트 테마*의 모든 매트릭스 페이지 (오버라이드 없는 것).

**예시**:
| 파일 | 적용 |
|---|---|
| `matrix/tutoring.html.twig` | `/tutoring/`, `/tutoring/seoul/`, `/tutoring/*/*` |
| `matrix/academy.html.twig` | `/academy/*` 모두 |
| `matrix/contents.html.twig` | `/contents/*` 모두 |
| (없음) | `_matrix.html.twig`로 fallback |

**원칙**:
- 같은 테마, 다른 지역 = 같은 셸, 다른 데이터 (균일성 ↔ SEO)
- 다른 테마, 같은 지역 = 다른 셸 (차별성 ↔ near-duplicate 방지)

### 6.3 prose 템플릿 (2~5순위)

| body_template | 템플릿 파일 |
|---|---|
| `guide` | `_guide.html.twig` |
| `essay` | `_essay.html.twig` |
| `report` | `_report.html.twig` |
| `case_study` | `_case_study.html.twig` |

이 매핑은 *고정*. 변경 불가 (코드 수정 필요).

## 7. 컨텍스트 변수

모든 템플릿이 받는 변수 (PublicNodeController::buildContext):

| 변수 | 타입 | 설명 |
|---|---|---|
| `node` | ContentNode | 현재 노드 |
| `url` | string | 정규 URL |
| `h1` | string | 페이지 제목 |
| `template` | BodyTemplate | 결정된 템플릿 enum |
| `body_html` | string\|null | Markdown→HTML (prose만) |
| `template_override` | string\|null | 매칭된 오버라이드 파일 경로 |
| `matrix_template` | string | 매트릭스 셸 경로 |
| `byKind` | array | DataPoint 종류별 그룹 (verified만) |
| `parent` | ContentNode | 지역 트리 부모 |
| `siblings` | list | 같은 region.parent 자식들 |
| `children` | list | region.parent=현재 노드인 자식들 |
| `theme_children` | list | 테마 자식들의 노드 |
| `theme_siblings` | list | 테마 형제들의 노드 |
| `urls` | UrlBuilder | URL 생성 헬퍼 |
| `crumbs` | list | 빵부스러기 |
| `json_ld` | string | Article JSON-LD |
| `breadcrumbs_jsonld` | string | BreadcrumbList JSON-LD |

오버라이드 파일도 이 컨텍스트를 그대로 받음.

## 8. sitemap.xml 생성

### 8.1 SQL

```sql
SELECT loc, last_review_at, priority, changefreq
FROM content_node
WHERE status = 'live'
ORDER BY depth, theme_id, region_id;
```

### 8.2 자동화

- cron 매일 03:00 재생성
- Search Console ping 자동
- `last_review_at` 변경 = HTTP cache 무효화 트리거

### 8.3 코드

[src/Controller/Public/SitemapController.php](../../src/Controller/Public/SitemapController.php)

## 9. JSON-LD 자동 생성

각 페이지 렌더 시 자동 생성:

| Schema | 데이터 출처 |
|---|---|
| Article | author + theme + region + first_published_at + last_review_at |
| BreadcrumbList | crumbs (빵부스러기) |
| FAQPage | DataPoint(kind=faq) |
| LocalBusiness (지역 페이지) | region + 매트릭스 데이터 |

코드: [PublicNodeController::buildJsonLd()](../../src/Controller/Public/PublicNodeController.php)

## 10. 페이지 캐시 전략

### 10.1 헤더

```
Cache-Control: public, max-age=300, s-maxage=1800
Last-Modified: {node.last_review_at or first_published_at}
```

- 브라우저 5분
- CDN/공유 30분
- `If-Modified-Since`로 304 가능

### 10.2 무효화 트리거

`last_review_at` 변경 = 캐시 무효화. 운영자가 데이터 편집 후 명시적으로 갱신해야 변경 반영.

```sql
UPDATE content_node SET last_review_at = NOW() WHERE status='live';
```

## 11. 새 콘텐츠 타입 추가 시 어디를 만지나 (7단계)

| # | 위치 | 변경 |
|---|---|---|
| 1 | `BodyTemplate` enum | case 추가 + isXxx() 메서드 |
| 2 | 마이그레이션 | `fn_publish_gate`의 CASE 분기 |
| 3 | `node.html.twig` | `{% elseif is_xxx %}` 분기 |
| 4 | 템플릿 파일 | `_xxx.html.twig` 신규 |
| 5 | SCSS | `_xxx.scss` + `app.scss`에 `@use` |
| 6 | 폼 라벨 | `ContentNodeType.php`의 choice_label |
| 7 | 시드 (선택) | `SeedDemoCommand.php`에 데모 |

## 12. 관련 룰

- 콘텐츠 타입 카탈로그: [04-content-types.md](04-content-types.md)
- 결정 룰 Q&A: [05-decision-rules.md](05-decision-rules.md)
- 페이지 시스템 *왜*: [docs/dev/04-page-system-architecture.md](../dev/04-page-system-architecture.md)

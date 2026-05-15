# 01. 페이지 렌더링 시스템

이 챕터는 *URL 하나가 어떻게 한 페이지로 변환되는지*의 전체 파이프라인입니다. 어디를 만질지 정하기 전에 이 흐름을 이해해야 합니다.

## 1. 페이지 4가지 유형

URL이 들어오면 다음 중 하나로 분류됩니다:

| 유형 | URL 패턴 | 좌표 조건 | 컨트롤러 |
|---|---|---|---|
| **루트** | `/` | 없음 | [PublicHomeController](../../src/Controller/Public/PublicHomeController.php) |
| **테마 허브** | `/{theme-slug}/` | `region IS NULL` AND `theme.parent IS NULL` | [PublicNodeController](../../src/Controller/Public/PublicNodeController.php) |
| **지역 페이지** | `/{theme}/{sido}/.../` | `region IS NOT NULL` | [PublicNodeController](../../src/Controller/Public/PublicNodeController.php) |
| **자식 테마 페이지** | `/{parent}/{child}/` | `region IS NULL` AND `theme.parent IS NOT NULL` | [PublicNodeController](../../src/Controller/Public/PublicNodeController.php) |

`PublicNodeController`는 모든 비-루트 URL을 받고, [PathResolver](../../src/Service/PathResolver.php)로 좌표(theme + region)를 ContentNode 행에 매핑합니다. 매칭 실패 시 404, `status='dead'`이면 [Redirect](../../src/Entity/Redirect.php) 테이블 조회 후 301.

## 2. 디스패처 — 외형 결정

[node.html.twig](../../templates/public/node.html.twig)는 *분기 로직만 담는 디스패처*입니다. 어떤 템플릿으로 렌더할지 다음 우선순위로 결정합니다:

```
1. template_override 파일 존재?    → 사용 (모든 자동 분기 무시)
   templates/public/hubs/{theme-path}.html.twig
   조건: region IS NULL (지역 페이지는 절대 오버라이드 불가)

2. body_template == guide_*?       → _guide.html.twig
3. body_template == essay?         → _essay.html.twig
4. body_template == report?        → _report.html.twig
5. body_template == case_study?    → _case_study.html.twig

6. 그 외 (지역 페이지 + 오버라이드 없는 매트릭스 허브) → matrix_template
   matrix_template = templates/public/matrix/{root-theme-slug}.html.twig
                     (없으면 templates/public/_matrix.html.twig 제네릭 fallback)
```

이 우선순위가 **시스템 전체의 외형 통제 규칙**입니다. 외워두거나 자주 참조할 가치가 있어요. 첫 매칭에서 멈춥니다 — 위가 아래를 이깁니다.

## 3. 템플릿 컨텍스트

두 종류로 분리됩니다.

### 3.1 페이지 단위 컨텍스트 — `PublicNodeController::buildContext()`

| 변수 | 타입 | 설명 |
|---|---|---|
| `node` | [ContentNode](../../src/Entity/ContentNode.php) | 현재 페이지의 노드 |
| `url` | string | 정규 URL |
| `h1` | string | 타이틀용 키워드 (지역명 + 테마명) |
| `template` | [BodyTemplate](../../src/Entity/Enum/BodyTemplate.php) | **결정된 템플릿 enum (CTA 분기에도 사용 — 03 챕터)** |
| `body_html` | string\|null | Markdown→HTML (prose 변종에서만) |
| `template_override` | string\|null | 매칭된 오버라이드 파일 경로 |
| `matrix_template` | string | 매트릭스 셸 경로 (테마 또는 fallback) |
| `byKind` | array | DataPoint를 kind별로 그룹화 (verified만) |
| `crumbs` | list | 빵부스러기 (label/url/current) |
| `json_ld`, `breadcrumbs_jsonld` | string | Schema.org JSON-LD |

오버라이드 파일도 이 컨텍스트를 그대로 받습니다. 추가 변수가 필요하면 `buildContext()`를 확장하세요.

### 3.2 Twig 글로벌 — `config/packages/twig.yaml`

페이지 *무관한* 헬퍼는 글로벌로 노출돼 `with … only` 인클루드에서도 접근 가능:

| 글로벌 | 클래스 | 용도 |
|---|---|---|
| `nav` | [NodeNavigator](../../src/Service/NodeNavigator.php) | 계층 질의 (parent/children/siblings/ancestors/themeChain/regionChain) |
| `urls` | [UrlBuilder](../../src/Service/UrlBuilder.php) | URL 생성 헬퍼 |

```twig
{# 부모, 형제, 자식 모두 글로벌로 1줄 호출 #}
{{ nav.parent(node) }}
{{ nav.siblings(node) }}             {# region 축 (기본) #}
{{ nav.siblings(node, 'theme') }}    {# 테마 형제들 #}
{{ nav.children(node, 'theme') }}    {# 자식 테마들 #}
{{ nav.ancestors(node) }}            {# 루트→부모 ContentNode 리스트 #}
```

전체 API는 [docs/ai/03-page-derivation.md §7.3](../ai/03-page-derivation.md#73-nodenavigator-api). 계층 표현 파셜은 [02-template-overrides.md §4](02-template-overrides.md#4-파셜-라이브러리)에 카탈로그.

## 4. URL 도출 (역방향)

ContentNode에서 URL을 만드는 것은 [UrlBuilder](../../src/Service/UrlBuilder.php)의 책임:

```
url(node) = "/" + theme_path(node.theme) + "/" + region_path(node.region) + "/"
```

URL은 *항상 함수의 출력*입니다. 손으로 만들지 않습니다. 같은 (theme, region) 좌표는 UNIQUE 제약으로 *반드시* 1개의 URL만 가집니다 — canonical이 자동 보장돼요.

## 5. 캐시 헤더

[PublicNodeController](../../src/Controller/Public/PublicNodeController.php)에서:

```
Cache-Control: public, max-age=300, s-maxage=1800
Last-Modified: {node.last_review_at or first_published_at}
```

- 브라우저 5분 / CDN 30분
- `If-Modified-Since`로 304 가능
- **무효화 트리거**: `last_review_at` 변경. 데이터 수정 후 캐시가 안 깨지면:
  ```sql
  UPDATE content_node SET last_review_at = NOW() WHERE status = 'live';
  ```

자세한 캐시 시나리오는 [08-platform.md](08-platform.md#캐시--성능)에서.

## 관련 문서

- 페이지 도출의 *왜*: [docs/dev/04-page-system-architecture.md](../dev/04-page-system-architecture.md)
- 페이지 도출의 *룰*: [docs/ai/03-page-derivation.md](../ai/03-page-derivation.md)
- 어디를 만질지 결정: [02-template-overrides.md](02-template-overrides.md)

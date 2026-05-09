# 04. 콘텐츠 타입 (BodyTemplate)

## 1. 패밀리 카탈로그

[BodyTemplate enum](../../src/Entity/Enum/BodyTemplate.php) 5개:

| 패밀리 | 자동/수동 | 발행 게이트 | 톤 |
|---|---|---|---|
| `Matrix` | 자동 (bodyTemplate=NULL → 기본) | data_count ≥ 5 | 매트릭스 |
| `Guide` | 수동 | body 3,000자+ AND verified FAQ ≥ 3 | how-to |
| `Essay` | 수동 | body 3,000자+ | 에디토리얼 |
| `Report` | 수동 | body 5,000자+ | 데이터 보고서 |
| `CaseStudy` | 수동 | body 1,500자+ | 사례 연구 |

발행 게이트는 [최신 마이그레이션](../../migrations/)의 `fn_publish_gate` PostgreSQL 함수에 정의. 모든 prose 패밀리는 추가로 `intro_text` 비어있지 않음 + verified author 필수.

매트릭스 자식 변종(시도/시군구/동)은 enum 값에 박지 않습니다. 같은 `Matrix` 패밀리로 렌더되고 시각 차이는 `region.depth`와 데이터에서 자연스럽게 나옵니다. 가이드 안의 sub-kind(심층/비교/FAQ)도 같은 이유로 enum에 두지 않습니다 — 차이는 작성자 보이스와 Markdown 본문이 만듭니다.

상세 카탈로그(렌더 시그니처, 사용처): [docs/ai/04-content-types.md](../ai/04-content-types.md).

## 2. 자동 vs 수동 — 작동 원리

[PublicNodeController::deriveTemplate()](../../src/Controller/Public/PublicNodeController.php):

```
return node.bodyTemplate ?? Matrix
```

매트릭스 페이지는 `bodyTemplate`을 비워두면 됩니다 (혹은 명시적으로 `Matrix` 선택). Prose 패밀리(Guide/Essay/Report/CaseStudy)는 *반드시 명시*해야 합니다 — 그렇지 않으면 자동으로 `Matrix`가 되어 매트릭스 게이트(data_count ≥ 5)를 받습니다.

## 3. 게이트 분기 — DB 함수가 강제

`fn_publish_gate` 트리거가 *코드 외부에서* 게이트를 강제합니다. 운영자가 SQL 콘솔에서 직접 INSERT해도 막힙니다. 이는 의도된 안전장치 — Month 2~3 충돌 시기에 "AI로 동 페이지 3,500개 한 번에 만들기" 같은 우회를 차단합니다.

## 4. 게이트 변경

발행 요건을 바꾸려면 *새 마이그레이션*으로 함수를 통째로 `CREATE OR REPLACE`:

1. 새 마이그레이션 파일 생성 (`make:migration` 또는 수동)
2. `fn_publish_gate`를 `CREATE OR REPLACE` (가장 최근 함수 정의 복사 → 수정)
3. **순서 주의**: 함수를 먼저 교체 → row 업데이트 나중. 옛 트리거가 새 값을 잘못 분류해 강등시키는 함정 방지
4. 기존 live 노드의 강등 위험 시뮬레이션 (`SELECT` 으로 미리)
5. `down()` 마이그레이션 작성 (개발 단계엔 비워도 OK)
6. 시드(`app:seed:demo`)가 새 게이트 통과하는지 검증

가장 최근 게이트 정의는 [migrations/](../../migrations/) 디렉토리의 가장 큰 번호 파일에서 찾으세요.

## 5. 새 콘텐츠 타입 추가하기

새 BodyTemplate 패밀리(예: `Interview`)를 추가하려면 7곳을 만집니다. 순서대로:

### 5.1 enum

[src/Entity/Enum/BodyTemplate.php](../../src/Entity/Enum/BodyTemplate.php):
```php
case Interview = 'interview';

public function isInterview(): bool {
    return $this === self::Interview;
}

public function isProse(): bool {
    return $this->isGuide() || $this->isEssay() || $this->isReport()
        || $this->isCaseStudy() || $this->isInterview();
}
```

### 5.2 마이그레이션 (게이트)

새 마이그레이션 파일 → `fn_publish_gate`의 CASE 문에 추가:
```sql
WHEN 'interview' THEN
    gate_kind := 'interview';
    min_len := 2000;          -- 인터뷰는 Q&A라 짧을 수 있음
    require_faq := FALSE;
```

순서: **함수를 먼저 교체, 그 다음 row 업데이트** (위 §4 동일).

### 5.3 디스패처

[templates/public/node.html.twig](../../templates/public/node.html.twig):
```twig
{% set is_interview = template.isInterview() %}

...
{% elseif is_interview %}
    {% include 'public/_interview.html.twig' %}
```

### 5.4 템플릿

`templates/public/_interview.html.twig` 신규. 컨텍스트 그대로 받음 (`node`, `byKind`, `body_html`, `theme_siblings` 등).

> **CTA 통합**: 새 prose 템플릿이 final CTA를 표시한다면 BodyTemplate 분기를 위해 `body_template: template`을 전달:
> ```twig
> {% include 'public/_partials/final_cta.html.twig' with {
>     title: '...',
>     body_template: template,
> } only %}
> ```
> 또는 prose 템플릿 안에서 직접 dispatcher 파셜 호출:
> ```twig
> {% set _cta = cta('final', template) %}
> {% include 'public/_partials/cta/_block_body.html.twig' with { cta: _cta } only %}
> ```
> 자세한 동작은 [03-cta-system.md](03-cta-system.md).

### 5.5 SCSS

`assets/scss/brand/_interview.scss` 신규 + [app.scss](../../assets/scss/brand/app.scss)에 `@use 'interview';` 추가.

### 5.6 폼 라벨

[src/Form/ContentNodeType.php](../../src/Form/ContentNodeType.php) `bodyTemplate`의 `choice_label` match 문에 추가:
```php
BodyTemplate::Interview => '인터뷰 (interview)',
```

### 5.7 시드 (선택)

[src/Command/SeedDemoCommand.php](../../src/Command/SeedDemoCommand.php) — 데모 데이터를 시드하려면 `INTERVIEW_DEMOS` 상수 + `seedInterviews()` 메소드 추가. 패턴은 `seedEssays()` 참조.

## 6. 가벼운 대안 — enum 안 만지기

새 콘텐츠 *타입*이 시각적으로 비슷하면 enum 안 늘리고 **자식 테마 + 기존 패밀리**로 해결:

- `contents` 아래 자식 테마 `parent-tip` 만들고 `body_template = essay` 사용
- 모든 page-level 자유는 `hubs/contents/parent-tip.html.twig` 오버라이드로

**enum 추가가 정당한 경우**:

- 발행 게이트 임계값이 *기존 패밀리와 다름*
- 시각 시그니처가 *기존 템플릿과 명확히 다름* (drop cap vs 인용 박스 등)
- 사용처(테마)가 *기존 패밀리로는 표현 불가능*

세 조건 모두 *거짓*이면: 기존 패밀리 사용하면 됩니다. 새 패밀리 추가는 *시스템 복잡도 증가*이므로 정당화가 필요합니다 — [docs/ai/04-content-types.md §9](../ai/04-content-types.md#9-셀프-검증-체크리스트). 같은 패밀리 안에서 sub-kind를 갈라야 한다는 충동도 같은 기준으로 통제하세요 — *시각 구조가 실제로 다른가*만이 정당화 사유입니다.

## 관련 문서

- 변종 상세 (시각 시그니처, 사용처): [docs/ai/04-content-types.md](../ai/04-content-types.md)
- 페이지 시스템 *왜*: [docs/dev/04-page-system-architecture.md](../dev/04-page-system-architecture.md)

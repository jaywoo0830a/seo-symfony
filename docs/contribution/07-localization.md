# 07. 로컬라이제이션 — 다른 국가/도메인 입양

이 시스템은 *한국 행정구역*을 기본 가정합니다. 다른 국가로 입양하려면 두 축을 함께 손봐야 합니다: **Region 데이터**와 **BodyTemplate 매핑**.

## 1. 행정구역 데이터 교체

[RegionFixtures](../../src/DataFixtures/RegionFixtures.php)는 한국 행정구역(시도 17 / 시군구 ~228 / 동·읍·면 ~3,500)을 시드합니다. 다른 국가용으로 바꾸려면 통째로 교체:

```php
// 예: 미국 (state / county 2단계)
private const STATES = [
    'california' => 'California',
    'texas'      => 'Texas',
    // ...
];

private const COUNTIES_BY_STATE = [
    'california' => ['los-angeles' => 'Los Angeles County', ...],
    // ...
];
```

깊이 단계별 의도:
- depth 0: 전국 루트 (가상 — 1개 row만)
- depth 1: 대단위 (시도, state)
- depth 2: 중단위 (시군구, county)
- depth 3: 소단위 (동, neighborhood)

## 2. 깊이 단계가 다를 때

깊이 단계가 한국과 다르면 (예: 미국 = 주/카운티 2단계만) **BodyTemplate enum 자체는 손볼 필요가 없습니다**. 매트릭스 자식 변종(시도/시군구/동)은 의도적으로 enum에 박지 않았습니다 — 모두 같은 `BodyTemplate::Matrix` 패밀리로 렌더되고, 시각 차이는 `region.depth`와 데이터에서 자연스럽게 나옵니다.

손볼 곳은 다음 두 가지뿐:

1. **RegionFixtures** — 깊이별 행정구역 데이터 교체 (위 §1)
2. **매트릭스 셸 카피** — `templates/public/matrix/{theme}.html.twig` 안에서 깊이별 헤더·카드 텍스트가 한국어로 박힌 부분 ("시도 단위 시급" 등) 번역. `region.depth` 값으로 분기하면 됨

[deriveTemplate()](../../src/Controller/Public/PublicNodeController.php)는 단순히 `bodyTemplate ?? Matrix` 한 줄이므로 깊이 단계와 무관하게 그대로 작동합니다.

## 3. 카피 / 톤

한국어 디폴트가 박힌 곳들:

| 위치 | 내용 | 변경 필요? |
|---|---|---|
| [_partials/hero.html.twig](../../templates/public/_partials/hero.html.twig) | hero CTA 디폴트 | 보통 호출자가 덮어씀 |
| [_partials/final_cta.html.twig](../../templates/public/_partials/final_cta.html.twig) | final CTA 디폴트 | 동일 |
| [config/cta.yaml](../../config/cta.yaml) | CTA 라벨/문구/assurances | **번역 필요** |
| [matrix/{theme}.html.twig](../../templates/public/matrix/) | 매트릭스 셸 카피 | **번역 필요** |
| [hubs/*.html.twig](../../templates/public/hubs/) | 페이지 단위 오버라이드 | **번역 필요** |
| [SeedDemoCommand](../../src/Command/SeedDemoCommand.php) | 데모 텍스트 (`buildIntro()`, `buildDataPoints()`) | 도메인 변경 시 |
| [.env BRAND_PHONE_HOURS](../../.env) | "평일 09:00 – 21:00" | 시간 표기 |

i18n(다국어)은 *이 시스템의 범위 밖*입니다 — [docs/ai/06-vocabulary.md §13](../ai/06-vocabulary.md#13-비-목록-이-시스템과-무관)에 명시. 사이트는 *단일 언어 + 단일 시장*을 가정.

## 4. URL slug 정책

지역 slug는 *영문 소문자 + 하이픈*이 디폴트. 다른 문자 체계(예: 일본어 히라가나, 중국어 한자)를 URL에 넣고 싶으면:

- **권장**: 로마자 slug 유지 (SEO/공유 친화적). 예: `tokyo`, `shibuya-ku`
- **비권장**: 비-ASCII slug. URL 인코딩되어 보기 흉하고 일부 분석 도구가 제대로 처리 못 함

slug → 실제 한글 이름 매핑은 `Region.name` 컬럼이 담당하므로 *URL은 영문 slug, 표시는 현지어*가 가능합니다 (현재 한국 시드 동일 패턴).

## 5. 입양 체크리스트 (한국 → 다른 국가)

```
1. RegionFixtures 통째로 교체 (영문 slug + 현지어 name)
2. matrix/*.html.twig 카피 번역 (깊이별 헤더·카드 텍스트 포함)
3. cta.yaml의 라벨/문구 번역
4. hubs/*.html.twig 오버라이드 번역
5. .env BRAND_* 값 현지화
6. SeedDemoCommand 데모 데이터 도메인 맞게 (또는 시드 안 쓰고 직접 입력)
7. 폰트 — 현지어 지원 여부 확인 (Asta Sans는 라틴/한글; CJK·아랍어는 다른 폰트 필요)
```

BodyTemplate enum은 손댈 필요 없음 — 깊이 단계와 무관하게 작동.

## 관련 문서

- 페이지 시스템: [01-page-system.md](01-page-system.md)
- 콘텐츠 타입 (패밀리 카탈로그): [04-content-types.md](04-content-types.md)
- 시드/픽스처: [08-platform.md §시드--샘플-데이터](08-platform.md#시드--샘플-데이터)

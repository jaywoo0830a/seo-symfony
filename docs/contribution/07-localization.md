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

깊이 단계가 한국과 다르면 (예: 미국 = 주/카운티 2단계만) [BodyTemplate enum](../../src/Entity/Enum/BodyTemplate.php)의 `Sido/Sigungu/Dong` 변종 이름 자체가 의미와 안 맞을 수 있습니다.

### 옵션 A — 변종 이름 유지하고 매핑만 변경

가장 가벼운 방식. enum case는 그대로 두고 `deriveTemplate()`의 깊이 매핑만 조정:

```php
// PublicNodeController::deriveTemplate()
return match ($regionDepth) {
    null, 0 => BodyTemplate::Hub,
    1 => BodyTemplate::Sido,      // 의미: state
    2 => BodyTemplate::Sigungu,   // 의미: county
    // depth 3 안 씀
    default => BodyTemplate::Hub,
};
```

이 방식의 단점: enum 이름이 한국어 어원이라 외국 개발자에게 어색함.

### 옵션 B — 변종 이름까지 변경

깔끔하지만 마이그레이션 + 시드 데이터 + 폼 라벨 모두 손봐야 함:

1. [BodyTemplate.php](../../src/Entity/Enum/BodyTemplate.php) — `case Sido = 'sido';` → `case StateLevel = 'state_level';` 등
2. 새 마이그레이션 — `body_template` 컬럼의 enum 값 변환 (`UPDATE content_node SET body_template = 'state_level' WHERE body_template = 'sido';`)
3. `fn_publish_gate` 함수의 CASE 문 업데이트
4. [PublicNodeController::deriveTemplate()](../../src/Controller/Public/PublicNodeController.php) 매핑 변경
5. [ContentNodeType](../../src/Form/ContentNodeType.php)의 choice_label match 업데이트
6. [SeedDemoCommand](../../src/Command/SeedDemoCommand.php) 시드 데이터의 BodyTemplate 참조 업데이트

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
2. BodyTemplate 깊이 매핑 결정 (옵션 A or B 위 §2)
3. cta.yaml의 라벨/문구 번역
4. matrix/*.html.twig 카피 번역
5. hubs/*.html.twig 오버라이드 번역
6. .env BRAND_* 값 현지화
7. SeedDemoCommand 데모 데이터 도메인 맞게 (또는 시드 안 쓰고 직접 입력)
8. 폰트 — 현지어 지원 여부 확인 (Asta Sans는 라틴/한글; CJK·아랍어는 다른 폰트 필요)
```

## 관련 문서

- 페이지 시스템: [01-page-system.md](01-page-system.md)
- 콘텐츠 타입 (변종 카탈로그): [04-content-types.md](04-content-types.md)
- 시드/픽스처: [08-platform.md §시드--샘플-데이터](08-platform.md#시드--샘플-데이터)

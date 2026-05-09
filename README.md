# seo-symfony — Programmatic SEO Starter

Symfony 기반 *programmatic SEO* 사이트의 빈 스타터입니다. 지역×테마 매트릭스 + prose 콘텐츠 패밀리(가이드/에세이/리포트/사례 연구) + 발행 게이트(DB 트리거) + CTA 시스템이 엔진으로 들어 있고, 도메인(테마/지역/시드 데이터)은 **비어 있습니다**.

다운로드해서 자신의 도메인을 채워 시작하세요.

## 두 가지 브랜치

| 브랜치 | 용도 |
|---|---|
| **`main`** (현재) | 빈 스타터. 엔진만 — 도메인 데이터·시드·도메인 특화 오버라이드 없음 |
| **`example`** | 한국어 과외 매칭 사이트 데모. 시드 데이터 + 시도/시군구/동 매트릭스 + 가이드/리포트/사례 연구 예제 |

엔진의 *작동 방식*을 보고 싶으면 `example` 브랜치를 참고하세요. `git clone -b example ...` 또는 GitHub Code 탭에서 `example` 선택.

## Quick Start (Docker)

```bash
# 1. 컨테이너 기동 (php + nginx + postgres + node sass watch + mailpit)
bash run/dev/up.sh

# 2. DB 마이그레이션 (자동 실행되지만, 수동으로도 가능)
bash run/dev/console.sh doctrine:migrations:migrate -n

# 3. 관리자 계정 시드 (AdminUserFixtures)
bash run/dev/console.sh doctrine:fixtures:load -n

# 4. 접속
#    Frontend:  http://localhost:8080
#    Admin:     http://localhost:8080/admin (AdminUserFixtures가 만든 계정으로 로그인)
#    Mailpit:   http://localhost:8025
#    Postgres:  127.0.0.1:5433
```

기본 관리자 계정 정보는 [src/DataFixtures/AdminUserFixtures.php](src/DataFixtures/AdminUserFixtures.php) 참고.

## 첫 입양 체크리스트

빈 상태에서 사이트를 채우는 순서:

1. **테마 정의** — `/admin/themes/new` 에서 루트 테마 추가 (예: `tutoring`, `cooking`, `lawyers` 등 도메인에 맞게)
2. **지역 정의** — `/admin/regions/new` 또는 `src/DataFixtures/`에 지역 픽스처 추가. 깊이 0~3 (전국/시도/시군구/동) 자유롭게
3. **작성자 정의** — `/admin/authors/new` 또는 픽스처. `verified_at` 채워야 발행 가능
4. **첫 노드 발행** — `/admin/nodes/new` 에서 (theme, region) 좌표 + intro + 데이터 포인트 5개 이상 → status=live
5. **(선택) 도메인 셸** — `templates/public/matrix/{root-theme.slug}.html.twig` 만들면 그 테마의 모든 매트릭스 페이지 외형이 통일됨
6. **(선택) 페이지 단위 오버라이드** — `templates/public/hubs/{theme-path}.html.twig` (region=NULL 페이지만)

상세 가이드: [docs/contribution/](docs/contribution/) — 5단계 템플릿 오버라이드, BodyTemplate 5패밀리, CTA 시스템 등.

## 디렉토리 구조

```
src/
  Command/                  # (운영 커맨드 — 비어 있음)
  Controller/               # PublicNodeController, ContentNodeShowController, MatrixController...
  Cta/                      # CtaResolver — config/cta.yaml 읽어 슬롯×bodyTemplate→CTA
  DataFixtures/
    AdminUserFixtures.php   # 기본 관리자 1명만
  Entity/
    Enum/
      BodyTemplate.php      # 5케이스: Matrix/Guide/Essay/Report/CaseStudy
      ContentStatus.php
      DataPointKind.php
    ContentNode.php         # 페이지의 추상 단위
    DataPoint.php
    Theme.php
    Region.php
    Author.php
    Redirect.php
  Form/                     # ContentNodeType, ConsultFormType, ...
  Repository/
  Service/                  # PathResolver, UrlBuilder, BreadcrumbBuilder

templates/public/
  layout.html.twig
  node.html.twig            # 디스패처: override > guide > essay > report > case_study > matrix
  _matrix.html.twig         # 제네릭 매트릭스 fallback
  _guide.html.twig          # 가이드 패밀리
  _essay.html.twig
  _report.html.twig
  _case_study.html.twig
  _partials/                # 재사용 블록 (hero, final_cta, faq, ...)
  matrix/                   # (테마별 셸 — 비어 있음. 본인 도메인의 셸을 추가하세요)
  hubs/                     # (페이지 단위 오버라이드 — 비어 있음)

config/
  cta.yaml                  # 슬롯×BodyTemplate→CTA 매핑

migrations/                 # Doctrine 마이그레이션 (DB 스키마 + fn_publish_gate 트리거)
docs/                       # 시스템 문서 (ai/, contribution/, dev/)
```

## 핵심 개념

- **5개 BodyTemplate 패밀리**: `Matrix` (지역 매트릭스), `Guide` (how-to), `Essay` (에세이), `Report` (데이터 리포트), `CaseStudy` (사례 연구). 자세히는 [docs/contribution/04-content-types.md](docs/contribution/04-content-types.md).
- **발행 게이트는 DB 트리거**: `fn_publish_gate` 함수가 `status='live'` 전이를 검증. 코드 외부에서 강제. [docs/dev/03-publish-gate-design.md](docs/dev/03-publish-gate-design.md).
- **5단계 템플릿 디스패치**: 페이지 단위 오버라이드 → 패밀리별 prose 템플릿 → 테마별 매트릭스 셸 → 제네릭 매트릭스. [docs/contribution/02-template-overrides.md](docs/contribution/02-template-overrides.md).
- **CTA는 yaml 매핑**: `config/cta.yaml`에서 슬롯(navbar/hero/final) × BodyTemplate → CTA 정의. [docs/contribution/03-cta-system.md](docs/contribution/03-cta-system.md).

## 문서

- [CUSTOMIZATION.md](CUSTOMIZATION.md) — `docs/contribution/` 색인 (이 스타터를 입양·확장)
- [OPERATIONS.md](OPERATIONS.md) — 운영자(어드민) 워크플로우 가이드
- [docs/ai/](docs/ai/) — LLM이 시스템 룰을 빠르게 조회하기 위한 룰북
- [docs/dev/](docs/dev/) — 시스템 설계 *왜*

문서의 일부 예시(테마/지역 관련 `tutoring`, `seoul` 등)는 `example` 브랜치의 데모 도메인을 가리킵니다. 실제 동작 코드는 `example` 브랜치에 있습니다.

## 라이선스

(라이선스 파일을 추가하세요)

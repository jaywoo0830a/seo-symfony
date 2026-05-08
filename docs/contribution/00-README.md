# Customization Guide — 색인

이 디렉토리는 이 코드베이스를 **새 프로젝트에 입양하거나 확장하는 개발자**를 위한 가이드입니다. *어디를 어떻게 바꾸는지*를 결정 규칙과 파일 경로로 정리했습니다.

> 다른 디렉토리와의 차이:
> - [docs/dev/](../dev/) — *왜 이렇게 설계됐는가* (개발자 onboarding)
> - [docs/ai/](../ai/) — 시스템 룰 빠른 조회 (LLM 친화)
> - [OPERATIONS.md](../../OPERATIONS.md) — *오늘 무엇을 해야 하나* (운영자)
> - **본 디렉토리** — *어디를 만져야 하나* (입양/확장)

## 챕터 구성

| # | 파일 | 내용 |
|---|---|---|
| 01 | [페이지 시스템](01-page-system.md) | 렌더 파이프라인, 디스패처 우선순위, 템플릿 컨텍스트 |
| 02 | [템플릿 오버라이드](02-template-overrides.md) | 5단계 자율도 (페이지/매트릭스/prose/파셜/루트) + 파셜 카탈로그 |
| 03 | [CTA 시스템](03-cta-system.md) | `cta.yaml` 매핑, 슬롯, 디스패처 파셜, 폼 + Notifier |
| 04 | [콘텐츠 타입](04-content-types.md) | BodyTemplate 카탈로그 + 새 변종 추가 (7단계) |
| 05 | [테마 + 콘텐츠 운영](05-themes-and-content.md) | 새 테마 추가 + 어드민 CRUD |
| 06 | [브랜드 + 디자인](06-brand-and-design.md) | `.env` 식별값, 디자인 토큰, SCSS 빌드 |
| 07 | [로컬라이제이션](07-localization.md) | 다른 국가/도메인 입양 (한국 행정구역 → 다른 체계) |
| 08 | [플랫폼](08-platform.md) | 보안/인증 + 캐시 + 시드/픽스처 |
| 09 | [결정 규칙](09-decision-rules.md) | AI 친화적 Q&A + 파일 레퍼런스 |

---

## Quick Start — 5분 입양

```bash
# 1. 환경 변수 설정 (브랜드명/전화번호/DB)
cp .env .env.local && vi .env.local

# 2. DB 초기화 + 시드
php bin/console doctrine:database:drop --force --if-exists
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
php bin/console app:admin:create me@example.com <password>
php bin/console app:seed:demo

# 3. 자산 빌드 + 서버
npm install && npm run build
php -S 127.0.0.1:8000 -t public
```

이렇게 하면 17개 데모 페이지가 뜨는 완성된 사이트가 됩니다.

---

## 첫 입양 체크리스트

1. `.env.local`에 `BRAND_*` 4개 + `DATABASE_URL` 본인 값 ([06-brand-and-design.md](06-brand-and-design.md))
2. 컬러 토큰 변경 → `npm run build` ([06-brand-and-design.md](06-brand-and-design.md#디자인-토큰))
3. DB 초기화 + 마이그레이션 + 픽스처 (위 Quick Start)
4. `php bin/console app:admin:create me@example.com <pw>`
5. (선택) `php bin/console app:seed:demo`로 데모 콘텐츠 ([08-platform.md](08-platform.md#시드--샘플-데이터))
6. 도메인이 한국이 아니면 [07-localization.md](07-localization.md)
7. 브랜드 톤이 다르면 매트릭스 셸 변경 + 페이지 오버라이드 ([02-template-overrides.md](02-template-overrides.md))
8. CTA 정책 검토 — 사이트 전반 전화 vs 가이드 페이지 폼 등 ([03-cta-system.md](03-cta-system.md))

---

## 작업별 진입점

찾고자 하는 작업에 따라 챕터를 골라 들어가세요:

| 하고 싶은 일 | 진입 챕터 |
|---|---|
| 한 페이지만 다르게 보이게 | [02 §단일 페이지 오버라이드](02-template-overrides.md#1-단일-페이지-오버라이드-가장-자유) |
| 한 테마의 모든 지역 페이지 외형 | [02 §매트릭스 셸](02-template-overrides.md#2-테마-단위-매트릭스-셸) |
| 모든 가이드/에세이 외형 | [02 §Prose 템플릿](02-template-overrides.md#3-prose-템플릿) |
| 가이드 페이지에 폼 CTA 띄우기 | [03 §매핑 변경](03-cta-system.md#매핑-테이블-편집) |
| 새 폼 타입 추가 | [03 §새 폼 추가하기](03-cta-system.md#새-폼-추가하기) |
| 새 알림 채널 추가 | [03 §새 Notifier 추가](03-cta-system.md#새-notifier-추가) |
| 새 콘텐츠 타입 추가 | [04 §확장 패턴](04-content-types.md#새-콘텐츠-타입-추가하기) |
| 발행 게이트 룰 변경 | [04 §게이트 변경](04-content-types.md#게이트-변경) |
| 다른 국가로 입양 | [07-localization.md](07-localization.md) |

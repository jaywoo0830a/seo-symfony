# 03. CTA 시스템

CTA(Call To Action)는 페이지마다 박힌 *행동 유도 블록*입니다. 이 시스템은 사이트 전반의 CTA를 **YAML 설정 한 파일**에서 결정하고, 페이지 위치(슬롯)와 콘텐츠 타입(BodyTemplate) 조합으로 분기합니다.

> **5개 엔티티 외부**: CTA는 Theme/Region/Author/ContentNode/DataPoint 어디에도 속하지 않습니다. *브랜드 식별값과 사업 정책의 결합*으로 보고 데이터 모델 밖에 배치됐습니다. 운영자는 CTA를 만지지 않으며, 폼 제출도 자체 DB에 저장하지 않습니다 (외부 채널로 forward).

## 핵심 모델

```
슬롯 × BodyTemplate → CTA 객체
```

세 가지 핵심 개념:

| 개념 | 의미 | 어디서 |
|---|---|---|
| **슬롯** (`CtaSlot`) | 페이지 안 위치. `navbar` / `hero` / `final` | [src/Cta/CtaSlot.php](../../src/Cta/CtaSlot.php) |
| **타입** (`CtaType`) | CTA의 행동 종류. `phone` / `form` / `external` / `email` / `messenger` | [src/Cta/CtaType.php](../../src/Cta/CtaType.php) |
| **CTA 객체** (`Cta`) | 한 슬롯에 채워질 단위 (label, payload, hint, assurances) | [src/Cta/Cta.php](../../src/Cta/Cta.php) |

## 1. 매핑 테이블 — `config/cta.yaml`

이 한 파일이 *언제 어떤 CTA를 보여줄지*를 결정합니다. 운영자는 만지지 않고, 사업 정책 결정 시 개발자가 편집합니다.

```yaml
# config/cta.yaml
parameters:
  cta.config:
    ctas:
      brand_phone:
        type: phone
        label: '지금 상담받기'
        payload:
          phone: '%env(BRAND_PHONE)%'
        hint: '%env(BRAND_PHONE_HOURS)%'
        assurances: ['상담 비용 없음', '매칭 후 환불 보장', '강사 신원 100% 검증']

      guide_consult_form:
        type: form
        label: '맞춤 추천 받기'
        payload:
          form: consult        # FormType key
        hint: '평일 24시간 내 회신'
        assurances: ['상담 비용 없음', '익명 상담 가능']

    slots:
      navbar:
        - { match: '*',         cta: brand_phone }

      hero:
        - { match: 'guide_*',   cta: guide_consult_form }
        - { match: '*',         cta: brand_phone }

      final:
        - { match: 'guide_*',   cta: guide_consult_form }
        - { match: '*',         cta: brand_phone }
```

### 매핑 규칙 (CtaResolver)

1. 페이지 진입 → 슬롯 + 페이지의 `BodyTemplate`이 [CtaResolver](../../src/Cta/CtaResolver.php)에 입력
2. `slots.<slot>` 의 룰들을 위에서 아래로 평가
3. `match`는 `fnmatch` 패턴 — `'guide_*'`, `'essay'`, `'*'` 등
4. 첫 매칭 룰의 `cta` 키로 `ctas.<key>` 조회
5. 매칭 실패 시 `brand_phone` 안전 디폴트

```
fnmatch('guide_*',  'guide_longform') → true   → guide_consult_form
fnmatch('guide_*',  'hub')             → false
fnmatch('*',        'hub')             → true   → brand_phone
```

## 2. 슬롯 카탈로그

| 슬롯 | 위치 | BodyTemplate 컨텍스트 | 비고 |
|---|---|---|---|
| `navbar` | [layout.html.twig](../../templates/public/layout.html.twig) 상단 바 | **없음** (layout 차원) | 항상 글로벌 fallback |
| `hero` | [_partials/hero.html.twig](../../templates/public/_partials/hero.html.twig) 페이지 상단 | 호출자가 `body_template: template` 전달 | 단일 버튼 |
| `final` | [_partials/final_cta.html.twig](../../templates/public/_partials/final_cta.html.twig) 페이지 하단 | 동일 | 폼 임베드 가능 |

### navbar의 한계

navbar는 layout 레벨이라 페이지의 `BodyTemplate`을 모릅니다. 그래서 cta.yaml의 `navbar` 슬롯은 항상 `*` 룰로 글로벌 CTA를 가져갑니다. 페이지별로 다른 navbar CTA를 원하면 layout을 controller가 BodyTemplate을 받게 리팩터해야 합니다 — 보통 그럴 일은 없습니다.

## 3. 타입 카탈로그

다섯 가지 type. 각 type별로 `payload` 스키마와 렌더 모양이 정해져 있습니다:

| type | payload | hero(button) 모양 | final(block_body) 모양 |
|---|---|---|---|
| `phone` | `{ phone: '1588-1234' }` | `<a href="tel:...">📞 라벨</a>` | 라운드 풀 큰 전화 링크 |
| `form` | `{ form: 'consult' }` | `<a href="#cta-form-consult">✉️ 라벨</a>` (스크롤 앵커) | 실제 `<form>` 임베드 |
| `external` | `{ url: 'https://...' }` | `<a href="..." target="_blank">라벨</a>` | 동일 (블록 래퍼) |
| `email` | `{ email: 'a@b.c' }` | `<a href="mailto:...">✉️ 라벨</a>` | 동일 |
| `messenger` | `{ url: 'https://pf.kakao.com/...' }` | `<a href="..." target="_blank">💬 라벨</a>` | 동일 |

타입별 분기는 두 디스패처 파셜에 있습니다:
- [_partials/cta/_button.html.twig](../../templates/public/_partials/cta/_button.html.twig) — hero/navbar/footer용 단일 버튼
- [_partials/cta/_block_body.html.twig](../../templates/public/_partials/cta/_block_body.html.twig) — final_cta 안쪽 본문

## 4. 폼 시스템

`type: form`인 CTA는 `payload.form` 키로 FormType을 식별. 현재 등록된 폼:

| form 키 | FormType | 필드 |
|---|---|---|
| `consult` | [ConsultFormType](../../src/Form/Cta/ConsultFormType.php) | name, phone, region, memo, agree (+ honeypot, CSRF) |

### 흐름

```
사용자 폼 제출
   ↓
POST /cta/submit/{form}                           ← CtaSubmitController
   ↓
honeypot 체크 + CSRF 검증 + 폼 validation
   ↓
CompositeCtaNotifier.send(form, data)             ← 텔레그램 + 디스코드 fan-out
   ↓
GET /cta/thank-you (X-Robots-Tag: noindex)
```

자체 DB에 *저장하지 않습니다*. 알림 채널이 영속.

### 새 폼 추가하기

1. **FormType 작성** — `src/Form/Cta/MyFormType.php`. `ConsultFormType`을 베이스로 시작:
   ```php
   final class NewsletterFormType extends AbstractType
   {
       public function buildForm(FormBuilderInterface $builder, array $options): void
       {
           $builder
               ->add('email', EmailType::class, [...])
               ->add('hp', TextType::class, ['mapped' => false, 'row_attr' => ['class' => 'cta-form__honeypot']])
               ->add('agree', CheckboxType::class, ['mapped' => false])
               ->add('submit', SubmitType::class);
       }

       public function configureOptions(OptionsResolver $resolver): void
       {
           $resolver->setDefaults([
               'csrf_protection' => true,
               'csrf_token_id' => 'cta_newsletter',
           ]);
       }
   }
   ```

2. **컨트롤러 등록** — [CtaSubmitController](../../src/Controller/Public/CtaSubmitController.php) `FORM_TYPES` 상수에 추가:
   ```php
   private const FORM_TYPES = [
       'consult' => ConsultFormType::class,
       'newsletter' => NewsletterFormType::class,
   ];
   ```

3. **Twig 함수 등록** — [CtaExtension::ctaFormView()](../../src/Twig/CtaExtension.php)의 match에 추가:
   ```php
   'newsletter' => NewsletterFormType::class,
   ```

4. **payload 처리** — controller `submit()` 메서드의 `$payload` 빌드 부분에서 어느 필드를 알림 채널로 보낼지 명시.

5. **cta.yaml 매핑** — `ctas:` 아래 새 항목 추가하고 `slots:` 룰에 매핑:
   ```yaml
   ctas:
     newsletter_signup:
       type: form
       label: '뉴스레터 구독'
       payload: { form: newsletter }

   slots:
     final:
       - { match: 'essay', cta: newsletter_signup }
       - { match: '*',     cta: brand_phone }
   ```

## 5. 알림 채널 (Notifier)

### 구조

```
CtaNotifierInterface
   ├─ TelegramNotifier
   ├─ DiscordNotifier
   └─ CompositeCtaNotifier (모든 채널 fan-out)
```

CompositeCtaNotifier는 모든 채널 send를 호출하며, 한 채널이 실패해도 다른 채널은 시도합니다 (각 Notifier 내부에서 try/catch).

### 스텁 모드

`.env`에서 토큰이 비어있으면 logger로만 기록하고 실제 전송은 스킵합니다 — 개발/테스트 환경 친화적:

```
CTA_TELEGRAM_BOT_TOKEN=
CTA_TELEGRAM_CHAT_ID=
CTA_DISCORD_WEBHOOK_URL=
```

스텁 모드에서는 [var/log/dev.log](../../var/log/) 에 다음과 같이 출력:
```
CTA[telegram:stub] form=consult data={...}
CTA[discord:stub]  form=consult data={...}
```

### 라이브 활성화

- **텔레그램**: BotFather에서 봇 생성 → 토큰 발급. 봇과 1:1 채팅 시작 후 `https://api.telegram.org/bot<TOKEN>/getUpdates` 호출로 `chat_id` 확인.
- **디스코드**: 채널 설정 → 연동 → 웹훅 → 새 웹훅 → URL 복사.

### 새 Notifier 추가

예: 슬랙 추가:

1. **클래스 작성** — `src/Notifier/SlackNotifier.php`:
   ```php
   final class SlackNotifier implements CtaNotifierInterface
   {
       public function __construct(
           private readonly HttpClientInterface $http,
           private readonly LoggerInterface $logger,
           private readonly string $slackWebhookUrl,
       ) {}

       public function send(string $formName, array $data): void
       {
           if ($this->slackWebhookUrl === '') {
               $this->logger->info('CTA[slack:stub] form={form}', ['form' => $formName]);
               return;
           }
           // POST $this->slackWebhookUrl with $data
       }
   }
   ```

2. **Composite에 추가** — [CompositeCtaNotifier](../../src/Notifier/CompositeCtaNotifier.php) 생성자에 인자 추가:
   ```php
   public function __construct(
       private readonly TelegramNotifier $telegram,
       private readonly DiscordNotifier $discord,
       private readonly SlackNotifier $slack,
   ) {}

   public function send(string $formName, array $data): void
   {
       $this->telegram->send($formName, $data);
       $this->discord->send($formName, $data);
       $this->slack->send($formName, $data);
   }
   ```

3. **환경 변수 추가** — `.env` + [config/services.yaml](../../config/services.yaml) bind:
   ```yaml
   string $slackWebhookUrl: '%env(default::CTA_SLACK_WEBHOOK_URL)%'
   ```

## 6. 보안 / 스팸

| 보호 | 위치 | 비고 |
|---|---|---|
| CSRF | `ConsultFormType::configureOptions()` | Symfony Form 자동 |
| Honeypot | `hp` 필드 + `.cta-form__honeypot` SCSS | 봇이 채우면 조용히 thank-you로 |
| `noindex` thank-you | `CtaSubmitController::thankYou()` | `X-Robots-Tag` + meta robots |
| 동의 체크박스 | `ConsultFormType` agree 필드 | 미동의 시 검증 실패 |
| Rate limit | **TODO — 운영 전 추가** | `framework.rate_limiter` 설정 |

운영 전환 시 [framework.rate_limiter](https://symfony.com/doc/current/rate_limiter.html) 설정으로 IP/세션당 제출 횟수 제한 추가하세요.

## 7. 새 type 추가하기

기본 5종으로 부족할 때만. 예: SMS 인증 후 콜백 받기 같은 특수한 type.

1. [src/Cta/CtaType.php](../../src/Cta/CtaType.php) enum에 case 추가
2. [_button.html.twig](../../templates/public/_partials/cta/_button.html.twig)와 [_block_body.html.twig](../../templates/public/_partials/cta/_block_body.html.twig)에 `{% elseif cta.type.value == 'newtype' %}` 분기 추가
3. cta.yaml의 `ctas:` 항목에서 사용

## 8. 코드 레퍼런스

| 파일 | 역할 |
|---|---|
| [config/cta.yaml](../../config/cta.yaml) | 매핑 테이블 (운영자 not-touch) |
| [src/Cta/](../../src/Cta/) | 도메인 — Cta, CtaSlot, CtaType, CtaResolver |
| [src/Form/Cta/](../../src/Form/Cta/) | FormType들 |
| [src/Notifier/](../../src/Notifier/) | 알림 채널 |
| [src/Twig/CtaExtension.php](../../src/Twig/CtaExtension.php) | Twig 함수 `cta()`, `cta_form_view()` |
| [src/Controller/Public/CtaSubmitController.php](../../src/Controller/Public/CtaSubmitController.php) | POST `/cta/submit/{form}`, GET `/cta/thank-you` |
| [templates/public/_partials/cta/](../../templates/public/_partials/cta/) | 디스패처 파셜 |
| [templates/public/cta/thank_you.html.twig](../../templates/public/cta/thank_you.html.twig) | 제출 후 페이지 |
| [assets/scss/brand/_cta-form.scss](../../assets/scss/brand/_cta-form.scss) | 폼 스타일 |
| `.env` (`BRAND_PHONE`, `CTA_TELEGRAM_*`, `CTA_DISCORD_*`) | 식별값/토큰 |

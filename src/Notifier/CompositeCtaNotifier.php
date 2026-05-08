<?php

declare(strict_types=1);

namespace App\Notifier;

/**
 * 모든 등록된 CTA 알림 채널로 fan-out. 채널 하나가 실패해도 다른 채널은 시도.
 *
 * 현재 구성: 텔레그램 + 디스코드. 추가 채널은 생성자 인자로 늘리기만 하면 됨.
 */
final class CompositeCtaNotifier implements CtaNotifierInterface
{
    public function __construct(
        private readonly TelegramNotifier $telegram,
        private readonly DiscordNotifier $discord,
    ) {}

    public function send(string $formName, array $data): void
    {
        $this->telegram->send($formName, $data);
        $this->discord->send($formName, $data);
    }
}

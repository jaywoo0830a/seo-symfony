<?php

declare(strict_types=1);

namespace App\Notifier;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * 텔레그램 봇으로 CTA 제출 알림 전송.
 *
 * 토큰/chat_id가 비어있으면 logger로만 기록 (스텁 모드).
 * 봇 생성: BotFather → /newbot. chat_id: @userinfobot 또는 봇 채팅에서 /start 후
 *   https://api.telegram.org/bot<TOKEN>/getUpdates 호출로 확인.
 */
final class TelegramNotifier implements CtaNotifierInterface
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
        private readonly string $telegramBotToken,
        private readonly string $telegramChatId,
    ) {}

    public function send(string $formName, array $data): void
    {
        if ($this->telegramBotToken === '' || $this->telegramChatId === '') {
            $this->logger->info('CTA[telegram:stub] form={form} data={data}', [
                'form' => $formName,
                'data' => $data,
            ]);

            return;
        }

        try {
            $this->http->request(
                'POST',
                sprintf('https://api.telegram.org/bot%s/sendMessage', $this->telegramBotToken),
                [
                    'json' => [
                        'chat_id' => $this->telegramChatId,
                        'text' => $this->formatMessage($formName, $data),
                        'parse_mode' => 'HTML',
                        'disable_web_page_preview' => true,
                    ],
                    'timeout' => 5,
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error('CTA[telegram] send failed: {msg}', ['msg' => $e->getMessage()]);
        }
    }

    /** @param array<string, mixed> $data */
    private function formatMessage(string $formName, array $data): string
    {
        $lines = [sprintf('<b>[%s] 신규 폼 제출</b>', htmlspecialchars($formName, \ENT_QUOTES | \ENT_HTML5))];
        foreach ($data as $key => $value) {
            $lines[] = sprintf(
                '· <b>%s</b>: %s',
                htmlspecialchars((string) $key, \ENT_QUOTES | \ENT_HTML5),
                htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_HTML5),
            );
        }

        return implode("\n", $lines);
    }
}

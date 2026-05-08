<?php

declare(strict_types=1);

namespace App\Notifier;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * 디스코드 웹훅으로 CTA 제출 알림 전송.
 *
 * 웹훅 URL이 비어있으면 logger로만 기록 (스텁 모드).
 * 웹훅 생성: 채널 설정 → 연동 → 웹훅 → 새 웹훅 → URL 복사.
 */
final class DiscordNotifier implements CtaNotifierInterface
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
        private readonly string $discordWebhookUrl,
    ) {}

    public function send(string $formName, array $data): void
    {
        if ($this->discordWebhookUrl === '') {
            $this->logger->info('CTA[discord:stub] form={form} data={data}', [
                'form' => $formName,
                'data' => $data,
            ]);

            return;
        }

        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = [
                'name' => (string) $key,
                'value' => (string) ($value === '' || $value === null ? '—' : $value),
                'inline' => false,
            ];
        }

        try {
            $this->http->request('POST', $this->discordWebhookUrl, [
                'json' => [
                    'username' => 'CTA Bot',
                    'embeds' => [[
                        'title' => sprintf('[%s] 신규 폼 제출', $formName),
                        'fields' => $fields,
                        'timestamp' => (new \DateTimeImmutable())->format(\DATE_ATOM),
                    ]],
                ],
                'timeout' => 5,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('CTA[discord] send failed: {msg}', ['msg' => $e->getMessage()]);
        }
    }
}

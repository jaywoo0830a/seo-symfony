<?php

declare(strict_types=1);

namespace App\Notifier;

/**
 * CTA 폼 제출 → 외부 알림 채널 (텔레그램, 디스코드 등) 단일 인터페이스.
 *
 * 시스템은 데이터를 자체 저장하지 않음 (5개 엔티티 외부). 알림이 곧 영속.
 */
interface CtaNotifierInterface
{
    /**
     * @param array<string, mixed> $data 폼 필드 (개인정보 포함). 채널이 알아서 정리해 전송.
     */
    public function send(string $formName, array $data): void;
}

<?php

declare(strict_types=1);

namespace App\Cta;

/**
 * 한 슬롯에 들어가는 CTA 한 단위. 불변 값 객체.
 *
 * payload는 type별로 의미가 다름:
 *  - phone:    ['phone' => '1588-1234']
 *  - form:     ['form' => 'consult']  (FormType 키)
 *  - external: ['url'  => 'https://...']
 *  - email:    ['email' => 'a@b.c']
 *  - messenger: ['url'  => 'https://pf.kakao.com/...']
 */
final class Cta
{
    /**
     * @param array<string, mixed> $payload
     * @param list<string> $assurances
     */
    public function __construct(
        public readonly CtaType $type,
        public readonly string $label,
        public readonly array $payload = [],
        public readonly string $hint = '',
        public readonly array $assurances = [],
    ) {}
}

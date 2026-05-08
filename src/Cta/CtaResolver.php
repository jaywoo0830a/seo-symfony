<?php

declare(strict_types=1);

namespace App\Cta;

use App\Entity\Enum\BodyTemplate;

/**
 * (slot, body_template) → Cta. config/cta.yaml의 매핑 테이블을 따라 첫 매칭 룰을 채택.
 *
 * fallback chain:
 *   1. slots.<slot> 의 룰들을 순서대로 평가 (fnmatch)
 *   2. 매칭 실패 → 'brand_phone' 안전 디폴트
 */
final class CtaResolver
{
    /** @param array{ctas: array<string, array<string, mixed>>, slots: array<string, list<array{match: string, cta: string}>>} $ctaConfig */
    public function __construct(
        private readonly array $ctaConfig,
    ) {}

    public function resolve(CtaSlot $slot, ?BodyTemplate $template = null): Cta
    {
        $rules = $this->ctaConfig['slots'][$slot->value] ?? [];
        $needle = $template?->value ?? '';

        foreach ($rules as $rule) {
            if ($rule['match'] === '*' || fnmatch($rule['match'], $needle)) {
                return $this->build($rule['cta']);
            }
        }

        return $this->build('brand_phone');
    }

    private function build(string $key): Cta
    {
        $def = $this->ctaConfig['ctas'][$key]
            ?? throw new \RuntimeException(sprintf('CTA definition not found: %s', $key));

        return new Cta(
            type: CtaType::from((string) $def['type']),
            label: (string) ($def['label'] ?? ''),
            payload: (array) ($def['payload'] ?? []),
            hint: (string) ($def['hint'] ?? ''),
            assurances: array_values(array_map('strval', (array) ($def['assurances'] ?? []))),
        );
    }
}

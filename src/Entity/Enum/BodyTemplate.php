<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum BodyTemplate: string
{
    case Hub = 'hub';
    case Sido = 'sido';
    case Sigungu = 'sigungu';
    case Dong = 'dong';

    case GuideLongform = 'guide_longform';
    case GuideComparison = 'guide_comparison';
    case GuideFaq = 'guide_faq';

    public function isGuide(): bool
    {
        return match ($this) {
            self::GuideLongform, self::GuideComparison, self::GuideFaq => true,
            default => false,
        };
    }
}

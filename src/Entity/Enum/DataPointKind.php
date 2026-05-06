<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum DataPointKind: string
{
    case Quantitative = 'quantitative';
    case Qualitative = 'qualitative';
    case Comparison = 'comparison';
    case CaseStudy = 'case';
    case Faq = 'faq';
}

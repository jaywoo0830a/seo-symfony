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

    /**
     * 공개 페이지에서 보여줄 한국어 라벨 (섹션 헤딩 등).
     * 어드민 폼 라벨은 더 길고 안내문이 붙음 — DataPointType::buildForm 참고.
     */
    public function label(): string
    {
        return match ($this) {
            self::Quantitative => '수치 데이터',
            self::Qualitative => '정성 데이터',
            self::Comparison => '인접 비교',
            self::CaseStudy => '사례·후기',
            self::Faq => '자주 묻는 질문',
        };
    }
}

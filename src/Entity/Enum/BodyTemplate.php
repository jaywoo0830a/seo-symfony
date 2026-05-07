<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum BodyTemplate: string
{
    // 매트릭스 변종 — 지역 트리 깊이로 자동 결정
    case Hub = 'hub';
    case Sido = 'sido';
    case Sigungu = 'sigungu';
    case Dong = 'dong';

    // 가이드 변종 — how-to 형식, FAQ 중심
    case GuideLongform = 'guide_longform';
    case GuideComparison = 'guide_comparison';
    case GuideFaq = 'guide_faq';

    // 에세이 — 에디토리얼 prose. 변종 구분 없이 단일 타입.
    case Essay = 'essay';

    // 권위 콘텐츠 — 인용 자석
    case Report = 'report';            // 자체 데이터 리포트 (분기/연 발행)
    case CaseStudy = 'case_study';     // 익명화된 실제 사례 연구

    public function isGuide(): bool
    {
        return match ($this) {
            self::GuideLongform, self::GuideComparison, self::GuideFaq => true,
            default => false,
        };
    }

    public function isEssay(): bool
    {
        return $this === self::Essay;
    }

    public function isReport(): bool
    {
        return $this === self::Report;
    }

    public function isCaseStudy(): bool
    {
        return $this === self::CaseStudy;
    }

    public function isProse(): bool
    {
        return $this->isGuide() || $this->isEssay() || $this->isReport() || $this->isCaseStudy();
    }
}

<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * 페이지의 본문 형식 — 한 enum 값 = 한 렌더 패밀리.
 * 매트릭스 자식 변종(시도/시군구/동)은 region.depth에서 파생되므로 enum에 두지 않는다.
 * 가이드 안의 sub-kind(예: 비교/FAQ)는 의도적으로 두지 않음 — 차이는 작성자 보이스와
 * Markdown 본문이 만든다. 필요해지면 그때 분류 도입.
 */
enum BodyTemplate: string
{
    case Matrix = 'matrix';        // 지역 매트릭스 — 구조화 데이터, region.depth로 변주
    case Guide = 'guide';          // how-to 가이드 — prose + FAQ
    case Essay = 'essay';          // 에디토리얼 prose
    case Report = 'report';        // 자체 데이터 리포트 (분기/연 발행)
    case CaseStudy = 'case_study'; // 익명화된 실제 사례 연구

    public function isMatrix(): bool
    {
        return $this === self::Matrix;
    }

    public function isGuide(): bool
    {
        return $this === self::Guide;
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

    /** prose = Markdown 본문이 의미 있는 모든 패밀리 (= Matrix가 아님). */
    public function isProse(): bool
    {
        return $this !== self::Matrix;
    }
}

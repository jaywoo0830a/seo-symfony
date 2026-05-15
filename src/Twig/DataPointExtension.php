<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Enum\DataPointKind;
use Twig\Attribute\AsTwigFunction;

/**
 * DataPoint 렌더 관련 Twig 함수.
 *
 *   {{ datapoint_kind_label('quantitative') }}  → '수치 데이터'
 *   {{ datapoint_kind_label(kind_enum) }}        → 동일
 *
 * `byKind`는 문자열 키(`quantitative` 등)로 구성되므로 *문자열 입력*이 흔함.
 * enum 객체도 받아 양쪽 호출자에서 안전하게 사용.
 */
final class DataPointExtension
{
    #[AsTwigFunction('datapoint_kind_label')]
    public function kindLabel(string|DataPointKind $kind): string
    {
        if (\is_string($kind)) {
            $kind = DataPointKind::from($kind);
        }
        return $kind->label();
    }
}

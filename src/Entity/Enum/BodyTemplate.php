<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum BodyTemplate: string
{
    case Hub = 'hub';
    case Sido = 'sido';
    case Sigungu = 'sigungu';
    case Dong = 'dong';
}

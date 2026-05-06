<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum ContentStatus: string
{
    case Draft = 'draft';
    case Live = 'live';
    case Noindex = 'noindex';
    case Dead = 'dead';
}

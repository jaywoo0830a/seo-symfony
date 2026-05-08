<?php

declare(strict_types=1);

namespace App\Cta;

enum CtaType: string
{
    case Phone = 'phone';
    case Form = 'form';
    case External = 'external';
    case Email = 'email';
    case Messenger = 'messenger';
}

<?php

declare(strict_types=1);

namespace App\Cta;

enum CtaSlot: string
{
    case Navbar = 'navbar';
    case Hero = 'hero';
    case Final = 'final';
}

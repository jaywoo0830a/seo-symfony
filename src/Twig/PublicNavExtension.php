<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Theme;
use App\Repository\ThemeRepository;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig functions for the public site chrome.
 * Auto-registered by Symfony's autoconfigure.
 */
final class PublicNavExtension
{
    public function __construct(
        private readonly ThemeRepository $themes,
    ) {}

    /**
     * @return list<Theme>
     */
    #[AsTwigFunction('top_themes')]
    public function topThemes(): array
    {
        return $this->themes->findRootThemes();
    }
}

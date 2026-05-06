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

    /**
     * 데모용 전화번호. 실제 운영 시 환경변수나 DB에서 가져오도록 교체 권장.
     */
    #[AsTwigFunction('brand_phone')]
    public function brandPhone(): string
    {
        return '1588-1234';
    }

    #[AsTwigFunction('brand_phone_link')]
    public function brandPhoneLink(): string
    {
        return 'tel:1588-1234';
    }
}

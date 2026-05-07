<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Theme;
use App\Repository\ThemeRepository;
use Twig\Attribute\AsTwigFunction;

/**
 * 공개 사이트 + 관리자 chrome용 Twig 함수.
 * 브랜드 식별값은 .env (BRAND_*) → services.yaml bind → 생성자 주입.
 */
final class PublicNavExtension
{
    public function __construct(
        private readonly ThemeRepository $themes,
        private readonly string $brandAdminName,
        private readonly string $brandPublicName,
        private readonly string $brandPhone,
        private readonly string $brandPhoneHours,
    ) {}

    /**
     * @return list<Theme>
     */
    #[AsTwigFunction('top_themes')]
    public function topThemes(): array
    {
        return $this->themes->findRootThemes();
    }

    #[AsTwigFunction('brand_admin_name')]
    public function brandAdminName(): string
    {
        return $this->brandAdminName;
    }

    #[AsTwigFunction('brand_public_name')]
    public function brandPublicName(): string
    {
        return $this->brandPublicName;
    }

    #[AsTwigFunction('brand_phone')]
    public function brandPhone(): string
    {
        return $this->brandPhone;
    }

    #[AsTwigFunction('brand_phone_link')]
    public function brandPhoneLink(): string
    {
        return 'tel:' . $this->brandPhone;
    }

    #[AsTwigFunction('brand_phone_hours')]
    public function brandPhoneHours(): string
    {
        return $this->brandPhoneHours;
    }
}

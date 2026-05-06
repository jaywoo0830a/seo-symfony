<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContentNodeRepository;
use App\Repository\ThemeRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/themes', name: 'admin_themes', methods: ['GET'])]
final class ThemesController extends AbstractController
{
    #[Template('dashboard/themes/index.html.twig')]
    public function __invoke(
        ThemeRepository $themes,
        ContentNodeRepository $nodes,
    ): array {
        $rows = [];
        foreach ($themes->findRootThemes() as $theme) {
            $rows[] = [
                'theme' => $theme,
                'nodeCount' => $nodes->count(['theme' => $theme]),
            ];
        }

        return ['rows' => $rows];
    }
}

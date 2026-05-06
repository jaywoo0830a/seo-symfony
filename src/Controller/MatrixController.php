<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContentNodeRepository;
use App\Repository\RegionRepository;
use App\Repository\ThemeRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/matrix', name: 'admin_matrix', methods: ['GET'])]
final class MatrixController extends AbstractController
{
    #[Template('dashboard/matrix.html.twig')]
    public function __invoke(
        ThemeRepository $themes,
        RegionRepository $regions,
        ContentNodeRepository $nodes,
    ): array {
        $rootThemes = $themes->findRootThemes();
        $sidos = $regions->findByDepth(1);

        $matrix = [];
        foreach ($rootThemes as $theme) {
            $cells = [];
            foreach ($sidos as $sido) {
                $node = $nodes->findByCoordinates($theme, $sido);
                $cells[] = [
                    'sido' => $sido,
                    'state' => $node?->getStatus()->value ?? 'empty',
                    'count' => $node?->getDataCount() ?? 0,
                ];
            }
            $matrix[] = ['theme' => $theme, 'cells' => $cells];
        }

        return [
            'sidos' => $sidos,
            'matrix' => $matrix,
        ];
    }
}

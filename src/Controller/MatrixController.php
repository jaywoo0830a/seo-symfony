<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentNode;
use App\Entity\Enum\ContentStatus;
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
                    'state' => $this->cellState($node),
                    'count' => $node?->getDataCount() ?? 0,
                    'node' => $node,
                ];
            }
            $matrix[] = ['theme' => $theme, 'cells' => $cells];
        }

        return [
            'sidos' => $sidos,
            'matrix' => $matrix,
        ];
    }

    /**
     * Derives the cell state for the matrix view. 'demoted' is a synthetic
     * state that means "noindex but was once published" — operator should
     * see it as a regression, not a normal noindex.
     */
    private function cellState(?ContentNode $node): string
    {
        if ($node === null) {
            return 'empty';
        }
        if ($node->getStatus() === ContentStatus::Noindex && $node->getFirstPublishedAt() !== null) {
            return 'demoted';
        }

        return $node->getStatus()->value;
    }
}

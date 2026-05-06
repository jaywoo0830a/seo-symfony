<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Enum\ContentStatus;
use App\Repository\AuthorRepository;
use App\Repository\ContentNodeRepository;
use App\Repository\DataPointRepository;
use App\Repository\RegionRepository;
use App\Repository\ThemeRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
final class DashboardController extends AbstractController
{
    #[Template('dashboard/index.html.twig')]
    public function __invoke(
        ThemeRepository $themes,
        RegionRepository $regions,
        AuthorRepository $authors,
        ContentNodeRepository $nodes,
        DataPointRepository $dataPoints,
    ): array {
        return [
            'stats' => [
                'themes' => $themes->count([]),
                'regions' => $regions->count([]),
                'sidos' => count($regions->findByDepth(1)),
                'authors' => $authors->count([]),
                'authors_verified' => count($authors->findVerified()),
                'content_nodes' => $nodes->count([]),
                'content_nodes_live' => $nodes->count(['status' => ContentStatus::Live]),
                'data_points' => $dataPoints->count([]),
            ],
            'staleCount' => count($nodes->findStale(90)),
            'demotedCount' => $nodes->countDemoted(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContentNodeRepository;
use App\Repository\RegionRepository;
use App\Repository\ThemeRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/silo', name: 'admin_silo', methods: ['GET'])]
final class SiloController extends AbstractController
{
    #[Template('dashboard/silo.html.twig')]
    public function __invoke(
        ThemeRepository $themes,
        RegionRepository $regions,
        ContentNodeRepository $nodes,
    ): array {
        return [
            'checks' => [
                ['label' => '루트 테마 수', 'value' => count($themes->findRootThemes())],
                ['label' => '시도 수',       'value' => count($regions->findByDepth(1))],
                ['label' => '시군구 수',     'value' => count($regions->findByDepth(2))],
                ['label' => '동 수',         'value' => count($regions->findByDepth(3))],
                ['label' => '콘텐츠 노드',    'value' => $nodes->count([])],
            ],
        ];
    }
}

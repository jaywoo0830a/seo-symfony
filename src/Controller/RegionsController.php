<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\RegionRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/regions', name: 'admin_regions', methods: ['GET'])]
final class RegionsController extends AbstractController
{
    #[Template('dashboard/regions/index.html.twig')]
    public function __invoke(RegionRepository $regions): array
    {
        $rows = [];
        foreach ($regions->findByDepth(1) as $sido) {
            $rows[] = [
                'sido' => $sido,
                'sigunguCount' => $regions->count(['parent' => $sido]),
            ];
        }

        return ['rows' => $rows];
    }
}

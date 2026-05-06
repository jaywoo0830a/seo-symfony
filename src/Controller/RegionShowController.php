<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Region;
use App\Repository\ContentNodeRepository;
use App\Repository\RegionRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/regions/{id}', name: 'admin_regions_show', methods: ['GET'], requirements: ['id' => '\d+'])]
final class RegionShowController extends AbstractController
{
    #[Template('dashboard/regions/show.html.twig')]
    public function __invoke(
        #[MapEntity(id: 'id')] Region $region,
        RegionRepository $regions,
        ContentNodeRepository $nodes,
    ): array {
        return [
            'region' => $region,
            'children' => $regions->findBy(['parent' => $region], ['name' => 'ASC']),
            'nodeCount' => $nodes->count(['region' => $region]),
        ];
    }
}

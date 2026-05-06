<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Theme;
use App\Repository\ContentNodeRepository;
use App\Repository\ThemeRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/themes/{id}', name: 'admin_themes_show', methods: ['GET'], requirements: ['id' => '\d+'])]
final class ThemeShowController extends AbstractController
{
    #[Template('dashboard/themes/show.html.twig')]
    public function __invoke(
        #[MapEntity(id: 'id')] Theme $theme,
        ThemeRepository $themes,
        ContentNodeRepository $nodes,
    ): array {
        return [
            'theme' => $theme,
            'children' => $themes->findBy(['parent' => $theme], ['name' => 'ASC']),
            'nodeCount' => $nodes->count(['theme' => $theme]),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContentNodeRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/nodes', name: 'admin_nodes', methods: ['GET'])]
final class ContentNodeIndexController extends AbstractController
{
    #[Template('dashboard/nodes/index.html.twig')]
    public function __invoke(ContentNodeRepository $nodes): array
    {
        return [
            'nodes' => $nodes->findBy([], ['id' => 'DESC']),
        ];
    }
}

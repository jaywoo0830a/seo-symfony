<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContentNodeRepository;
use App\Util\Paginator;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/nodes', name: 'admin_nodes', methods: ['GET'])]
final class ContentNodeIndexController extends AbstractController
{
    #[Template('dashboard/nodes/index.html.twig')]
    public function __invoke(Request $request, ContentNodeRepository $nodes): array
    {
        return [
            'pager' => new Paginator(
                $nodes->indexQueryBuilder(),
                $request->query->getInt('page', 1),
            ),
        ];
    }
}

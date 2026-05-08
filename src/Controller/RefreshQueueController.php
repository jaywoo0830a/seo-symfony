<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContentNodeRepository;
use App\Util\Paginator;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/queue', name: 'admin_queue', methods: ['GET'])]
final class RefreshQueueController extends AbstractController
{
    #[Template('dashboard/queue.html.twig')]
    public function __invoke(Request $request, ContentNodeRepository $nodes): array
    {
        return [
            'demoted' => new Paginator(
                $nodes->demotedQueryBuilder(),
                $request->query->getInt('demoted_page', 1),
            ),
            'overdue' => new Paginator(
                $nodes->staleQueryBuilder(90),
                $request->query->getInt('overdue_page', 1),
            ),
            'soon' => new Paginator(
                $nodes->staleQueryBuilder(60),
                $request->query->getInt('soon_page', 1),
            ),
        ];
    }
}

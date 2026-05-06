<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContentNodeRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/queue', name: 'admin_queue', methods: ['GET'])]
final class RefreshQueueController extends AbstractController
{
    #[Template('dashboard/queue.html.twig')]
    public function __invoke(ContentNodeRepository $nodes): array
    {
        return [
            'demoted' => $nodes->findDemoted(),
            'overdue' => $nodes->findStale(90),
            'soon' => $nodes->findStale(60),
        ];
    }
}

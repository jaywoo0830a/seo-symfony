<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AuthorRepository;
use App\Repository\ContentNodeRepository;
use App\Repository\RegionRepository;
use App\Repository\ThemeRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/search', name: 'admin_search', methods: ['GET'])]
final class AdminSearchController extends AbstractController
{
    /**
     * @return array<string, mixed>
     */
    #[Template('dashboard/search.html.twig')]
    public function __invoke(
        Request $request,
        ThemeRepository $themes,
        RegionRepository $regions,
        AuthorRepository $authors,
        ContentNodeRepository $nodes,
    ): array {
        $query = trim((string) $request->query->get('q', ''));

        if ($query === '') {
            return [
                'query' => '',
                'themes' => [],
                'regions' => [],
                'authors' => [],
                'nodes' => [],
                'totalCount' => 0,
            ];
        }

        $themeHits = $themes->searchByText($query);
        $regionHits = $regions->searchByText($query);
        $authorHits = $authors->searchByText($query);
        $nodeHits = $nodes->searchByText($query);

        return [
            'query' => $query,
            'themes' => $themeHits,
            'regions' => $regionHits,
            'authors' => $authorHits,
            'nodes' => $nodeHits,
            'totalCount' => count($themeHits) + count($regionHits) + count($authorHits) + count($nodeHits),
        ];
    }
}

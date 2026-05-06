<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Repository\ContentNodeRepository;
use App\Service\UrlBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/sitemap.xml', name: 'public_sitemap', methods: ['GET'])]
final class SitemapController extends AbstractController
{
    public function __invoke(
        Request $request,
        ContentNodeRepository $nodes,
        UrlBuilder $urls,
    ): Response {
        $base = $request->getSchemeAndHttpHost();
        $entries = [];
        foreach ($nodes->findLiveForSitemap() as $node) {
            $entries[] = [
                'loc' => $base . $urls->build($node),
                'lastmod' => $node->getLastReviewAt() ?? $node->getFirstPublishedAt(),
                'priority' => $this->priority($node->getRegion()?->getDepth()),
                'changefreq' => $this->changefreq($node->getRegion()?->getDepth()),
            ];
        }

        $xml = $this->renderView('public/sitemap.xml.twig', ['entries' => $entries]);

        return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function priority(?int $regionDepth): string
    {
        return match ($regionDepth) {
            null => '1.0',
            1 => '0.9',
            2 => '0.7',
            3 => '0.5',
            default => '0.5',
        };
    }

    private function changefreq(?int $regionDepth): string
    {
        return $regionDepth === null || $regionDepth === 1 ? 'weekly' : 'monthly';
    }
}

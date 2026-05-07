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
        $latest = null;
        foreach ($nodes->findLiveForSitemap() as $node) {
            $lastmod = $node->getLastReviewAt() ?? $node->getFirstPublishedAt();
            if ($lastmod !== null && ($latest === null || $lastmod > $latest)) {
                $latest = $lastmod;
            }
            $entries[] = [
                'loc' => $base . $urls->build($node),
                'lastmod' => $lastmod,
                'priority' => $this->priority($node->getRegion()?->getDepth()),
                'changefreq' => $this->changefreq($node->getRegion()?->getDepth()),
            ];
        }

        $xml = $this->renderView('public/sitemap.xml.twig', ['entries' => $entries]);
        $response = new Response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);

        // 사이트맵은 더 길게 캐시 (브라우저 30분 / 공유 캐시 1시간).
        // 가장 최근 업데이트된 노드의 시간으로 Last-Modified 설정.
        $response->setPublic();
        $response->setMaxAge(1800);
        $response->setSharedMaxAge(3600);
        if ($latest !== null) {
            $response->setLastModified($latest);

            if ($response->isNotModified($request)) {
                return $response;
            }
        }

        return $response;
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

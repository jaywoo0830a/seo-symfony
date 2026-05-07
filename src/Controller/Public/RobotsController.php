<?php

declare(strict_types=1);

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/robots.txt', name: 'public_robots', methods: ['GET'])]
final class RobotsController extends AbstractController
{
    public function __invoke(
        Request $request,
        UrlGeneratorInterface $urls,
    ): Response {
        $sitemap = $urls->generate('public_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // 운영 환경에서만 검색엔진 허용. dev / staging에서는 색인 차단.
        $env = $this->getParameter('kernel.environment');
        $body = $env === 'prod'
            ? $this->productionBody($sitemap)
            : $this->disallowAllBody();

        return new Response($body, 200, [
            'Content-Type'  => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function productionBody(string $sitemap): string
    {
        return <<<TXT
            User-agent: *
            Allow: /
            Disallow: /admin/
            Disallow: /admin
            Disallow: /login

            Sitemap: {$sitemap}
            TXT;
    }

    private function disallowAllBody(): string
    {
        // dev / staging — 검색엔진 색인 완전 차단
        return <<<'TXT'
            User-agent: *
            Disallow: /
            TXT;
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\ContentNode;
use App\Entity\Enum\BodyTemplate;
use App\Entity\Enum\ContentStatus;
use App\Entity\Enum\DataPointKind;
use App\Repository\ContentNodeRepository;
use App\Repository\RedirectRepository;
use App\Service\BreadcrumbBuilder;
use App\Service\PathResolver;
use App\Service\UrlBuilder;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[Route(
    '/{path}',
    name: 'public_node',
    requirements: ['path' => '.+'],
    priority: -10,
    methods: ['GET'],
)]
final class PublicNodeController extends AbstractController
{
    public function __invoke(
        string $path,
        Request $request,
        PathResolver $resolver,
        ContentNodeRepository $nodes,
        RedirectRepository $redirects,
        UrlBuilder $urls,
        BreadcrumbBuilder $breadcrumbs,
        Environment $twig,
    ): Response {
        // Canonicalise to trailing slash.
        if (!str_ends_with($request->getPathInfo(), '/')) {
            return $this->redirect($request->getPathInfo() . '/', 301);
        }

        $node = $resolver->resolve($path);
        if ($node === null) {
            throw $this->createNotFoundException();
        }

        if ($node->getStatus() === ContentStatus::Dead) {
            $redirect = $redirects->findByFromNode($node);
            if ($redirect !== null) {
                return $this->redirect($urls->build($redirect->getToNode()), 301);
            }
            throw $this->createNotFoundException();
        }

        if ($node->getStatus() !== ContentStatus::Live) {
            throw $this->createNotFoundException();
        }

        // 캐시 헤더: 브라우저 5분 / 공유 캐시(CDN·리버스 프록시) 30분.
        // 같은 노드의 데이터가 변하면 Last-Modified가 갱신되므로
        // 클라이언트는 If-Modified-Since로 304 응답을 받게 됨.
        $response = new Response();
        $response->setPublic();
        $response->setMaxAge(300);
        $response->setSharedMaxAge(1800);
        $response->setLastModified($node->getLastReviewAt() ?? $node->getFirstPublishedAt());

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $this->render(
            'public/node.html.twig',
            $this->buildContext($node, $nodes, $urls, $breadcrumbs, $twig, $request->getSchemeAndHttpHost()),
            $response,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(
        ContentNode $node,
        ContentNodeRepository $nodes,
        UrlBuilder $urls,
        BreadcrumbBuilder $breadcrumbs,
        Environment $twig,
        string $absoluteBaseUrl,
    ): array {
        $byKind = [];
        foreach (DataPointKind::cases() as $kind) {
            $byKind[$kind->value] = [];
        }
        foreach ($node->getDataPoints() as $dp) {
            if ($dp->isVerified()) {
                $byKind[$dp->getKind()->value][] = $dp;
            }
        }

        $h1 = $this->buildH1($node);
        $crumbs = $breadcrumbs->build($node);
        $template = $this->deriveTemplate($node);

        return [
            'node' => $node,
            'url' => $urls->build($node),
            'h1' => $h1,
            'template' => $template,
            'body_html' => $template->isProse() ? $this->renderMarkdown($node->getBodyMarkdown()) : null,
            'template_override' => $this->findTemplateOverride($node, $twig),
            'matrix_template' => $this->findMatrixTemplate($node, $twig),
            'byKind' => $byKind,
            'parent' => $nodes->findParentOf($node),
            'siblings' => $this->findSiblings($node, $nodes),
            'children' => $nodes->findChildrenOf($node),
            'theme_children' => $nodes->findThemeChildrenOf($node),
            'theme_siblings' => $nodes->findThemeSiblingsOf($node),
            'urls' => $urls,
            'crumbs' => $crumbs,
            'json_ld' => $this->buildJsonLd($node, $h1),
            'breadcrumbs_jsonld' => $breadcrumbs->buildJsonLd($crumbs, $absoluteBaseUrl),
        ];
    }

    /**
     * Look for an operator-authored template override at:
     *   templates/public/hubs/{theme-path}.html.twig
     *
     * Where theme-path mirrors the URL theme segments — e.g.
     *   /tutoring/                       → hubs/tutoring.html.twig
     *   /guides/                         → hubs/guides.html.twig
     *   /guides/how-to-choose-tutor/     → hubs/guides/how-to-choose-tutor.html.twig
     *
     * Only applies to region=NULL nodes (theme hubs and individual guides).
     * Region pages (sido/sigungu/dong) intentionally never override — uniformity
     * across thousands of geo pages is a feature, not a bug.
     *
     * If found, the override wins over both the prose guide template and the
     * default matrix render. The operator created the file explicitly, so it
     * outranks any automatic dispatch.
     */
    /**
     * Pick the matrix template for this node based on its ROOT theme.
     *
     *   /tutoring/seoul/...  → matrix/tutoring.html.twig (if exists)
     *   /academy/busan/...   → matrix/academy.html.twig (if exists)
     *   anything else        → _matrix.html.twig (generic fallback)
     *
     * The root theme is the right axis: region pages within one vertical (tutoring)
     * stay uniform across regions for SEO, while different verticals (tutoring vs
     * academy) get distinct shells to avoid near-duplicate cross-theme pages.
     */
    private function findMatrixTemplate(ContentNode $node, Environment $twig): string
    {
        $rootTheme = $node->getTheme();
        while ($rootTheme->getParent() !== null) {
            $rootTheme = $rootTheme->getParent();
        }

        $candidate = sprintf('public/matrix/%s.html.twig', $rootTheme->getSlug());

        return $twig->getLoader()->exists($candidate) ? $candidate : 'public/_matrix.html.twig';
    }

    private function findTemplateOverride(ContentNode $node, Environment $twig): ?string
    {
        if ($node->getRegion() !== null) {
            return null;
        }

        $segments = [];
        for ($t = $node->getTheme(); $t !== null; $t = $t->getParent()) {
            array_unshift($segments, $t->getSlug());
        }

        $candidate = sprintf('public/hubs/%s.html.twig', implode('/', $segments));

        return $twig->getLoader()->exists($candidate) ? $candidate : null;
    }

    private function renderMarkdown(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        return (string) (new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]))->convert($markdown);
    }

    private function buildJsonLd(ContentNode $node, string $h1): string
    {
        $intro = trim(strip_tags((string) $node->getIntroText()));
        $description = mb_substr($intro, 0, 160);

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $h1,
        ];
        if ($description !== '') {
            $payload['description'] = $description;
        }
        if ($node->getAuthor() !== null) {
            $payload['author'] = [
                '@type' => 'Person',
                'name' => $node->getAuthor()->getRealName(),
            ];
        }
        if ($node->getFirstPublishedAt() !== null) {
            $payload['datePublished'] = $node->getFirstPublishedAt()->format('c');
        }
        if ($node->getLastReviewAt() !== null) {
            $payload['dateModified'] = $node->getLastReviewAt()->format('c');
        }

        return json_encode($payload, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    private function buildH1(ContentNode $node): string
    {
        $region = $node->getRegion();
        if ($region === null) {
            return $node->getTheme()->getName();
        }

        return sprintf('%s %s', $region->getName(), $node->getTheme()->getName());
    }

    private function deriveTemplate(ContentNode $node): BodyTemplate
    {
        return $node->getBodyTemplate() ?? BodyTemplate::Matrix;
    }

    /**
     * Finds live siblings (same theme, same region.parent) excluding self.
     *
     * @return list<ContentNode>
     */
    private function findSiblings(ContentNode $node, ContentNodeRepository $nodes): array
    {
        $parent = $nodes->findParentOf($node);
        if ($parent === null) {
            return [];
        }

        return array_values(array_filter(
            $nodes->findChildrenOf($parent),
            fn (ContentNode $sibling) => $sibling->getId() !== $node->getId()
                && $sibling->getStatus() === ContentStatus::Live,
        ));
    }
}

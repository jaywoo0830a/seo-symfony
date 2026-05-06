<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\ContentNode;
use App\Entity\Enum\BodyTemplate;
use App\Entity\Enum\ContentStatus;
use App\Entity\Enum\DataPointKind;
use App\Repository\ContentNodeRepository;
use App\Repository\RedirectRepository;
use App\Service\PathResolver;
use App\Service\UrlBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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

        return $this->render('public/node.html.twig', $this->buildContext($node, $nodes, $urls));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(ContentNode $node, ContentNodeRepository $nodes, UrlBuilder $urls): array
    {
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

        return [
            'node' => $node,
            'url' => $urls->build($node),
            'h1' => $h1,
            'template' => $this->deriveTemplate($node),
            'byKind' => $byKind,
            'parent' => $nodes->findParentOf($node),
            'siblings' => $this->findSiblings($node, $nodes),
            'children' => $nodes->findChildrenOf($node),
            'urls' => $urls,
            'json_ld' => $this->buildJsonLd($node, $h1),
        ];
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
        $regionDepth = $node->getRegion()?->getDepth();

        return match ($regionDepth) {
            null, 0 => BodyTemplate::Hub,
            1 => BodyTemplate::Sido,
            2 => BodyTemplate::Sigungu,
            3 => BodyTemplate::Dong,
            default => BodyTemplate::Hub,
        };
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

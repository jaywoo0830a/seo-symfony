<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContentNode;
use App\Entity\Region;
use App\Entity\Theme;

/**
 * 디자인 프리뷰 전용 NodeNavigator stub.
 *
 * NodeNavigator는 final이라 상속 불가 + 진짜는 ContentNodeRepository에
 * 의존해 DB 쿼리. 프리뷰는 메모리 객체만 다루므로 별도 stub으로 duck-typing.
 *
 * Twig 글로벌 `nav` 를 매 요청마다 이 인스턴스로 임시 override
 * (PreviewController::renderPreview).
 *
 * 노드 단일 시각 — 어떤 ContentNode가 들어오든 같은 fake 데이터를 돌려줌.
 * 한 프리뷰 페이지 = 한 PreviewNavigator 인스턴스.
 */
final class PreviewNavigator
{
    /**
     * @param array{region: list<ContentNode>, theme: list<ContentNode>} $childrenByAxis
     * @param array{region: list<ContentNode>, theme: list<ContentNode>} $siblingsByAxis
     * @param list<ContentNode> $ancestors
     * @param list<Theme>       $themeChain
     * @param list<Region>      $regionChain
     */
    public function __construct(
        private readonly array $childrenByAxis = ['region' => [], 'theme' => []],
        private readonly array $siblingsByAxis = ['region' => [], 'theme' => []],
        private readonly array $ancestors = [],
        private readonly ?ContentNode $parent = null,
        private readonly array $themeChain = [],
        private readonly array $regionChain = [],
    ) {}

    public function parent(ContentNode $node): ?ContentNode
    {
        return $this->parent;
    }

    /**
     * @return list<ContentNode>
     */
    public function children(ContentNode $node, string $axis = 'region'): array
    {
        return $this->childrenByAxis[$axis] ?? [];
    }

    /**
     * @return list<ContentNode>
     */
    public function siblings(ContentNode $node, string $axis = 'region'): array
    {
        return $this->siblingsByAxis[$axis] ?? [];
    }

    /**
     * @return list<ContentNode>
     */
    public function ancestors(ContentNode $node): array
    {
        return $this->ancestors;
    }

    /**
     * @return list<ContentNode>
     */
    public function uncles(ContentNode $node): array
    {
        return [];
    }

    public function countChildren(ContentNode $node, string $axis = 'region'): int
    {
        return \count($this->childrenByAxis[$axis] ?? []);
    }

    /**
     * @return list<Theme>
     */
    public function themeChain(ContentNode $node): array
    {
        return $this->themeChain;
    }

    /**
     * @return list<Region>
     */
    public function regionChain(ContentNode $node): array
    {
        return $this->regionChain;
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContentNode;
use App\Entity\Enum\ContentStatus;
use App\Entity\Region;
use App\Entity\Theme;
use App\Repository\ContentNodeRepository;

/**
 * 노드 좌표(theme, region) 주변의 계층 질의를 한 곳에 모은 헬퍼.
 *
 * Twig 글로벌 `nav`로 노출되어 템플릿이 직접 호출:
 *   {{ nav.parent(node) }}
 *   {{ nav.children(node) }}                    {# region 축 #}
 *   {{ nav.children(node, 'theme') }}           {# theme 축 #}
 *   {{ nav.siblings(node) }}
 *   {{ nav.ancestors(node) }}
 *   {{ nav.uncles(node) }}
 *   {{ nav.countChildren(node) }}
 *   {{ nav.themeChain(node) }}                  {# Theme 객체 root→leaf #}
 *   {{ nav.regionChain(node) }}                 {# Region 객체 시도→leaf #}
 *
 * region 축 walk은 fn_parent_live_check 트리거의 의미를 따라
 * region.depth=0 (전국 가상 루트)을 NULL로 collapse — 즉 시도(depth 1)의
 * 부모는 *테마 허브* (region=NULL).
 *
 * 요청 스코프 내에서 결과를 메모이즈하여 중복 쿼리를 회피.
 */
final class NodeNavigator
{
    public const AXIS_REGION = 'region';
    public const AXIS_THEME = 'theme';

    /** @var array<int, ContentNode|null> */
    private array $parentCache = [];
    /** @var array<string, list<ContentNode>> */
    private array $childrenCache = [];
    /** @var array<string, list<ContentNode>> */
    private array $siblingsCache = [];

    public function __construct(
        private readonly ContentNodeRepository $nodes,
    ) {}

    /**
     * 노드의 *직접* 부모 — 통합 계층(region 우선, region=NULL이면 theme).
     *
     *   region != NULL  →  같은 테마 + region.parent (depth=0은 NULL로 collapse)
     *   region == NULL  →  theme.parent의 허브 (region=NULL)
     *   루트            →  null
     *
     * 식별자가 없는(미flush/detached/orphan FK) 엔티티가 부모로 흘러들어오면
     * Doctrine 바인딩이 실패하므로 안전하게 null 반환.
     */
    public function parent(ContentNode $node): ?ContentNode
    {
        $key = (int) $node->getId();
        if (\array_key_exists($key, $this->parentCache)) {
            return $this->parentCache[$key];
        }

        $region = $node->getRegion();
        if ($region === null) {
            $themeParent = $node->getTheme()->getParent();
            if (!$this->isBindable($themeParent)) {
                return $this->parentCache[$key] = null;
            }
            return $this->parentCache[$key] = $this->nodes->findByCoordinates($themeParent, null);
        }

        $parentRegion = $region->getParent();
        if ($parentRegion !== null && $parentRegion->getDepth() === 0) {
            $parentRegion = null;
        }
        if ($parentRegion !== null && !$this->isBindable($parentRegion)) {
            return $this->parentCache[$key] = null;
        }

        return $this->parentCache[$key] = $this->nodes->findByCoordinates($node->getTheme(), $parentRegion);
    }

    /**
     * 직접 자식 노드들.
     *
     *   axis='region' (기본) — 같은 테마, region.parent=현재 노드 (또는 시도 row)
     *   axis='theme'         — theme.parent=현재 테마, 같은 region 좌표 (live만)
     *
     * @return list<ContentNode>
     */
    public function children(ContentNode $node, string $axis = self::AXIS_REGION): array
    {
        $key = $this->cacheKey($node, $axis);
        if (\array_key_exists($key, $this->childrenCache)) {
            return $this->childrenCache[$key];
        }

        // 두 repository 메서드 모두 $node->getTheme() 또는 $node->getRegion()를 바인딩.
        // 식별자 없는 엔티티는 [] fallback.
        if (!$this->isBindable($node->getTheme()) || !$this->isBindable($node->getRegion())) {
            return $this->childrenCache[$key] = [];
        }

        return $this->childrenCache[$key] = match ($axis) {
            self::AXIS_REGION => $this->nodes->findChildrenOf($node),
            self::AXIS_THEME => $this->nodes->findThemeChildrenOf($node),
            default => throw new \InvalidArgumentException(sprintf('Unknown axis "%s". Expected "region" or "theme".', $axis)),
        };
    }

    /**
     * 형제 노드들 (자기 자신 제외, live만).
     *
     *   axis='region' (기본) — parent()의 children 중 자기 제외
     *   axis='theme'         — 같은 theme.parent, 같은 region 좌표, 자기 제외
     *
     * @return list<ContentNode>
     */
    public function siblings(ContentNode $node, string $axis = self::AXIS_REGION): array
    {
        $key = $this->cacheKey($node, $axis);
        if (\array_key_exists($key, $this->siblingsCache)) {
            return $this->siblingsCache[$key];
        }

        if ($axis === self::AXIS_THEME) {
            // findThemeSiblingsOf가 theme.parent와 node.theme를 모두 쿼리 파라미터로 바인딩.
            // 식별자 없는 엔티티가 흘러들어오면 Doctrine 오류 → 안전하게 [] 반환.
            $themeParent = $node->getTheme()->getParent();
            if (!$this->isBindable($node->getTheme()) || !$this->isBindable($themeParent)) {
                return $this->siblingsCache[$key] = [];
            }
            return $this->siblingsCache[$key] = $this->nodes->findThemeSiblingsOf($node);
        }

        if ($axis !== self::AXIS_REGION) {
            throw new \InvalidArgumentException(sprintf('Unknown axis "%s". Expected "region" or "theme".', $axis));
        }

        $parent = $this->parent($node);
        if ($parent === null) {
            return $this->siblingsCache[$key] = [];
        }

        return $this->siblingsCache[$key] = array_values(array_filter(
            $this->children($parent, self::AXIS_REGION),
            fn (ContentNode $s) => $s->getId() !== $node->getId()
                && $s->getStatus() === ContentStatus::Live,
        ));
    }

    /**
     * 조상 체인 — 루트 → 직접 부모 (자기 자신 제외).
     *
     * parent()를 따라 재귀적으로 walk. 통합 계층 (region → theme).
     *
     * @return list<ContentNode>
     */
    public function ancestors(ContentNode $node): array
    {
        $chain = [];
        $current = $node;
        while (($p = $this->parent($current)) !== null) {
            array_unshift($chain, $p);
            $current = $p;
        }
        return $chain;
    }

    /**
     * 삼촌·이모 — 부모의 형제 (region 축).
     *
     * @return list<ContentNode>
     */
    public function uncles(ContentNode $node): array
    {
        $parent = $this->parent($node);
        if ($parent === null) {
            return [];
        }
        return $this->siblings($parent, self::AXIS_REGION);
    }

    /**
     * 자식 노드 중 live만 카운트.
     */
    public function countChildren(ContentNode $node, string $axis = self::AXIS_REGION): int
    {
        return \count(array_filter(
            $this->children($node, $axis),
            fn (ContentNode $c) => $c->getStatus() === ContentStatus::Live,
        ));
    }

    /**
     * Theme 트리 체인 — 루트 → 현재 (Theme 객체).
     *
     * DB 쿼리 없음 — Theme.parent를 따라 walk.
     *
     * @return list<Theme>
     */
    public function themeChain(ContentNode $node): array
    {
        $chain = [];
        for ($t = $node->getTheme(); $t !== null; $t = $t->getParent()) {
            array_unshift($chain, $t);
        }
        return $chain;
    }

    /**
     * Region 트리 체인 — 시도(depth 1) → 현재 (Region 객체).
     * 전국 가상 루트(depth 0)는 스킵.
     *
     * DB 쿼리 없음 — Region.parent를 따라 walk.
     *
     * @return list<Region>
     */
    public function regionChain(ContentNode $node): array
    {
        $chain = [];
        for ($r = $node->getRegion(); $r !== null && $r->getDepth() >= 1; $r = $r->getParent()) {
            array_unshift($chain, $r);
        }
        return $chain;
    }

    private function cacheKey(ContentNode $node, string $axis): string
    {
        return $node->getId() . ':' . $axis;
    }

    /**
     * Doctrine 바인딩 가능 여부. null은 OK (정상적 좌표값),
     * 엔티티는 식별자가 있어야 함 (detached/orphan 방어).
     */
    private function isBindable(?object $entity): bool
    {
        if ($entity === null) {
            return true;
        }
        return method_exists($entity, 'getId') && $entity->getId() !== null;
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContentNode;
use App\Entity\Region;
use App\Entity\Theme;
use App\Repository\ContentNodeRepository;
use App\Repository\RegionRepository;
use App\Repository\ThemeRepository;

/**
 * Resolves a public URL path back to its ContentNode coordinate.
 *
 * URL is the inverse of UrlBuilder:
 *   /{theme-slug}/                          → (theme, region=null)
 *   /{theme-slug}/{child-theme}/            → (child-theme, region=null)   — guides
 *   /{theme-slug}/{sido}/                   → (theme, sido)
 *   /{theme-slug}/{sido}/{sigungu}/         → (theme, sigungu)
 *   /{theme-slug}/{sido}/{sigungu}/{dong}/  → (theme, dong)
 *
 * Theme descent is greedy: keep matching child-theme slugs until one fails, then
 * the remainder is the region path. Theme slugs and sido slugs don't collide in
 * practice (sido = "seoul", child theme = "how-to-choose-tutor").
 *
 * Status filtering is the caller's job — this resolver just locates the row.
 */
final class PathResolver
{
    public function __construct(
        private readonly ThemeRepository $themes,
        private readonly RegionRepository $regions,
        private readonly ContentNodeRepository $nodes,
    ) {}

    public function resolve(string $path): ?ContentNode
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if ($segments === []) {
            return null;
        }

        $theme = $this->themes->findOneBy(['slug' => $segments[0], 'parent' => null]);
        if ($theme === null) {
            return null;
        }

        // Greedy theme descent: while the next segment matches a child of the current
        // theme, keep walking. Once it doesn't, the remainder is region path.
        $consumed = 1;
        for ($n = count($segments); $consumed < $n; $consumed++) {
            $child = $this->themes->findOneBy(['slug' => $segments[$consumed], 'parent' => $theme]);
            if ($child === null) {
                break;
            }
            $theme = $child;
        }

        $regionSegments = array_slice($segments, $consumed);
        $region = $this->walkRegionTree($regionSegments);
        if (count($regionSegments) > 0 && $region === null) {
            return null;
        }

        return $this->nodes->findByCoordinates($theme, $region);
    }

    /**
     * @param list<string> $segments
     */
    private function walkRegionTree(array $segments): ?Region
    {
        if ($segments === []) {
            return null;
        }

        $region = $this->regions->findOneBy(['slug' => $segments[0], 'depth' => 1]);
        for ($i = 1, $n = count($segments); $i < $n; $i++) {
            if ($region === null) {
                return null;
            }
            $region = $this->regions->findOneBy(['slug' => $segments[$i], 'parent' => $region]);
        }

        return $region;
    }
}

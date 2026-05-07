<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContentNode;
use App\Entity\Region;
use App\Entity\Theme;

/**
 * Derives the public URL for a ContentNode from its coordinates.
 *
 * URL is a function of (theme, region) — never hand-written.
 *   url(node) = "/" + theme_path(node.theme) + region_path(node.region) + "/"
 *   theme_path  : root → t, slugs joined by "/"
 *   region_path : sido → r, slugs joined by "/" (country root is dropped)
 */
final class UrlBuilder
{
    public function build(ContentNode $node): string
    {
        return $this->buildPath($node->getTheme(), $node->getRegion());
    }

    /**
     * 좌표(테마 + 지역)만으로 URL 생성. ContentNode가 없는 중간 좌표
     * (예: breadcrumb 항목들)에도 URL을 만들 수 있음.
     */
    public function buildPath(Theme $theme, ?Region $region): string
    {
        $themePath = $this->themePath($theme);
        $regionPath = $region ? $this->regionPath($region) : '';

        return '/' . $themePath . ($regionPath !== '' ? '/' . $regionPath : '') . '/';
    }

    private function themePath(Theme $theme): string
    {
        $segments = [];
        for ($t = $theme; $t !== null; $t = $t->getParent()) {
            array_unshift($segments, $t->getSlug());
        }

        return implode('/', $segments);
    }

    private function regionPath(Region $region): string
    {
        $segments = [];
        for ($r = $region; $r !== null && $r->getDepth() >= 1; $r = $r->getParent()) {
            array_unshift($segments, $r->getSlug());
        }

        return implode('/', $segments);
    }
}

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
        $themePath = $this->themePath($node->getTheme());
        $regionPath = $node->getRegion() ? $this->regionPath($node->getRegion()) : '';

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

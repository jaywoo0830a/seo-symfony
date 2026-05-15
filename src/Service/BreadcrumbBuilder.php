<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContentNode;
use App\Entity\Region;

/**
 * 노드 좌표로부터 빵부스러기 경로를 도출한다.
 *
 *   /                                                    → [전국]
 *   /tutoring/                                           → [전국, 과외]
 *   /tutoring/seoul/                                     → [전국, 과외, 서울특별시]
 *   /tutoring/seoul/gangnam-gu/                          → [전국, 과외, 서울특별시, 강남구]
 *   /elementary-tutoring/essays/policy-28/               → [전국, 초등 과외, 에세이, …]
 *
 * 테마는 root→leaf 체인을 모두 펼치고, 지역은 시도부터 leaf까지.
 * 시각용 배열과 Schema.org BreadcrumbList JSON-LD를 모두 생성.
 */
final class BreadcrumbBuilder
{
    public function __construct(
        private readonly UrlBuilder $urls,
    ) {}

    /**
     * @return list<array{label: string, url: string, current: bool}>
     */
    public function build(ContentNode $node): array
    {
        $theme = $node->getTheme();

        $crumbs = [
            ['label' => '전국', 'url' => '/', 'current' => false],
        ];

        // 테마 체인을 root→leaf 순으로 (각 단계가 자체 hub URL을 가짐)
        $themeChain = [];
        for ($t = $theme; $t !== null; $t = $t->getParent()) {
            array_unshift($themeChain, $t);
        }
        foreach ($themeChain as $t) {
            $crumbs[] = [
                'label' => $t->getName(),
                'url' => $this->urls->buildPath($t, null),
                'current' => false,
            ];
        }

        // 지역 체인을 root→leaf 순으로 (country root는 스킵: depth >= 1만)
        $regionChain = [];
        for ($r = $node->getRegion(); $r !== null && $r->getDepth() >= 1; $r = $r->getParent()) {
            array_unshift($regionChain, $r);
        }
        foreach ($regionChain as $region) {
            $crumbs[] = [
                'label' => $region->getName(),
                'url' => $this->urls->buildPath($theme, $region),
                'current' => false,
            ];
        }

        // 마지막 항목이 현재 페이지
        $last = count($crumbs) - 1;
        $crumbs[$last]['current'] = true;

        return $crumbs;
    }

    /**
     * Schema.org BreadcrumbList JSON-LD 생성. 마지막 항목은 item을 생략
     * (Google 가이드: 현재 페이지는 item 없음).
     *
     * @param list<array{label: string, url: string, current: bool}> $breadcrumbs
     */
    public function buildJsonLd(array $breadcrumbs, string $absoluteBaseUrl): string
    {
        $items = [];
        foreach ($breadcrumbs as $i => $crumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['label'],
            ];
            if (!$crumb['current']) {
                $item['item'] = $absoluteBaseUrl . $crumb['url'];
            }
            $items[] = $item;
        }

        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}

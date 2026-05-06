<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentNode;
use App\Entity\Enum\ContentStatus;
use App\Entity\Region;
use App\Entity\Theme;
use App\Repository\ContentNodeRepository;
use App\Repository\RegionRepository;
use App\Repository\ThemeRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/admin/matrix/{path}',
    name: 'admin_matrix',
    requirements: ['path' => '.*'],
    defaults: ['path' => ''],
    methods: ['GET'],
)]
final class MatrixController extends AbstractController
{
    #[Template('dashboard/matrix.html.twig')]
    public function __invoke(
        string $path,
        ThemeRepository $themes,
        RegionRepository $regions,
        ContentNodeRepository $nodes,
    ): array {
        $rootThemes = $themes->findRootThemes();
        [$columns, $level, $parentRegion] = $this->resolveSlice($path, $regions);

        return [
            'level' => $level,
            'parent' => $parentRegion,
            'crumbs' => $this->buildCrumbs($parentRegion),
            'columns' => $columns,
            'matrix' => $this->buildMatrix($rootThemes, $columns, $nodes),
            'coverage' => $level === 'sido'
                ? $this->buildCoverage($rootThemes, $regions, $nodes)
                : null,
        ];
    }

    /**
     * Parses the path and returns [columns, level, parentRegion].
     * - ""             → sidos (top), parent=null
     * - "seoul"        → sigungu of seoul, parent=seoul
     * - "seoul/gangnam-gu" → dongs of gangnam-gu, parent=gangnam-gu
     *
     * @return array{0: list<Region>, 1: string, 2: ?Region}
     */
    private function resolveSlice(string $path, RegionRepository $regions): array
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if ($segments === []) {
            return [$regions->findByDepth(1), 'sido', null];
        }

        $sido = $regions->findOneBy(['slug' => $segments[0], 'depth' => 1]);
        if ($sido === null) {
            throw new NotFoundHttpException(sprintf('시도 "%s"를 찾을 수 없습니다.', $segments[0]));
        }

        if (count($segments) === 1) {
            $children = $regions->findBy(['parent' => $sido], ['name' => 'ASC']);
            return [$children, 'sigungu', $sido];
        }

        $sigungu = $regions->findOneBy(['slug' => $segments[1], 'parent' => $sido]);
        if ($sigungu === null) {
            throw new NotFoundHttpException(sprintf('시군구 "%s"를 찾을 수 없습니다.', $segments[1]));
        }

        if (count($segments) === 2) {
            $children = $regions->findBy(['parent' => $sigungu], ['name' => 'ASC']);
            return [$children, 'dong', $sigungu];
        }

        throw new NotFoundHttpException('매트릭스는 동(depth 3)까지만 드릴다운할 수 있습니다.');
    }

    /**
     * @param list<Theme> $themes
     * @param list<Region> $columns
     * @return list<array{theme: Theme, cells: list<array{region: Region, state: string, count: int, node: ?ContentNode}>}>
     */
    private function buildMatrix(array $themes, array $columns, ContentNodeRepository $nodes): array
    {
        $matrix = [];
        foreach ($themes as $theme) {
            $cells = [];
            foreach ($columns as $region) {
                $node = $nodes->findByCoordinates($theme, $region);
                $cells[] = [
                    'region' => $region,
                    'state' => $this->cellState($node),
                    'count' => $node?->getDataCount() ?? 0,
                    'node' => $node,
                ];
            }
            $matrix[] = ['theme' => $theme, 'cells' => $cells];
        }

        return $matrix;
    }

    /**
     * Synthetic 'demoted' state when noindex AND first_published_at is set.
     */
    private function cellState(?ContentNode $node): string
    {
        if ($node === null) {
            return 'empty';
        }
        if ($node->getStatus() === ContentStatus::Noindex && $node->getFirstPublishedAt() !== null) {
            return 'demoted';
        }

        return $node->getStatus()->value;
    }

    /**
     * Breadcrumb for back navigation. Returns list of {label, path} where path
     * is the {path} URL parameter, suitable for path('admin_matrix', {path: ...}).
     *
     * @return list<array{label: string, path: string}>
     */
    private function buildCrumbs(?Region $current): array
    {
        if ($current === null) {
            return [];
        }

        $crumbs = [['label' => '전국', 'path' => '']];
        if ($current->getDepth() === 2) {
            $crumbs[] = ['label' => $current->getParent()->getName(), 'path' => $current->getParent()->getSlug()];
        }

        return $crumbs;
    }

    /**
     * Per-theme coverage at each region tier (sido / sigungu / dong).
     * Shown only on the top page for orientation.
     *
     * @param list<Theme> $themes
     * @return list<array{theme: Theme, sido: array{live: int, total: int}, sigungu: array{live: int, total: int}, dong: array{live: int, total: int}}>
     */
    private function buildCoverage(array $themes, RegionRepository $regions, ContentNodeRepository $nodes): array
    {
        $totals = [
            1 => $regions->count(['depth' => 1]),
            2 => $regions->count(['depth' => 2]),
            3 => $regions->count(['depth' => 3]),
        ];

        $coverage = [];
        foreach ($themes as $theme) {
            $coverage[] = [
                'theme' => $theme,
                'sido' => [
                    'live' => $nodes->countLiveByThemeAndRegionDepth($theme, 1),
                    'total' => $totals[1],
                ],
                'sigungu' => [
                    'live' => $nodes->countLiveByThemeAndRegionDepth($theme, 2),
                    'total' => $totals[2],
                ],
                'dong' => [
                    'live' => $nodes->countLiveByThemeAndRegionDepth($theme, 3),
                    'total' => $totals[3],
                ],
            ];
        }

        return $coverage;
    }
}

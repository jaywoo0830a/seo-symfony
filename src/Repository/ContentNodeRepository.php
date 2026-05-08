<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContentNode;
use App\Entity\Enum\ContentStatus;
use App\Entity\Region;
use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentNode>
 */
class ContentNodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentNode::class);
    }

    public function findByCoordinates(Theme $theme, ?Region $region): ?ContentNode
    {
        return $this->findOneBy(['theme' => $theme, 'region' => $region]);
    }

    /**
     * Locates the parent node by walking one step toward the region tree root.
     * Returns null for theme hubs (region IS NULL); they have no parent.
     */
    public function findParentOf(ContentNode $node): ?ContentNode
    {
        $region = $node->getRegion();
        if ($region === null) {
            return null;
        }

        return $this->findOneBy([
            'theme' => $node->getTheme(),
            'region' => $region->getParent(),
        ]);
    }

    /**
     * @return list<ContentNode>
     */
    public function findChildrenOf(ContentNode $node): array
    {
        $qb = $this->createQueryBuilder('n')
            ->innerJoin('n.region', 'r')
            ->where('n.theme = :theme')
            ->setParameter('theme', $node->getTheme())
            ->orderBy('r.depth', 'ASC')
            ->addOrderBy('r.name', 'ASC');

        if ($node->getRegion() === null) {
            $qb->andWhere('r.depth = 1');
        } else {
            $qb->andWhere('r.parent = :parentRegion')
                ->setParameter('parentRegion', $node->getRegion());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Theme-tree children: nodes whose theme is a direct child of this node's theme,
     * sharing the same region coordinate. Used for "navigate to sub-categories"
     * (e.g. /guides/ → list of individual guides).
     *
     * @return list<ContentNode>
     */
    public function findThemeChildrenOf(ContentNode $node): array
    {
        $qb = $this->createQueryBuilder('n')
            ->innerJoin('n.theme', 't')
            ->addSelect('t')
            ->where('t.parent = :parentTheme')
            ->andWhere('n.status = :liveStatus')
            ->setParameter('parentTheme', $node->getTheme())
            ->setParameter('liveStatus', ContentStatus::Live)
            ->orderBy('t.name', 'ASC');

        if ($node->getRegion() === null) {
            $qb->andWhere('n.region IS NULL');
        } else {
            $qb->andWhere('n.region = :region')
                ->setParameter('region', $node->getRegion());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Theme-tree siblings: nodes whose theme is a sibling of this node's theme
     * (same theme parent), same region coordinate, excluding self. Used for
     * "other pages in this category" (e.g. one guide → other guides).
     *
     * @return list<ContentNode>
     */
    public function findThemeSiblingsOf(ContentNode $node): array
    {
        $themeParent = $node->getTheme()->getParent();
        if ($themeParent === null) {
            return [];
        }

        $qb = $this->createQueryBuilder('n')
            ->innerJoin('n.theme', 't')
            ->addSelect('t')
            ->where('t.parent = :parentTheme')
            ->andWhere('t != :selfTheme')
            ->andWhere('n.status = :liveStatus')
            ->setParameter('parentTheme', $themeParent)
            ->setParameter('selfTheme', $node->getTheme())
            ->setParameter('liveStatus', ContentStatus::Live)
            ->orderBy('t.name', 'ASC');

        if ($node->getRegion() === null) {
            $qb->andWhere('n.region IS NULL');
        } else {
            $qb->andWhere('n.region = :region')
                ->setParameter('region', $node->getRegion());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Live nodes for sitemap.xml generation.
     *
     * @return list<ContentNode>
     */
    public function findLiveForSitemap(): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.region', 'r')
            ->addSelect('r')
            ->where('n.status = :status')
            ->setParameter('status', ContentStatus::Live)
            ->orderBy('n.theme', 'ASC')
            ->addOrderBy('r.depth', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function indexQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('n')
            ->orderBy('n.id', 'DESC');
    }

    public function staleQueryBuilder(int $daysSinceReview = 90): QueryBuilder
    {
        $cutoff = new \DateTimeImmutable(sprintf('-%d days', $daysSinceReview));

        return $this->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.lastReviewAt IS NULL OR n.lastReviewAt < :cutoff')
            ->setParameter('status', ContentStatus::Live)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('n.lastReviewAt', 'ASC');
    }

    public function demotedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.firstPublishedAt IS NOT NULL')
            ->setParameter('status', ContentStatus::Noindex)
            ->orderBy('n.firstPublishedAt', 'DESC');
    }

    /**
     * Nodes due for refresh queue.
     *
     * @return list<ContentNode>
     */
    public function findStale(int $daysSinceReview = 90): array
    {
        return $this->staleQueryBuilder($daysSinceReview)->getQuery()->getResult();
    }

    /**
     * Nodes that were once published but auto-degraded to noindex
     * (data_count fell below 5). first_published_at is set by the publish
     * gate on entry to 'live' and never cleared, so the pair is the
     * marker for "previously live".
     *
     * @return list<ContentNode>
     */
    public function findDemoted(): array
    {
        return $this->demotedQueryBuilder()->getQuery()->getResult();
    }

    public function countDemoted(): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.status = :status')
            ->andWhere('n.firstPublishedAt IS NOT NULL')
            ->setParameter('status', ContentStatus::Noindex)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count of live nodes for one theme at a specific region depth.
     * Used by the matrix coverage summary (e.g. "tutoring · sido 12 / 17").
     */
    public function countLiveByThemeAndRegionDepth(\App\Entity\Theme $theme, int $regionDepth): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->innerJoin('n.region', 'r')
            ->where('n.theme = :theme')
            ->andWhere('n.status = :status')
            ->andWhere('r.depth = :depth')
            ->setParameter('theme', $theme)
            ->setParameter('status', ContentStatus::Live)
            ->setParameter('depth', $regionDepth)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Searches across joined theme name, region name, and node intro_text.
     *
     * @return list<ContentNode>
     */
    public function searchByText(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.theme', 't')
            ->leftJoin('n.region', 'r')
            ->addSelect('t', 'r')
            ->where('LOWER(t.name) LIKE LOWER(:q)')
            ->orWhere('LOWER(r.name) LIKE LOWER(:q)')
            ->orWhere('LOWER(n.introText) LIKE LOWER(:q)')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('n.theme', 'ASC')
            ->addOrderBy('r.depth', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

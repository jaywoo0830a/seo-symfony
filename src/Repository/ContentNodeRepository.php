<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContentNode;
use App\Entity\Enum\ContentStatus;
use App\Entity\Region;
use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    /**
     * Nodes due for refresh queue.
     *
     * @return list<ContentNode>
     */
    public function findStale(int $daysSinceReview = 90): array
    {
        $cutoff = new \DateTimeImmutable(sprintf('-%d days', $daysSinceReview));

        return $this->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.lastReviewAt IS NULL OR n.lastReviewAt < :cutoff')
            ->setParameter('status', ContentStatus::Live)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('n.lastReviewAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

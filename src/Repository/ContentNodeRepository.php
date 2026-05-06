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
     * Live nodes for sitemap.xml generation, ordered by depth.
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
            ->addOrderBy('COALESCE(r.depth, 0)', 'ASC')
            ->addOrderBy('n.theme', 'ASC')
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

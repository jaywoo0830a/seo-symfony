<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContentNode;
use App\Entity\DataPoint;
use App\Entity\Enum\DataPointKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DataPoint>
 */
class DataPointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DataPoint::class);
    }

    public function countVerified(ContentNode $node, ?DataPointKind $kind = null): int
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.node = :node')
            ->andWhere('d.verified = true')
            ->setParameter('node', $node);

        if ($kind !== null) {
            $qb->andWhere('d.kind = :kind')->setParameter('kind', $kind);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}

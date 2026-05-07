<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Region;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Region>
 */
class RegionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Region::class);
    }

    public function findBySlug(string $slug, ?Region $parent = null): ?Region
    {
        return $this->findOneBy(['slug' => $slug, 'parent' => $parent]);
    }

    public function findByAdminCode(string $adminCode): ?Region
    {
        return $this->findOneBy(['adminCode' => $adminCode]);
    }

    /**
     * @return list<Region>
     */
    public function findByDepth(int $depth): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.depth = :depth')
            ->setParameter('depth', $depth)
            ->orderBy('r.slug', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Region>
     */
    public function searchByText(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->where('LOWER(r.name) LIKE LOWER(:q) OR LOWER(r.slug) LIKE LOWER(:q) OR r.adminCode LIKE :q')
            ->andWhere('r.depth >= 1')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('r.depth', 'ASC')
            ->addOrderBy('r.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

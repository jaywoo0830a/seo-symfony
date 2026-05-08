<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Author;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Author>
 */
class AuthorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Author::class);
    }

    public function findBySlug(string $slug): ?Author
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function indexQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.realName', 'ASC');
    }

    /**
     * @return list<Author>
     */
    public function findVerified(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.verifiedAt IS NOT NULL')
            ->orderBy('a.realName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Author>
     */
    public function searchByText(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->where('LOWER(a.realName) LIKE LOWER(:q) OR LOWER(a.slug) LIKE LOWER(:q)')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('a.realName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

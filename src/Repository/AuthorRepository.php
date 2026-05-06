<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Author;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}

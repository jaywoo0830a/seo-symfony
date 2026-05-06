<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Theme>
 */
class ThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Theme::class);
    }

    public function findBySlug(string $slug, ?Theme $parent = null): ?Theme
    {
        return $this->findOneBy(['slug' => $slug, 'parent' => $parent]);
    }

    /**
     * @return list<Theme>
     */
    public function findRootThemes(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.parent IS NULL')
            ->orderBy('t.slug', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdminUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminUser>
 */
class AdminUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminUser::class);
    }

    public function findByEmail(string $email): ?AdminUser
    {
        return $this->findOneBy(['email' => $email]);
    }
}

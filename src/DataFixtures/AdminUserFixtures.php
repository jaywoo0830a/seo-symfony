<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AdminUser;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Dev-only seed: a single admin@local / admin1234 account.
 * Production must use `app:admin:create` instead.
 */
final class AdminUserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $admin = (new AdminUser())
            ->setEmail('admin@local')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin1234'));

        $manager->persist($admin);
        $manager->flush();
    }
}

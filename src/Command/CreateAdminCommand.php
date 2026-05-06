<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AdminUser;
use App\Repository\AdminUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:admin:create',
    description: '관리자 계정을 생성합니다.',
)]
final class CreateAdminCommand
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUserRepository $admins,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('관리자 이메일')]
        string $email,
        #[Argument('초기 비밀번호 (최소 8자)')]
        string $password,
        #[Option(name: 'force', description: '같은 이메일이 있으면 비밀번호를 갱신')]
        bool $force = false,
    ): int {
        if (strlen($password) < 8) {
            $io->error('비밀번호는 8자 이상이어야 합니다.');
            return Command::FAILURE;
        }

        $existing = $this->admins->findByEmail($email);
        if ($existing !== null && !$force) {
            $io->error("이미 존재하는 이메일입니다: $email (--force로 비밀번호를 갱신할 수 있습니다)");
            return Command::FAILURE;
        }

        $user = $existing ?? (new AdminUser())->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        if ($existing === null) {
            $this->em->persist($user);
        }
        $this->em->flush();

        $io->success(sprintf(
            '%s: %s (ROLE_ADMIN)',
            $existing ? '비밀번호 갱신' : '관리자 생성',
            $email,
        ));
        return Command::SUCCESS;
    }
}

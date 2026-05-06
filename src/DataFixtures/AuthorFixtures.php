<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Author;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Phase 0 author seeds for E-E-A-T signals.
 *
 * Authors must be `verified_at != NULL` for ContentNodes to pass the publish gate.
 */
class AuthorFixtures extends Fixture
{
    public const EDITOR_REF = 'author-editor';

    public function load(ObjectManager $manager): void
    {
        $editor = (new Author())
            ->setSlug('editor')
            ->setRealName('편집팀')
            ->setCredentials('교육 콘텐츠 편집 5년차. 전국 시도교육청 자료 큐레이션.')
            ->setBio('지역 교육 데이터를 검증하고 정리하는 편집팀 공식 계정입니다.')
            ->setVerifiedAt(new \DateTimeImmutable('2026-01-01 00:00:00'));
        $manager->persist($editor);
        $this->addReference(self::EDITOR_REF, $editor);

        $unverified = (new Author())
            ->setSlug('contributor-pending')
            ->setRealName('외부 기고자(미인증)')
            ->setCredentials('가입 후 자격 증빙 대기 중')
            ->setBio('기고 자격 검증 절차 진행 중입니다.');
        $manager->persist($unverified);

        $manager->flush();
    }
}

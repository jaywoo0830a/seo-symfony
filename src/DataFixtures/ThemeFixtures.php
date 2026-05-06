<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Theme;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ThemeFixtures extends Fixture
{
    public const TUTORING_REF = 'theme-tutoring';
    public const ACADEMY_REF = 'theme-academy';
    public const CONTENTS_REF = 'theme-contents';
    public const GUIDES_REF = 'theme-guides';

    /**
     * @var list<array{slug: string, name: string, description: string, ref: string}>
     */
    private const PRIMARY_THEMES = [
        [
            'slug' => 'tutoring',
            'name' => '과외',
            'description' => '지역별 1:1 과외 매칭과 개별 학습 코칭에 특화된 테마.',
            'ref' => self::TUTORING_REF,
        ],
        [
            'slug' => 'academy',
            'name' => '학원',
            'description' => '오프라인 학원의 지역별 정보와 평가에 집중한 테마.',
            'ref' => self::ACADEMY_REF,
        ],
        [
            'slug' => 'contents',
            'name' => '학습 콘텐츠',
            'description' => '과목·학년별 학습 자료와 인터랙티브 콘텐츠 테마.',
            'ref' => self::CONTENTS_REF,
        ],
        [
            'slug' => 'guides',
            'name' => '가이드',
            'description' => '지역에 종속되지 않는 정보형 가이드(FAQ, 비교, 노하우) 테마.',
            'ref' => self::GUIDES_REF,
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::PRIMARY_THEMES as $entry) {
            $theme = new Theme();
            $theme->setSlug($entry['slug'])
                ->setName($entry['name'])
                ->setDepth(0)
                ->setDescription($entry['description']);

            $manager->persist($theme);
            $this->addReference($entry['ref'], $theme);
        }

        $manager->flush();
    }
}

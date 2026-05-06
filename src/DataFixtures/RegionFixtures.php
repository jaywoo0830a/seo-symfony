<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Region;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Phase 0 region tree.
 *
 * Loads:
 * - 1 country root (대한민국, depth=0)
 * - 17 sido (depth=1) — full set
 * - 25 Seoul sigungu (depth=2) — full set, since 수도권 is Phase 2~3 priority
 * - 6 sample dongs in Gangnam-gu (depth=3) — strategy doc explicitly mentions 대치/역삼/압구정
 *
 * Other sigungu (~203) and dong (~3,500) should be loaded from the official admin
 * code dataset via a one-off importer command, not via fixtures.
 */
final class RegionFixtures extends Fixture
{
    public const KR_REF = 'region-kr';
    public const SEOUL_REF = 'region-seoul';
    public const GANGNAM_GU_REF = 'region-seoul-gangnam-gu';

    /**
     * @var list<array{slug: string, name: string, code: string}>
     */
    private const SIDO = [
        ['slug' => 'seoul',     'name' => '서울특별시',       'code' => '11'],
        ['slug' => 'busan',     'name' => '부산광역시',       'code' => '26'],
        ['slug' => 'daegu',     'name' => '대구광역시',       'code' => '27'],
        ['slug' => 'incheon',   'name' => '인천광역시',       'code' => '28'],
        ['slug' => 'gwangju',   'name' => '광주광역시',       'code' => '29'],
        ['slug' => 'daejeon',   'name' => '대전광역시',       'code' => '30'],
        ['slug' => 'ulsan',     'name' => '울산광역시',       'code' => '31'],
        ['slug' => 'sejong',    'name' => '세종특별자치시',    'code' => '36'],
        ['slug' => 'gyeonggi',  'name' => '경기도',          'code' => '41'],
        ['slug' => 'gangwon',   'name' => '강원특별자치도',    'code' => '51'],
        ['slug' => 'chungbuk',  'name' => '충청북도',        'code' => '43'],
        ['slug' => 'chungnam',  'name' => '충청남도',        'code' => '44'],
        ['slug' => 'jeonbuk',   'name' => '전북특별자치도',    'code' => '52'],
        ['slug' => 'jeonnam',   'name' => '전라남도',        'code' => '46'],
        ['slug' => 'gyeongbuk', 'name' => '경상북도',        'code' => '47'],
        ['slug' => 'gyeongnam', 'name' => '경상남도',        'code' => '48'],
        ['slug' => 'jeju',      'name' => '제주특별자치도',    'code' => '50'],
    ];

    /**
     * @var list<array{slug: string, name: string, code: string}>
     */
    private const SEOUL_SIGUNGU = [
        ['slug' => 'jongno-gu',       'name' => '종로구',     'code' => '11110'],
        ['slug' => 'jung-gu',         'name' => '중구',       'code' => '11140'],
        ['slug' => 'yongsan-gu',      'name' => '용산구',     'code' => '11170'],
        ['slug' => 'seongdong-gu',    'name' => '성동구',     'code' => '11200'],
        ['slug' => 'gwangjin-gu',     'name' => '광진구',     'code' => '11215'],
        ['slug' => 'dongdaemun-gu',   'name' => '동대문구',    'code' => '11230'],
        ['slug' => 'jungnang-gu',     'name' => '중랑구',     'code' => '11260'],
        ['slug' => 'seongbuk-gu',     'name' => '성북구',     'code' => '11290'],
        ['slug' => 'gangbuk-gu',      'name' => '강북구',     'code' => '11305'],
        ['slug' => 'dobong-gu',       'name' => '도봉구',     'code' => '11320'],
        ['slug' => 'nowon-gu',        'name' => '노원구',     'code' => '11350'],
        ['slug' => 'eunpyeong-gu',    'name' => '은평구',     'code' => '11380'],
        ['slug' => 'seodaemun-gu',    'name' => '서대문구',    'code' => '11410'],
        ['slug' => 'mapo-gu',         'name' => '마포구',     'code' => '11440'],
        ['slug' => 'yangcheon-gu',    'name' => '양천구',     'code' => '11470'],
        ['slug' => 'gangseo-gu',      'name' => '강서구',     'code' => '11500'],
        ['slug' => 'guro-gu',         'name' => '구로구',     'code' => '11530'],
        ['slug' => 'geumcheon-gu',    'name' => '금천구',     'code' => '11545'],
        ['slug' => 'yeongdeungpo-gu', 'name' => '영등포구',    'code' => '11560'],
        ['slug' => 'dongjak-gu',      'name' => '동작구',     'code' => '11590'],
        ['slug' => 'gwanak-gu',       'name' => '관악구',     'code' => '11620'],
        ['slug' => 'seocho-gu',       'name' => '서초구',     'code' => '11650'],
        ['slug' => 'gangnam-gu',      'name' => '강남구',     'code' => '11680'],
        ['slug' => 'songpa-gu',       'name' => '송파구',     'code' => '11710'],
        ['slug' => 'gangdong-gu',     'name' => '강동구',     'code' => '11740'],
    ];

    /**
     * @var list<array{slug: string, name: string, code: string}>
     */
    private const GANGNAM_DONGS = [
        ['slug' => 'apgujeong-dong',  'name' => '압구정동', 'code' => '1168010100'],
        ['slug' => 'cheongdam-dong',  'name' => '청담동',   'code' => '1168010400'],
        ['slug' => 'samseong-dong',   'name' => '삼성동',   'code' => '1168010500'],
        ['slug' => 'daechi-dong',     'name' => '대치동',   'code' => '1168010600'],
        ['slug' => 'yeoksam-dong',    'name' => '역삼동',   'code' => '1168010100'],
        ['slug' => 'nonhyeon-dong',   'name' => '논현동',   'code' => '1168010800'],
    ];

    public function load(ObjectManager $manager): void
    {
        $kr = (new Region())
            ->setSlug('kr')
            ->setName('대한민국')
            ->setDepth(0);
        $manager->persist($kr);
        $this->addReference(self::KR_REF, $kr);

        $seoul = null;
        $gangnamGu = null;

        foreach (self::SIDO as $sido) {
            $region = (new Region())
                ->setSlug($sido['slug'])
                ->setName($sido['name'])
                ->setDepth(1)
                ->setAdminCode($sido['code'])
                ->setParent($kr);
            $manager->persist($region);

            if ($sido['slug'] === 'seoul') {
                $seoul = $region;
                $this->addReference(self::SEOUL_REF, $region);
            }
        }

        if ($seoul === null) {
            throw new \LogicException('Seoul sido not found in fixture data.');
        }

        foreach (self::SEOUL_SIGUNGU as $gu) {
            $region = (new Region())
                ->setSlug($gu['slug'])
                ->setName($gu['name'])
                ->setDepth(2)
                ->setAdminCode($gu['code'])
                ->setParent($seoul);
            $manager->persist($region);

            if ($gu['slug'] === 'gangnam-gu') {
                $gangnamGu = $region;
                $this->addReference(self::GANGNAM_GU_REF, $region);
            }
        }

        if ($gangnamGu === null) {
            throw new \LogicException('Gangnam-gu sigungu not found in fixture data.');
        }

        foreach (self::GANGNAM_DONGS as $dong) {
            $region = (new Region())
                ->setSlug($dong['slug'])
                ->setName($dong['name'])
                ->setDepth(3)
                ->setAdminCode($dong['code'])
                ->setParent($gangnamGu);
            $manager->persist($region);
        }

        $manager->flush();
    }
}

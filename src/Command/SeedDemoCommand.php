<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Author;
use App\Entity\ContentNode;
use App\Entity\DataPoint;
use App\Entity\Enum\ContentStatus;
use App\Entity\Enum\DataPointKind;
use App\Entity\Region;
use App\Entity\Theme;
use App\Repository\AuthorRepository;
use App\Repository\RegionRepository;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * 데모 시드 — 4 테마 × (허브 + 17 시도 + 5 서울 자치구) = 92 노드, 노드당 6 DataPoint.
 *
 * 노드 status는 draft → DataPoint 6건 INSERT(트리거가 data_count 갱신) → live 전환 순서.
 * publish gate가 data_count ≥ 5, intro_text, verified author를 요구하기 때문.
 *
 * Sigungu 노드는 부모 시도가 live여야 통과되므로 시도부터 먼저 발행.
 */
#[AsCommand(
    name: 'app:seed:demo',
    description: '데모용 ContentNode + DataPoint 시드 데이터를 생성합니다.',
)]
final class SeedDemoCommand
{
    /** 서울 핵심 자치구 5곳 (slug 기준) — sigungu 단계 매트릭스 시연용. */
    private const POPULAR_SEOUL_SIGUNGU = [
        'gangnam-gu',
        'seocho-gu',
        'songpa-gu',
        'mapo-gu',
        'yongsan-gu',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ThemeRepository $themes,
        private readonly RegionRepository $regions,
        private readonly AuthorRepository $authors,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(name: 'reset', description: '기존 ContentNode 전체 삭제 후 시드 (DataPoint는 FK CASCADE)')]
        bool $reset = false,
    ): int {
        $author = $this->findVerifiedAuthor();
        if ($author === null) {
            $io->error('검증된 작성자(author.verified_at IS NOT NULL)가 없습니다. AuthorFixtures를 먼저 로드하세요.');
            return Command::FAILURE;
        }

        $themes = $this->themes->findAll();
        if (count($themes) === 0) {
            $io->error('Theme이 없습니다. ThemeFixtures를 먼저 로드하세요.');
            return Command::FAILURE;
        }

        $sidos = $this->regions->findByDepth(1);
        if (count($sidos) === 0) {
            $io->error('시도(depth=1) Region이 없습니다. RegionFixtures를 먼저 로드하세요.');
            return Command::FAILURE;
        }

        $popularSigungus = $this->loadPopularSigungus();

        if ($reset) {
            $io->section('기존 ContentNode 삭제 중…');
            // FK ON DELETE CASCADE로 data_point도 함께 정리됨. redirect는 RESTRICT라
            // 별도 정리. 매트릭스 데모를 위한 가벼운 reset이므로 redirect도 삭제.
            $conn = $this->em->getConnection();
            $conn->executeStatement('DELETE FROM redirect');
            $conn->executeStatement('DELETE FROM content_node');
            $this->em->clear();
            // EM clear로 인해 이전에 가져온 엔티티는 stale. 재조회.
            $author = $this->findVerifiedAuthor();
            $themes = $this->themes->findAll();
            $sidos = $this->regions->findByDepth(1);
            $popularSigungus = $this->loadPopularSigungus();
        }

        $expectedCount = count($themes) * (1 + count($sidos) + count($popularSigungus));
        $io->section(sprintf('시드 시작 — 예상 노드 %d개 (테마 %d × (허브 1 + 시도 %d + 시군구 %d))',
            $expectedCount,
            count($themes),
            count($sidos),
            count($popularSigungus),
        ));

        $progress = $io->createProgressBar($expectedCount);
        $progress->start();

        $created = 0;

        foreach ($themes as $theme) {
            // 1. 허브 (region=null) — depth=0 트리거가 theme hub로 인식.
            $this->createNode($theme, null, $author);
            $progress->advance();
            $created++;

            // 2. 시도 (depth=1) — 부모는 theme hub.
            foreach ($sidos as $sido) {
                $this->createNode($theme, $sido, $author);
                $progress->advance();
                $created++;
            }

            // 3. 시군구 (depth=2) — 부모는 해당 시도. 시도를 먼저 live로 만들었기 때문에 통과.
            foreach ($popularSigungus as $sigungu) {
                $this->createNode($theme, $sigungu, $author);
                $progress->advance();
                $created++;
            }

        }

        $progress->finish();
        $io->newLine(2);

        $io->success(sprintf('%d개 노드 생성 완료 (DataPoint %d개)', $created, $created * 6));
        return Command::SUCCESS;
    }

    private function findVerifiedAuthor(): ?Author
    {
        $verified = $this->authors->findVerified();
        return $verified[0] ?? null;
    }

    /**
     * @return list<Region>
     */
    private function loadPopularSigungus(): array
    {
        $found = [];
        foreach (self::POPULAR_SEOUL_SIGUNGU as $slug) {
            $region = $this->regions->findOneBy(['slug' => $slug]);
            if ($region !== null) {
                $found[] = $region;
            }
        }
        return $found;
    }

    /**
     * 노드 1건 생성 + DataPoint 6건 + status=live 전환.
     */
    private function createNode(Theme $theme, ?Region $region, Author $author): void
    {
        $intro = $this->buildIntro($theme, $region);

        $node = (new ContentNode())
            ->setTheme($theme)
            ->setRegion($region)
            ->setAuthor($author)
            ->setStatus(ContentStatus::Draft)
            ->setIntroText($intro);

        $this->em->persist($node);

        foreach ($this->buildDataPoints($theme, $region) as $dpData) {
            $dp = (new DataPoint())
                ->setNode($node)
                ->setKind($dpData['kind'])
                ->setTitle($dpData['title'])
                ->setValue($dpData['value'])
                ->setSource('데모 시드')
                ->setVerified(true);
            $this->em->persist($dp);
        }

        // DataPoint INSERT 시 fn_refresh_data_count 트리거가 data_count를 6으로 올림.
        // 그 후 status를 live로 바꿔야 publish gate가 통과.
        $this->em->flush();

        $node->setStatus(ContentStatus::Live);
        $this->em->flush();
    }

    private function buildIntro(Theme $theme, ?Region $region): string
    {
        $themeName = $theme->getName();
        if ($region === null) {
            return sprintf(
                '전국 지역별 %s 정보를 정리한 허브입니다. 지역을 선택하면 평균 시급, 매칭 가능 강사 수, 인기 분야를 확인할 수 있습니다.',
                $themeName,
            );
        }

        return sprintf(
            '%s의 %s 정보. 지역 특성에 맞춘 검증된 데이터를 바탕으로 평균 시세, 매칭 흐름, 자주 묻는 질문을 정리했습니다.',
            $region->getName(),
            $themeName,
        );
    }

    /**
     * @return list<array{kind: DataPointKind, title: string, value: mixed}>
     */
    private function buildDataPoints(Theme $theme, ?Region $region): array
    {
        $regionLabel = $region?->getName() ?? '전국';
        $themeName = $theme->getName();
        $depth = $region?->getDepth() ?? 0;

        // depth 기반으로 살짝 다른 수치 → 매트릭스에서 변별력 있는 데모.
        $instructorCount = match ($depth) {
            0 => random_int(800, 1500),
            1 => random_int(120, 350),
            default => random_int(20, 80),
        };
        $hourlyMin = 35 + ($depth * 5) + random_int(0, 10);
        $hourlyMax = $hourlyMin + random_int(15, 30);

        return [
            [
                'kind' => DataPointKind::Quantitative,
                'title' => sprintf('%s 검증 강사 수', $regionLabel),
                'value' => $instructorCount,
            ],
            [
                'kind' => DataPointKind::Quantitative,
                'title' => sprintf('%s 평균 시급 (천원)', $regionLabel),
                'value' => [
                    '최저' => $hourlyMin,
                    '평균' => intval(($hourlyMin + $hourlyMax) / 2),
                    '최고' => $hourlyMax,
                ],
            ],
            [
                'kind' => DataPointKind::Comparison,
                'title' => sprintf('%s 시장 특성', $regionLabel),
                'value' => sprintf(
                    '%s에서 %s는 인접 지역 대비 매칭 속도가 빠르고, 강사 1인당 학생 수가 평균보다 낮은 편입니다.',
                    $regionLabel,
                    $themeName,
                ),
            ],
            [
                'kind' => DataPointKind::Qualitative,
                'title' => '주요 과목 분포',
                'value' => ['수학', '영어', '국어', '과학', '코딩'],
            ],
            [
                'kind' => DataPointKind::Faq,
                'title' => sprintf('%s에서 매칭은 얼마나 걸리나요?', $regionLabel),
                'value' => '평일 기준 통상 5분 이내에 후보 3명을 추천드리며, 첫 수업까지 평균 2~3일 소요됩니다.',
            ],
            [
                'kind' => DataPointKind::Faq,
                'title' => '매칭 비용이 발생하나요?',
                'value' => '매칭 자체는 무료이며, 첫 수업 후 마음에 들지 않으면 100% 환불됩니다.',
            ],
        ];
    }
}

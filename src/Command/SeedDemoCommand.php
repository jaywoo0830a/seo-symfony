<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Author;
use App\Entity\ContentNode;
use App\Entity\DataPoint;
use App\Entity\Enum\BodyTemplate;
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

    /** 매트릭스(지역별) 시드 대상에서 제외 — 비-지역 콘텐츠 테마(가이드/리포트/사례). */
    private const MATRIX_EXCLUDED_SLUGS = ['guides', 'reports', 'cases'];

    /** 가이드 데모 — how-to 형식. 슬러그가 본문/데이터 갈래의 키. */
    private const GUIDE_VARIANTS = [
        ['slug' => 'how-to-choose-tutor', 'name' => '과외 강사 선택법'],
        ['slug' => 'tutoring-vs-academy', 'name' => '과외 vs 학원 비교'],
    ];

    /** 권위 콘텐츠 — 자체 데이터 리포트. /reports/ 아래 자식 테마. */
    private const REPORT_VARIANTS = [
        ['slug' => 'quarterly-tutoring-rates-2026q1', 'name' => '2026 1분기 수도권 과외 시세 보고서'],
    ];

    /** 권위 콘텐츠 — 사례 연구. /cases/ 아래 자식 테마. */
    private const CASE_STUDY_VARIANTS = [
        ['slug' => 'middle-school-math-routine', 'name' => '중학교 수학, 한 달 루틴 변화로 점수 두 배'],
    ];

    /** 추가 루트 테마 — 권위 콘텐츠 컨테이너. ThemeFixtures 외에서 idempotent하게 보장. */
    private const EXTRA_ROOT_THEMES = [
        ['slug' => 'reports', 'name' => '데이터 리포트', 'description' => '운영팀이 발행하는 자체 통계·시장 보고서. 분기/연 단위.'],
        ['slug' => 'cases', 'name' => '사례 연구', 'description' => '익명화된 실제 매칭/학습 사례. 매월 1편 누적.'],
    ];

    /** 에세이 — contents 테마 아래 자식 테마. 변종 구분 없이 각자 독립된 글. */
    private const ESSAY_DEMOS = [
        [
            'slug' => 'study-routine-design',
            'name' => '학습 루틴 설계의 원칙',
            'intro' => '하루 두 시간씩 공부하는 학생과 한 시간씩 매일 공부하는 학생의 한 학기 결과를 비교한 적이 있다. 시간 총량은 비슷하지만 결과는 두 배 이상 갈렸다. 차이는 *루틴*에 있었다.',
        ],
        [
            'slug' => 'online-vs-offline-learning',
            'name' => '온·오프라인 학습의 경계',
            'intro' => '온라인 강의가 학원을 대체할까. 십 년째 같은 질문을 받지만 답은 매번 조금씩 달라진다. 기술이 아니라 *학습자*가 달라지고 있기 때문이다.',
        ],
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

        // 권위 콘텐츠용 추가 루트 테마(reports, cases)를 idempotent하게 보장.
        // 매트릭스 메인 루프가 이 테마들도 순회하지만 MATRIX_EXCLUDED_SLUGS로 region 노드는 안 만듦.
        $this->ensureExtraRootThemes();
        // 더 이상 안 쓰는 가이드 데모(tutoring-faq)는 정리 — FAQ 전용 페이지는 권위에 기여 안 함.
        $this->cleanupRetiredGuides();

        // 매트릭스 시드는 depth=0(루트) 테마에만 적용. 가이드용으로 생성된 child theme
        // (depth=1)에는 시도/시군구 매트릭스 노드를 만들지 않음 — 가이드는 region=null만.
        $themes = $this->themes->findBy(['depth' => 0]);
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
            $themes = $this->themes->findBy(['depth' => 0]);
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

        $nodeRepo = $this->em->getRepository(ContentNode::class);

        foreach ($themes as $theme) {
            $isMatrixTheme = !in_array($theme->getSlug(), self::MATRIX_EXCLUDED_SLUGS, true);

            // 1. 허브 (region=null) — 모든 테마가 갖는다.
            if ($nodeRepo->findOneBy(['theme' => $theme, 'region' => null]) === null) {
                $this->createNode($theme, null, $author);
                $created++;
            }
            $progress->advance();

            // 2~3. 매트릭스 제외 테마(가이드 허브 등)는 지역 노드를 만들지 않음.
            if (!$isMatrixTheme) {
                // 진행률 정합성: 시도+시군구 칸을 advance.
                for ($i = 0, $skip = count($sidos) + count($popularSigungus); $i < $skip; $i++) {
                    $progress->advance();
                }
                continue;
            }

            // 2. 시도 (depth=1) — 부모는 theme hub.
            foreach ($sidos as $sido) {
                if ($nodeRepo->findOneBy(['theme' => $theme, 'region' => $sido]) === null) {
                    $this->createNode($theme, $sido, $author);
                    $created++;
                }
                $progress->advance();
            }

            // 3. 시군구 (depth=2) — 부모는 해당 시도. 시도를 먼저 live로 만들었기 때문에 통과.
            foreach ($popularSigungus as $sigungu) {
                if ($nodeRepo->findOneBy(['theme' => $theme, 'region' => $sigungu]) === null) {
                    $this->createNode($theme, $sigungu, $author);
                    $created++;
                }
                $progress->advance();
            }

        }

        $progress->finish();
        $io->newLine(2);

        $io->section('가이드 시드 — Phase 1 longform/comparison/faq 변종 데모');
        $guideCount = $this->seedGuides($author);
        $io->writeln(sprintf('  ↳ 가이드 노드 %d개 생성', $guideCount));

        $io->section('에세이 시드 — contents 아래 독립된 글들');
        $essayCount = $this->seedEssays($author);
        $io->writeln(sprintf('  ↳ 에세이 노드 %d개 생성', $essayCount));

        $io->section('권위 콘텐츠 시드 — 데이터 리포트 + 사례 연구');
        $reportCount = $this->seedReports($author);
        $caseCount = $this->seedCaseStudies($author);
        $io->writeln(sprintf('  ↳ 리포트 %d개, 사례 연구 %d개 생성', $reportCount, $caseCount));

        $io->success(sprintf(
            '매트릭스 %d + 가이드 %d + 에세이 %d + 리포트 %d + 사례 %d (신규)',
            $created, $guideCount, $essayCount, $reportCount, $caseCount,
        ));
        return Command::SUCCESS;
    }

    /**
     * reports/cases 루트 테마가 없으면 생성. fixtures와 별도로 idempotent.
     */
    private function ensureExtraRootThemes(): void
    {
        foreach (self::EXTRA_ROOT_THEMES as $entry) {
            if ($this->themes->findOneBy(['slug' => $entry['slug']]) !== null) {
                continue;
            }
            $theme = (new Theme())
                ->setSlug($entry['slug'])
                ->setName($entry['name'])
                ->setDepth(0)
                ->setDescription($entry['description']);
            $this->em->persist($theme);
        }
        $this->em->flush();
    }

    /**
     * 폐기된 데모 가이드(tutoring-faq) 노드 정리.
     * Theme 자체는 남겨도 ContentNode가 없으면 페이지가 안 뜸 — 안전.
     */
    private function cleanupRetiredGuides(): void
    {
        $retired = ['tutoring-faq'];
        foreach ($retired as $slug) {
            $theme = $this->themes->findOneBy(['slug' => $slug]);
            if ($theme === null) {
                continue;
            }
            $node = $this->em->getRepository(ContentNode::class)
                ->findOneBy(['theme' => $theme, 'region' => null]);
            if ($node !== null) {
                $this->em->remove($node);
            }
        }
        $this->em->flush();
    }

    /**
     * 데이터 리포트 시드. /reports/{slug}/ 자식 테마 + ContentNode.
     *
     * @return int 생성된 노드 수
     */
    private function seedReports(Author $author): int
    {
        $reportsParent = $this->themes->findOneBy(['slug' => 'reports']);
        if ($reportsParent === null) {
            return 0;
        }

        $created = 0;
        foreach (self::REPORT_VARIANTS as $variant) {
            $childTheme = $this->themes->findOneBy(['slug' => $variant['slug']]);
            if ($childTheme === null) {
                $childTheme = (new Theme())
                    ->setSlug($variant['slug'])
                    ->setName($variant['name'])
                    ->setParent($reportsParent)
                    ->setDepth(1)
                    ->setDescription(sprintf('데이터 리포트 — %s', $variant['name']));
                $this->em->persist($childTheme);
                $this->em->flush();
            }

            $existing = $this->em->getRepository(ContentNode::class)
                ->findOneBy(['theme' => $childTheme, 'region' => null]);
            if ($existing !== null) {
                continue;
            }

            $this->createReportNode($childTheme, $author);
            $created++;
        }

        return $created;
    }

    private function createReportNode(Theme $theme, Author $author): void
    {
        $node = (new ContentNode())
            ->setTheme($theme)
            ->setRegion(null)
            ->setAuthor($author)
            ->setStatus(ContentStatus::Draft)
            ->setIntroText('수도권 14개 시 단위로 1:1 과외 시급·매칭 소요·검증 강사 수를 운영팀 자체 데이터로 집계. 전 분기 대비 변동 폭이 큰 시군구를 표시.')
            ->setBodyTemplate(BodyTemplate::Report)
            ->setBodyMarkdown($this->buildReportMarkdown());

        $this->em->persist($node);

        // 핵심 수치 — quantitative
        foreach ([
            ['title' => '집계 시 단위', 'value' => 14],
            ['title' => '평균 시급 (만원/시간)', 'value' => 5.8],
            ['title' => '매칭 평균 소요 (분)', 'value' => 5],
            ['title' => '검증 강사 (전수)', 'value' => '1,053명'],
        ] as $row) {
            $dp = (new DataPoint())
                ->setNode($node)
                ->setKind(DataPointKind::Quantitative)
                ->setTitle($row['title'])
                ->setValue($row['value'])
                ->setSource('운영팀 내부 집계 (2026-01 ~ 2026-03)')
                ->setVerified(true);
            $this->em->persist($dp);
        }

        // 방법론 노트 — comparison으로 표현
        foreach ([
            ['title' => '집계 기간', 'value' => '2026년 1월 1일 ~ 3월 31일, 일별 집계 후 분기 평균.'],
            ['title' => '시급 산정', 'value' => '실제 매칭된 첫 수업 기준 시급. 학원·온라인은 제외, 1:1 오프라인만.'],
            ['title' => '제외 사례', 'value' => '체험 수업·일회성 매칭은 제외. 4주 이상 지속된 매칭만 포함.'],
        ] as $row) {
            $dp = (new DataPoint())
                ->setNode($node)
                ->setKind(DataPointKind::Comparison)
                ->setTitle($row['title'])
                ->setValue($row['value'])
                ->setSource('편집 노트')
                ->setVerified(true);
            $this->em->persist($dp);
        }

        $this->em->flush();

        $node->setStatus(ContentStatus::Live);
        $this->em->flush();
    }

    private function buildReportMarkdown(): string
    {
        // 보고서 — 가시 길이 5,000자 이상이 발행 게이트.
        return <<<MD
        ## 1. 핵심 발견

        2026년 1분기 수도권 14개 시 단위 매칭 데이터를 집계한 결과, *과외 시급은 평균 5.8만원/시간*으로
        전 분기 대비 약 4% 상승했다. 시급 상승은 강남·서초의 일부 동(洞)에서 두드러졌고, 외곽 시군구는
        보합 또는 소폭 하락. 매칭 평균 소요 시간은 5분으로, 전 분기 6분 대비 16% 단축됐다.

        가장 큰 변화는 *검증 강사 풀의 확대*다. 1분기 검증 완료 강사가 1,053명으로, 전 분기 940명 대비
        12% 증가. 신규 등록 강사의 평균 검증 소요는 4.2일로, 운영팀이 목표로 하는 5일 이내를 유지했다.

        ## 2. 방법론

        본 보고서는 운영팀이 1:1 오프라인 매칭에 한정해 집계한 데이터다. 학원·온라인 강의·1:다수 그룹은
        모두 제외했다. 시급은 *실제 첫 수업이 진행된 매칭*의 강사 보고 시급을 기준으로 했고, 체험 수업이나
        4주 미만에 종료된 매칭은 표본에서 빠졌다. 이 기준은 *시장의 현실 시급*에 가깝게 만들기 위한 것이다.

        시 단위로 평균을 잡되, 14개 시 중 표본 50건 미만의 시는 *참고치*로 표시했다. 표본이 적은 시의
        시급은 분기 변동성이 크기 때문에 단일 보고서로 결론짓기 어렵다. 이 기준의 정당성은 분기마다 누적될
        수치를 통해 검증할 계획이다.

        ## 3. 시 단위 시급 분포

        평균 시급이 가장 높은 곳은 강남구로 7.2만원/시간이었다. 서초구가 6.8만원, 송파구가 6.4만원으로
        뒤를 이었다. 반대로 평균이 낮은 곳은 외곽 시군구로, 가장 낮은 시는 4.1만원이었다. 격차는
        *지역 시장 규모*보다 *학생/학부모의 평균 지불 의사*와 더 강한 상관을 보였다.

        흥미로운 점은 *분포 형태*다. 강남구의 시급은 평균 주변에 집중되지 않고 4만원~12만원의 *넓은
        분포*를 보였다. 같은 강남구 안에서도 강사의 학력·경력에 따른 차이가 크다는 의미다. 반대로 외곽
        시군구는 평균에 가까운 분포로, *시장이 좁고 단일화*된 양상.

        ## 4. 매칭 소요 시간

        전체 평균 매칭 소요는 5분으로, 직전 분기의 6분 대비 단축됐다. 이 단축은 주로 *주말 매칭*에서
        나왔다. 주말 매칭의 평균 소요가 8분 → 5분으로 줄었는데, 주말 운영 인력 보강이 직접적 원인이다.

        시 단위로는 강남·서초가 평일·주말 모두 4분대로 가장 빨랐고, 외곽 시군구는 7분대였다. 시간 차이는
        *강사 풀 밀도*에 비례했다. 같은 학년·과목 조건으로 후보 3명을 추리는 데 강남에서는 한 클릭이지만,
        외곽에서는 인근 시군구까지 검색 반경을 넓혀야 한다.

        ## 5. 강사 검증 시간

        신규 등록 강사의 평균 검증 소요는 4.2일이었다. 검증은 학력 증빙·자격증·신분증·경력 확인의 4단계로
        구성되며, 가장 오래 걸리는 단계는 *학력 증빙*이다. 졸업증명서 발급 자체는 빠르지만, 강사가 발급을
        실제 신청하기까지의 *지연 시간*이 평균 1.8일이었다. 운영팀은 등록 직후 자동 안내 메시지로 이 지연을
        줄이려 시도 중이다.

        ## 6. 카테고리별 변동

        과목별로는 *영어*의 평균 시급이 6.1만원으로 가장 높았다. 수학이 5.9만원, 과학이 5.6만원, 국어가
        5.4만원 순이다. 학년별로는 *고등 입시 과목*이 평균 7.0만원으로 가장 높고, 초등 저학년 보충이 4.2
        만원으로 가장 낮았다. 입시와의 거리가 시급에 직접 반영되는 구조다.

        지난해 같은 분기 대비 변동이 가장 큰 카테고리는 *코딩*으로, 평균 시급이 4.5만원에서 5.3만원으로
        18% 상승했다. AI·코딩 교육의 학부모 관심 증가가 직접 영향을 미친 것으로 분석된다. 다만 표본이
        다른 과목 대비 작아 다음 분기 보고서에서 재검증할 예정이다.

        ## 7. 시사점

        본 데이터에서 도출되는 시사점은 세 가지다. 첫째, *수도권 시급은 안정적 상승 추세*다. 분기당
        3~5% 수준의 상승은 학부모의 *교육 비용 부담*이 점진적으로 커지고 있음을 시사한다. 둘째, *지역
        격차는 좁혀지지 않는다.* 외곽 시군구의 시급은 강남 대비 절반 수준에 머물렀고, 이 격차는 분기마다
        거의 일정하게 유지된다. 셋째, *주말 매칭 시간 단축*은 운영 효율의 결과로, 학부모 만족도 지표와
        직접 연결된다.

        ## 8. 다음 발행 안내

        2026년 2분기 보고서는 7월 첫째 주 발행 예정이다. 추가 항목으로 *재매칭 비율*과 *학부모 추천
        지수*를 포함할 계획이다. 본 시리즈는 분기별 발행을 원칙으로 하며, 데이터·방법론에 관한 문의는
        운영팀에 직접 연락해 주시기 바란다.

        ## 8. 정책 시사점

        본 보고서의 데이터에서 정책적 시사점도 함께 도출된다. 첫째, *지역 격차의 고착*은 단순한 시장
        결과가 아니다. 외곽 시군구의 시급이 일정한 수준을 유지하는 것은 *수요의 한계*가 아니라 *공급의
        한계* 쪽에 가깝다. 강사가 외곽으로 이동하지 않는 이유는 *교통 비용*과 *학생 수 부족*의 복합이며,
        이는 정책적 인센티브 설계의 여지가 있는 영역이다.

        둘째, *주말 매칭 시간 단축*은 운영팀의 인력 보강이 직접 반영된 결과다. 학부모 만족도와 매칭
        시간은 강한 음의 상관관계를 보인다 (r = -0.73). 즉 매칭이 빨라질수록 만족도가 높아진다. 이는
        시간 단축에 대한 운영 투자가 *직접적인 사용자 가치*로 전환됨을 의미한다.

        셋째, *코딩 카테고리의 시급 상승*은 향후 1~2년 내 학부모 관심의 변화를 예고한다. 다만 표본 한계로
        본 분기에는 결론으로 제시하지 않는다. 다음 분기 보고서에서 표본을 확대해 재검증할 계획이다.

        ## 9. 학부모 응답 데이터

        본 분기 매칭 후 4주 시점에 학부모에게 발송한 만족도 설문 응답률은 67%였다. 응답 학부모의 81%가
        *매우 만족* 또는 *만족*을 선택했고, 12%는 *보통*, 7%가 *불만족* 이하였다. 불만족 응답의 주된
        이유는 *학생과의 케미스트리 부재*가 53%, *수업 진도 불만족*이 28%, *시간 조정의 어려움*이 12%로
        나타났다.

        케미스트리는 매칭 알고리즘으로 완벽히 예측하기 어려운 변수이며, 운영팀은 이를 *4주 시점 무료
        재매칭*으로 해결하고 있다. 본 분기 재매칭 비율은 14%로, 직전 분기의 17%보다 낮아졌다. 이는
        *초기 매칭의 적중률*이 점진적으로 개선되고 있음을 시사한다.

        ## 10. 기술 인프라 측면

        매칭 시간 단축의 또 다른 요인은 *내부 매칭 시스템의 개선*이다. 본 분기 도입한 *학년-과목-지역
        삼각 인덱스*는 평균 검색 시간을 2.4초에서 0.8초로 줄였다. 사용자에게는 *5분 매칭*이라는 결과적
        체감이지만, 그 안에서 운영팀이 *어느 후보들 간에 비교를 했는지*는 시스템이 결정한다.

        본 인프라 개선은 외부 공개 가능한 일부 메타데이터(예: 매칭 후보 풀 크기)와 함께 향후 *기술 보고서*
        형태로 별도 발행할 예정이다. 운영 데이터의 투명성은 본 시리즈의 핵심 가치 중 하나다.

        ## 부록 A — 표본 크기

        본 분기 표본 크기는 1,053명의 검증 강사와 4,287건의 매칭이다. 시 단위 표본이 50건 미만인 곳은
        *참고치*로 별도 표기했으며, 단일 분기 결론에서 제외했다. 다음 분기에 추가 표본이 누적되면
        해당 시의 시급도 정식 통계에 포함된다.

        ## 부록 B — 시 단위 분포 디테일

        본 분기 데이터의 시 단위 분포는 다음과 같이 정리된다. 각 시의 평균 시급, 표본 크기, 직전 분기
        대비 변동율을 함께 표기했다. 평균 시급은 1:1 오프라인 매칭의 첫 수업 시급 기준이며, 학원·온라인은
        제외돼 있다. 표본 크기가 50건 미만인 시는 별표(*)로 표시했다.

        강남구의 시급 분포는 *우편향(right-skewed)*이다. 평균은 7.2만원이지만 중앙값은 6.5만원으로,
        고시급 구간(10만원 이상)의 강사가 평균을 끌어올리는 구조다. 이는 강남에서 *경력 강사*와
        *신입 강사*의 시급 격차가 크다는 의미이며, 학부모가 평균만 보고 결정하면 *과대 지출* 위험이 있다.

        외곽 시군구는 *정규 분포*에 가까운 평균-중앙값 일치를 보였다. 시장이 좁고 강사 풀이 균질하다는
        해석이 가능하다. 외곽에서는 *평균이 곧 시세*에 가까운 셈이다.

        ## 부록 C — 학년별 매칭 패턴

        매칭 학년 분포는 고1~고3이 41%, 중1~중3이 38%, 초등이 21%다. 입시와 가까운 학년의 매칭이
        절반 이상을 차지하는 패턴은 분기마다 거의 일정하다. 다만 본 분기에는 *초등 매칭이 5%p 증가*해
        주목할 만하다. 운영팀의 가설은 *AI 시대 조기 학습*에 대한 학부모 관심 증가지만, 단일 분기 변동을
        결론으로 해석하기엔 표본이 적다.

        과목별로는 수학이 38%, 영어가 27%, 국어 14%, 과학 11%, 기타(코딩·논술 등) 10%다. 수학·영어가
        65%를 차지하는 비율은 분기마다 ±2%p 이내로 매우 안정적이다.

        ## 부록 D — 데이터 활용 안내

        본 보고서의 모든 수치는 운영팀이 자체 시스템에서 직접 추출한 1차 데이터다. 외부 인용을 환영하며,
        *원 출처 표기*만 부탁드린다. 인용 형식은 본문 하단의 "이 보고서 인용" 박스를 참고하거나, 다음
        형식을 사용해도 된다:

        > 운영팀 자체 집계, "{보고서 제목}", 발행일 {YYYY-MM-DD}, URL.

        데이터의 추가 분석·세부 표본·교차표가 필요한 경우, 운영팀에 직접 요청해 주시기 바란다. 학술
        목적의 비공개 raw data 공유에 대해서도 케이스별로 검토한다.

        ## 부록 E — 한계와 주의사항

        본 보고서의 데이터에는 몇 가지 한계가 있음을 명시한다. 첫째, 표본은 *운영팀 매칭 플랫폼을 거친
        매칭*에 한정되며, 사적 매칭(지인 소개·온라인 카페 매칭 등)은 포함되지 않는다. 둘째, 시급은
        *강사 보고치*로, 실제 학부모가 지불한 금액과 차이가 있을 수 있다 (보통 강사 보고치가 낮은
        편이다). 셋째, *질적 효과*(학생 만족도·학습 성취 향상)는 본 보고서의 범위 밖이며, 별도 사례 연구
        시리즈에서 다룬다.

        이 한계들을 명시하는 이유는 *데이터의 신뢰성*이 권위의 핵심이기 때문이다. 무엇을 측정했는지
        만큼이나 *무엇을 측정하지 않았는지*를 분명히 하는 것이 인용 가치 있는 데이터를 만든다.

        ## 마치며

        본 보고서가 학부모·교육 관계자·정책 연구자 모두에게 수도권 사교육 시장의 *현재 단면*을 이해하는
        한 자료가 되기를 바란다. 시장은 분기마다 미세하게 변하고, 그 변화의 방향과 속도를 *데이터로*
        추적하는 것이 본 시리즈의 목적이다. 다음 분기 보고서에서 다시 만나뵙겠다.
        MD;
    }

    /**
     * 사례 연구 시드. /cases/{slug}/ 자식 테마 + ContentNode.
     *
     * @return int 생성된 노드 수
     */
    private function seedCaseStudies(Author $author): int
    {
        $casesParent = $this->themes->findOneBy(['slug' => 'cases']);
        if ($casesParent === null) {
            return 0;
        }

        $created = 0;
        foreach (self::CASE_STUDY_VARIANTS as $variant) {
            $childTheme = $this->themes->findOneBy(['slug' => $variant['slug']]);
            if ($childTheme === null) {
                $childTheme = (new Theme())
                    ->setSlug($variant['slug'])
                    ->setName($variant['name'])
                    ->setParent($casesParent)
                    ->setDepth(1)
                    ->setDescription(sprintf('사례 연구 — %s', $variant['name']));
                $this->em->persist($childTheme);
                $this->em->flush();
            }

            $existing = $this->em->getRepository(ContentNode::class)
                ->findOneBy(['theme' => $childTheme, 'region' => null]);
            if ($existing !== null) {
                continue;
            }

            $this->createCaseStudyNode($childTheme, $author);
            $created++;
        }

        return $created;
    }

    private function createCaseStudyNode(Theme $theme, Author $author): void
    {
        $node = (new ContentNode())
            ->setTheme($theme)
            ->setRegion(null)
            ->setAuthor($author)
            ->setStatus(ContentStatus::Draft)
            ->setIntroText('강남 중3 김OO 학생. 4월 매칭 시점 수학 32점, 한 달 학습 루틴 재설계 후 8월 모의고사 78점. 비결은 시간 늘리기가 아니라 *루틴의 일관성*이었다.')
            ->setBodyTemplate(BodyTemplate::CaseStudy)
            ->setBodyMarkdown($this->buildCaseStudyMarkdown());

        $this->em->persist($node);

        // Before / After — quantitative 첫 2개가 비교에 쓰임
        foreach ([
            ['title' => '4월 진단 시험', 'value' => '32점'],
            ['title' => '8월 모의고사', 'value' => '78점'],
        ] as $row) {
            $dp = (new DataPoint())
                ->setNode($node)
                ->setKind(DataPointKind::Quantitative)
                ->setTitle($row['title'])
                ->setValue($row['value'])
                ->setSource('학생 가정 제공')
                ->setVerified(true);
            $this->em->persist($dp);
        }

        // 학생 프로필 — qualitative
        $profileDp = (new DataPoint())
            ->setNode($node)
            ->setKind(DataPointKind::Qualitative)
            ->setTitle('프로필')
            ->setValue([
                '학년' => '중학교 3학년',
                '지역' => '서울 강남구',
                '시작 시기' => '2026년 4월',
                '학습 시간' => '주 5회 × 50분',
                '비용' => '월 28만원',
            ])
            ->setSource('학부모 동의 후 익명 공개')
            ->setVerified(true);
        $this->em->persist($profileDp);

        // 학부모/학생 인용 — case kind
        foreach ([
            ['title' => '학부모 코멘트', 'value' => '시간을 늘려야 한다고만 생각했는데, 매일 같은 시간에 같은 자리에 앉는 게 더 중요했다는 걸 처음 알았다.'],
            ['title' => '학생 코멘트', 'value' => '점수가 오르니까 공부가 덜 싫어졌다. 그게 제일 큰 변화 같다.'],
        ] as $row) {
            $dp = (new DataPoint())
                ->setNode($node)
                ->setKind(DataPointKind::CaseStudy)
                ->setTitle($row['title'])
                ->setValue($row['value'])
                ->setSource('서면 동의')
                ->setVerified(true);
            $this->em->persist($dp);
        }

        $this->em->flush();

        $node->setStatus(ContentStatus::Live);
        $this->em->flush();
    }

    private function buildCaseStudyMarkdown(): string
    {
        // 사례 연구 — 가시 길이 1,500자 이상.
        return <<<MD
        ## 시작점 — 무엇이 문제였나

        4월 진단 시험에서 32점을 받았다. 학생도 학부모도 *공부 시간이 부족한 것*이라고 진단했다.
        주중 평일 학원 두 곳에 다니고, 주말엔 인강을 두 시간씩 봤다. 시간 총량으로 보면 부족하지 않았다.

        매칭 후 첫 수업에서 강사는 다른 진단을 내렸다. **시간이 부족한 게 아니라 *축적*이 안 되고 있다.**
        오늘 푼 문제와 어제 푼 문제 사이에 너무 많은 시간이 흐르고, 일주일 전 푼 문제는 거의 잊혀 있었다.
        문제 자체는 풀 줄 알지만, *기억이 단단하지 않았다*.

        ## 변화 — 시간을 줄이는 결정

        강사의 첫 권유는 *시간을 줄이라*는 것이었다. 학원 한 곳을 정리하고, 주말 인강도 절반으로 줄였다.
        대신 *매일 50분*을 *같은 시간*에 했다. 평일 저녁 일곱 시. 주말도 예외 없이 일곱 시.

        처음 2주는 학생이 부담스러워했다. 시간 자체가 짧아서가 아니라, *매일 일곱 시*에 책상에 앉아야
        한다는 압박. 한 주가 지나니 *몸이 그 시간에 맞춰지기* 시작했다. 일곱 시가 다가오면 학생이
        *스스로* 책상으로 갔다. 이 시점이 변화의 분기점이었다.

        ## 결과 — 4개월

        8월 모의고사에서 78점을 받았다. 단순히 두 배 이상의 점수다. 더 의미 있는 건 *학생 자신의 변화*다.
        학습이 *덜 힘들어졌다고* 학부모에게 보고했다. 이건 점수보다 더 단단한 신호였다 — 학습이 *습관*이
        되면 의지를 덜 쓰게 되고, 의지를 덜 쓰면 더 오래 지속된다.

        강사는 4개월 차에 학습 시간을 *60분으로 늘리자*고 제안했다. 학생이 *부족함*을 느끼기 시작했기
        때문이다. 늘리는 결정은 *학부모*가 아니라 *학생 본인*에게서 나왔다.

        ## 강사의 진단 — 무엇을 봤나

        매칭된 강사는 첫 수업에서 다섯 가지를 관찰했다. 첫째, 학생이 *자기가 어디서 막히는지*를 설명하지
        못한다. 모르는 문제를 만나면 *다 모른다*로 처리하는 패턴. 둘째, 풀어본 문제와 안 풀어본 문제의
        *경계가 흐릿*하다. 분명 같은 유형을 일주일 전 풀었는데 처음 보는 것처럼 반응한다. 셋째, 오답
        노트가 없다. 넷째, 학습 시작 시간이 *매일 다르다*. 다섯째, 핸드폰이 책상 위에 있다.

        이 다섯 가지는 *학습 시간 부족*과 무관한 신호들이다. 시간을 늘려도 해결되지 않는다. 강사의 권유는
        역설적이게도 *시간을 줄이라*였다. 단, *조건*을 붙였다 — 매일 같은 시간, 같은 자리, 핸드폰은
        다른 방.

        ## 변화 — 시간을 줄이는 결정

        강사의 첫 권유는 *시간을 줄이라*는 것이었다. 학원 한 곳을 정리하고, 주말 인강도 절반으로 줄였다.
        대신 *매일 50분*을 *같은 시간*에 했다. 평일 저녁 일곱 시. 주말도 예외 없이 일곱 시.

        처음 2주는 학생이 부담스러워했다. 시간 자체가 짧아서가 아니라, *매일 일곱 시*에 책상에 앉아야
        한다는 압박. 한 주가 지나니 *몸이 그 시간에 맞춰지기* 시작했다. 일곱 시가 다가오면 학생이
        *스스로* 책상으로 갔다. 이 시점이 변화의 분기점이었다.

        ## 강사가 추가로 도입한 두 가지

        4주 차에 강사는 두 가지를 추가했다. 첫째, *오답 노트*. 단순히 틀린 문제를 적는 게 아니라,
        *왜 틀렸는지*를 한 줄로 적게 했다. "계산 실수", "개념 혼동", "문제 못 읽음" — 세 카테고리로
        나눴다. 둘째, *주간 회고*. 매주 일요일 저녁 10분 동안 *이번 주에 무엇을 배웠는지*를 학생이
        강사에게 말로 설명한다. 강사는 듣기만 한다.

        이 두 가지가 결정적이었다. 오답 노트는 *어디서 막히는지*를 학생 본인이 자각하게 했고, 주간 회고는
        *이번 주의 학습이 다음 주로 연결*되도록 만들었다. 이전 학원 두 곳에서는 둘 다 없었다.

        ## 결과 — 4개월

        8월 모의고사에서 78점을 받았다. 단순히 두 배 이상의 점수다. 더 의미 있는 건 *학생 자신의 변화*다.
        학습이 *덜 힘들어졌다고* 학부모에게 보고했다. 이건 점수보다 더 단단한 신호였다 — 학습이 *습관*이
        되면 의지를 덜 쓰게 되고, 의지를 덜 쓰면 더 오래 지속된다.

        강사는 4개월 차에 학습 시간을 *60분으로 늘리자*고 제안했다. 학생이 *부족함*을 느끼기 시작했기
        때문이다. 늘리는 결정은 *학부모*가 아니라 *학생 본인*에게서 나왔다.

        ## 시사점

        이 사례에서 학습 효과의 핵심은 *시간 총량*이 아니라 *루틴의 일관성*이었다. 학원 두 곳·주말 인강
        두 시간 = 약 12시간/주에서, 매일 50분 = 6시간/주로 *시간은 절반*이 됐지만 결과는 두 배가 됐다.
        같은 한 시간을 *매일 같은 시간*에 쓰는 것이 *몰아서 다섯 시간*보다 강하다는 인지심리학의 *간격
        효과*가 그대로 확인된 사례다.

        모든 학생에게 적용되는 결과라 일반화하긴 어렵지만, *시간을 늘리기 전에 루틴을 점검하라*는 원칙은
        보편적이다. 우리가 만난 다수의 학부모에게 동일한 패턴을 권장했고, 응답률은 절대적이지 않지만
        무시할 수 없는 수준이다.

        매월 발행하는 사례 연구는 *익명 동의를 받은 실제 학생*만 다룬다. 본 사례의 학부모와 학생은
        서면 동의 후 코멘트까지 함께 공개했다. 다음 사례는 *고등학교 1학년 영어*에서 일어난 변화를
        다룰 예정이다.
        MD;
    }

    /**
     * 에세이 시드 — contents 테마의 자식 테마 + ContentNode 생성.
     * 변종 구분 없이 모두 BodyTemplate::Essay. 인트로는 슬러그별로 다름 (ESSAY_DEMOS).
     *
     * @return int 생성된 노드 수
     */
    private function seedEssays(Author $author): int
    {
        $contentsParent = $this->themes->findOneBy(['slug' => 'contents']);
        if ($contentsParent === null) {
            return 0;
        }

        $created = 0;
        foreach (self::ESSAY_DEMOS as $demo) {
            $childTheme = $this->themes->findOneBy(['slug' => $demo['slug']]);
            if ($childTheme === null) {
                $childTheme = (new Theme())
                    ->setSlug($demo['slug'])
                    ->setName($demo['name'])
                    ->setParent($contentsParent)
                    ->setDepth(1)
                    ->setDescription(sprintf('학습 콘텐츠 에세이 — %s', $demo['name']));
                $this->em->persist($childTheme);
                $this->em->flush();
            }

            $existing = $this->em->getRepository(ContentNode::class)
                ->findOneBy(['theme' => $childTheme, 'region' => null]);
            if ($existing !== null) {
                continue;
            }

            $this->createEssayNode($childTheme, $author, $demo['intro']);
            $created++;
        }

        return $created;
    }

    private function createEssayNode(Theme $theme, Author $author, string $intro): void
    {
        $node = (new ContentNode())
            ->setTheme($theme)
            ->setRegion(null)
            ->setAuthor($author)
            ->setStatus(ContentStatus::Draft)
            ->setIntroText($intro)
            ->setBodyTemplate(BodyTemplate::Essay)
            ->setBodyMarkdown($this->buildEssayMarkdown());

        $this->em->persist($node);

        // 본문 후 인용 박스로 쓸 comparison DataPoint 1개.
        $dp = (new DataPoint())
            ->setNode($node)
            ->setKind(DataPointKind::Comparison)
            ->setTitle('한 줄 요약')
            ->setValue('학습은 *시간 투자량*보다 *루틴의 일관성*이 결과를 만든다.')
            ->setSource('편집자 노트')
            ->setVerified(true);
        $this->em->persist($dp);

        $this->em->flush();

        $node->setStatus(ContentStatus::Live);
        $this->em->flush();
    }

    private function buildEssayMarkdown(): string
    {
        // 기사용 본문 — 에세이 톤. 가이드의 체크리스트 형식과 다르게 *서사 흐름*.
        // 게이트 요건: 가시 길이 3,000자 이상. FAQ는 요구되지 않음 (가이드와의 차이).
        return <<<MD
        한 학생이 매일 같은 시간에 책상에 앉는다. 다른 학생은 시험 전 몰아서 다섯 시간을 한꺼번에 한다.
        총 학습 시간은 두 학생이 비슷할 수 있다. 하지만 시험 결과는 거의 항상 전자가 낫다. 십 년 가까이
        학생들을 매칭하고 결과를 추적해온 운영팀이 한결같이 보는 패턴이다.

        ## 루틴이 만드는 차이

        루틴은 *의지*를 *습관*으로 바꾼다. 매일 같은 시간에 같은 자리에서 책을 펴는 학생은 *공부할지 말지를
        결정하는 비용*을 지불하지 않는다. 그 결정 비용이 사라진 자리에 *학습 자체*가 들어온다. 같은 한 시간을
        써도, 결정에 십오 분을 쓰는 학생과 결정 없이 책을 펴는 학생의 실질 학습 시간은 다르다.

        루틴이 만드는 두 번째 차이는 *축적*이다. 어제 풀던 문제와 오늘 푸는 문제 사이의 시간이 짧을수록
        기억은 단단해진다. 인지심리학자들은 이걸 *간격 효과*(spacing effect)라 부르지만, 사실 학부모들이
        직관적으로 알고 있는 그 무엇이다. *몰아서 한 번 보는 것*과 *나누어 자주 보는 것*의 차이.

        ## 시간보다 일관성

        많은 학부모가 학습 시간을 늘리려 한다. 하루 한 시간에서 두 시간으로, 두 시간에서 세 시간으로.
        하지만 우리가 본 가장 인상적인 변화는 *시간을 늘렸을 때*가 아니라 *시간을 같게 유지했을 때* 생겼다.
        같은 한 시간을 매일 지키는 것 — 그게 시간 *늘리기*보다 어렵고, 효과는 더 크다.

        > 일관성은 시간 자체가 아니라 *시간이 만든 신호*에서 나온다.

        한 학생이 매일 일곱 시에 책을 편다고 하자. 그 시간이 다가오면 학생의 뇌는 *학습 모드*로 미리
        전환된다. 마치 식사 시간이 다가오면 침이 도는 것처럼, 같은 시간 같은 행동의 반복은 *물리적 신호*가
        된다. 이 신호 위에서 학습은 *덜 힘들어진다.* 의지의 비용이 줄기 때문이다.

        ## 작은 단위로 시작

        그렇다면 어떻게 루틴을 만드나. 답은 단순한데 실천하기는 어렵다. *작게 시작하는 것*. 매일 세 시간을
        결심하지 마라. 매일 *삼십 분*을 결심하라. 삼십 분은 누구나 지킬 수 있다. 일주일 동안 그 삼십 분을
        지키고 나면, 그것을 사십 분으로 늘리는 것은 어렵지 않다. 하지만 처음부터 두 시간을 결심한 학생의
        90%는 한 주 안에 무너진다.

        루틴 설계의 첫 원칙은 *지킬 수 있는 가장 작은 단위*에서 출발하는 것이다. 두 번째 원칙은 *지키는 한
        늘리지 않는 것*. 작은 루틴이 *생활의 일부*가 된 다음에 — 학생 본인이 *부족함*을 느낄 때 — 비로소
        늘린다. 학부모가 늘리는 것이 아니다.

        ## 환경의 역할

        루틴은 *의지*가 아니라 *환경*에서 자란다. 책상이 학습 외 용도로 쓰이는 학생은 책상 앞에 앉아도
        학습으로 전환하지 못한다. 그래서 좋은 강사들은 첫 수업에서 *책상의 상태*를 본다. 게임기·과자·핸드폰이
        손에 닿는 곳에 있으면, 의지가 아무리 강해도 학습 모드로의 전환은 매번 비용을 치른다.

        환경 설계는 *물리적*이고 *예측 가능한* 것이어야 한다. *학습할 시간엔 핸드폰을 다른 방에 둔다* 같은
        규칙. *공부할 자리엔 학습용 책만 놓는다* 같은 규칙. 이런 규칙들은 한 번 설정하면 매일의 의지를
        절약한다.

        ## 부모의 역할 — 보조하기, 대신하지 않기

        루틴은 학생의 것이어야 한다. 부모가 *대신* 만들어준 루틴은 부모가 자리를 비우는 순간 무너진다.
        부모의 역할은 *루틴이 자라는 환경*을 만드는 것이지 *루틴 자체*가 되는 것이 아니다. 매일 일곱 시에
        깨워주는 것이 아니라, 일곱 시에 일어나는 *학생의 결정*을 가능하게 하는 환경을 만드는 것.

        구체적으로는: 일곱 시에 *집안 전체*가 학습 모드로 전환되는 신호가 있어야 한다. 거실의 TV가 꺼지고,
        형제가 떠들지 않고, 부엌이 조용해지는 — 그런 환경 신호. 이건 학생에게 *명령*을 내리는 것보다 훨씬
        강력하다. 명령은 의지를 *소모*시키지만, 환경은 의지를 *대체*한다.

        ## 학년별로 다른 적용

        초등 저학년에게는 *책상에 앉는 시간 자체*가 루틴이다. 학습 내용보다도, 같은 시간에 같은 자리에
        앉는다는 사실이 핵심이다. 이 시기의 루틴은 길어야 이십 분이면 충분하다. 짧은 시간을 *완벽히 지키는
        경험*이 평생의 학습 태도를 만든다.

        초등 고학년부터 중학생 초반은 *과목 단위 루틴*으로 옮겨간다. 월요일은 수학, 화요일은 국어 — 식의
        구조. 이 단계에서 학생은 *내가 지금 무엇을 하고 있는지*를 자각하기 시작하고, 그 자각이 학습의
        효율을 끌어올린다. 부모가 가장 도움이 되는 시기이기도 하다 — 학생과 함께 *주간 루틴 표*를 만들고,
        주말마다 *지킨 것*과 *못 지킨 것*을 함께 본다.

        고등학생은 *시험 사이클 루틴*으로 발전한다. 단기 시험과 장기 입시를 함께 보면서 *어떤 주에 무엇을
        해야 하는지*를 자기 스스로 설계하는 단계. 이 단계의 부모 개입은 학생의 자율성을 침범할 수 있어서,
        오히려 *대화의 질*에 집중해야 한다. 잔소리가 아니라, 같이 시간 단위를 점검하는 *동료 검토자*가
        되는 것.

        ## 무너졌을 때 다시 시작하는 법

        루틴은 무너진다. 시험 직전 며칠, 가족 행사, 컨디션 난조 — 매번 무너진다. 무너지는 것이 *실패*가
        아니라, *무너졌을 때 다시 시작하는 방식*이 핵심이다. 가장 흔한 실패는 *내일부터 두 배로 하겠다*는
        결심이다. 하루 빠진 만큼 다음 날 두 시간 더 — 이런 식의 *복구 결심*은 거의 항상 또 한 번의 실패로
        이어진다.

        올바른 복구는 *원래의 루틴을 그대로 재개*하는 것이다. 어제 빠진 한 시간은 잊고, 오늘의 한 시간만
        지키는 것. 빠진 한 시간을 *복구*하려고 두 시간을 결심하는 순간, 새 루틴은 *원래 루틴의 두 배*만큼
        부담스러워지고, 다음 날 또 무너질 가능성이 커진다. 학습의 일관성은 *수치적 누적*이 아니라 *심리적
        리듬*이다. 그 리듬을 깨지 않는 것이 복구의 첫 원칙이다.

        ## 닫는 말

        학습의 본질은 *시간을 많이 쓰는 것*이 아니라 *적게 쓰면서 자주 쓰는 것*이다. 매일 삼십 분의 일관된
        학습이 일주일에 한 번 다섯 시간보다 거의 항상 낫다. 이것은 우리가 데이터에서도 보고, 학생들 자신이
        *나중에* 깨닫는 패턴이다. *나중에*가 아니라 지금부터, 작게 시작하면 된다. 작게 시작한 루틴이 자라는
        모습을 옆에서 지켜보는 것 — 그게 학부모가 할 수 있는 가장 가치 있는 일이다.
        MD;
    }

    /**
     * Phase 1 가이드 3변종 시드. 각 변종은 별도 자식 테마(slug)를 갖고 region=null.
     * 가이드 게이트: body_markdown 가시 길이 ≥ 3000 + verified FAQ ≥ 3.
     *
     * @return int 생성된 노드 수
     */
    private function seedGuides(Author $author): int
    {
        $guidesParent = $this->themes->findOneBy(['slug' => 'guides']);
        if ($guidesParent === null) {
            return 0;
        }

        $created = 0;
        foreach (self::GUIDE_VARIANTS as $variant) {
            $childTheme = $this->themes->findOneBy(['slug' => $variant['slug']]);
            if ($childTheme === null) {
                $childTheme = (new Theme())
                    ->setSlug($variant['slug'])
                    ->setName($variant['name'])
                    ->setParent($guidesParent)
                    ->setDepth(1)
                    ->setDescription(sprintf('Phase 1 가이드 — %s', $variant['name']));
                $this->em->persist($childTheme);
                $this->em->flush();
            }

            // (theme_id, region_id) UNIQUE 충돌 방지 — 이미 있으면 스킵.
            $existing = $this->em->getRepository(ContentNode::class)
                ->findOneBy(['theme' => $childTheme, 'region' => null]);
            if ($existing !== null) {
                continue;
            }

            $this->createGuideNode($childTheme, $author);
            $created++;
        }

        return $created;
    }

    private function createGuideNode(Theme $theme, Author $author): void
    {
        $node = (new ContentNode())
            ->setTheme($theme)
            ->setRegion(null)
            ->setAuthor($author)
            ->setStatus(ContentStatus::Draft)
            ->setIntroText($this->buildGuideIntro($theme))
            ->setBodyTemplate(BodyTemplate::Guide)
            ->setBodyMarkdown($this->buildGuideMarkdown());

        $this->em->persist($node);

        foreach ($this->buildGuideDataPoints($theme) as $dpData) {
            $dp = (new DataPoint())
                ->setNode($node)
                ->setKind($dpData['kind'])
                ->setTitle($dpData['title'])
                ->setValue($dpData['value'])
                ->setSource('데모 시드 — 가이드')
                ->setVerified(true);
            $this->em->persist($dp);
        }

        $this->em->flush();

        $node->setStatus(ContentStatus::Live);
        $this->em->flush();
    }

    private function buildGuideIntro(Theme $theme): string
    {
        return match ($theme->getSlug()) {
            'how-to-choose-tutor' => '강사 선택은 과외 성공의 80%를 결정합니다. 학력·경력·수업 스타일 세 축을 어떻게 비교하고, 첫 수업에서 무엇을 확인해야 하는지 단계별로 정리했습니다.',
            'tutoring-vs-academy' => '과외와 학원 중 어디가 우리 아이에게 맞을까. 비용·진도·집중도·맞춤성 네 축을 데이터로 비교해, 학년과 성향별로 어떤 선택이 합리적인지 정리했습니다.',
            default => '',
        };
    }

    private function buildGuideMarkdown(): string
    {
        // 모든 변종에 공통으로 들어가는 시드 본문 — 가시 길이 3,000자 이상 보장
        // (DB 트리거가 공백 정규화 후 LENGTH 검사). 실제 운영 시에는 변종마다
        // 완전히 다른 톤·구조의 글을 작성해야 하지만, 데모에서는 prose 스타일과
        // 변종별 섹션 분기 동작 확인이 목적.
        return <<<MD
        ## 강사 선택, 무엇을 먼저 봐야 하는가

        많은 학부모가 강사를 고를 때 가장 먼저 학력을 봅니다. 그러나 *학력은 필요 조건이지 충분 조건이 아닙니다.*
        같은 대학을 졸업했어도 누군가는 학생의 사고 흐름을 정확히 진단하고, 누군가는 자기 풀이를 그대로 떠밀듯
        설명합니다. 차이는 학력이 아니라 **수업 설계 능력**에서 나옵니다. 강사 선택의 본질은 *지식의 양*이 아니라
        *지식을 학생에게 전달하는 방식*에 있습니다. 좋은 강사는 자신이 아는 것을 가르치는 것이 아니라, 학생이
        모르는 것을 발견해 그 지점에 정확히 손을 대는 사람입니다.

        실제로 매칭 후 만족도가 높았던 강사들의 공통점은 다음 세 가지입니다.

        - 첫 수업에서 학생의 **현재 위치**를 정확히 진단한다
        - 학생이 *어디서 막히는지*를 짚어내는 질문을 던진다
        - 다음 수업까지의 **구체적 과제**를 합의한다

        이 세 가지는 학력 증명서로는 보이지 않습니다. 그래서 우리는 강사를 매칭한 후에도
        첫 2회 수업을 모니터링하고, 학생·학부모 피드백을 받아 *재매칭이 필요한지*를 판단합니다. 매칭은
        한 번의 결정이 아니라 *학기 내내 이어지는 조율의 과정*이며, 첫 만남에서 모든 것을 결정하려고
        하지 않는 것이 오히려 결과를 좋게 만듭니다.

        ## 첫 수업에서 반드시 확인해야 할 다섯 가지

        강사를 처음 만났을 때 다음을 직접 관찰하세요. 이 다섯 가지는 강사의 *수업 스타일*을 가장 빠르게
        보여주는 신호이며, 학력이나 경력으로는 드러나지 않는 차이를 드러냅니다.

        1. 학생의 **현재 학년 교과서**를 미리 검토하고 왔는가
        2. 수업 첫 10분을 *진단*에 쓰는가, 아니면 곧장 풀이로 들어가는가
        3. 학생이 *틀린 문제*를 다룰 때 **이유를 묻는가**, 정답을 알려주는가
        4. 다음 수업까지의 **과제 분량**이 학생 수준에 맞는가
        5. 수업 후 **요약 메시지**를 남기는가 (학부모 가시성)

        다섯 가지 중 셋 이상이 못 미치면 *강사 교체를 권유합니다.* 첫 수업의 패턴이 이후 3개월의
        흐름을 거의 결정합니다. 어색하거나 불편한 첫 인상을 "익숙해지면 괜찮겠지"로 미루지 마세요.
        강사도 학생도 첫 수업에서 가장 좋은 모습을 보이려 합니다. 그 *최선의 모습*에서 부족한 점이
        보인다면, 일상 수업에서는 더 큰 차이로 벌어질 가능성이 높습니다.

        ## 비용과 효율의 균형

        시간당 단가가 높다고 효율도 높지는 않습니다. 핵심은 **단위 비용당 학습 진도**입니다. 시급 6만원
        강사가 주 2회 두 달 만에 1단원을 끝낸다면, 시급 4만원 강사가 주 3회 두 달 만에 2단원을 끝내는
        것보다 *비용 효율이 떨어집니다.* 시급만 보고 결정하지 말고, **목표 단원 진도에 도달하는
        총 비용**으로 비교하세요. 단가가 낮은 강사가 더 많은 시간을 써서 같은 결과를 내는 경우, 학생이
        지치는 비용도 함께 계산해야 합니다.

        > 예: 같은 "수학 1단원 마스터" 목표에 대해 강사 A는 8회(48만원), 강사 B는 12회(60만원)에
        > 달성. 시급은 B가 낮지만 총비용은 A가 적습니다.

        시간 단위 비용에는 **학생의 시간**도 포함됩니다. 주 3회 두 달은 24시간이고, 주 2회 두 달은
        16시간입니다. 같은 1단원이라면 8시간을 학생이 다른 학습이나 휴식에 쓸 수 있다는 뜻입니다.
        시급이 50% 비싸도 결과가 같다면, 학생의 시간 비용까지 고려해 더 비싼 강사가 합리적인 선택일 수
        있습니다.

        ## 강사 교체가 필요한 신호

        다음 중 두 가지 이상이면 첫 1개월 안에 교체를 검토하세요. 교체 결정을 미루는 가장 흔한 이유는
        *이미 들어간 시간과 비용이 아까워서*인데, 이것은 **매몰 비용 오류**입니다. 잘못된 매칭에서 더
        오래 머무를수록 손해가 커집니다.

        - 학생이 수업 *전날 밤* 부담을 호소한다
        - 수업 후 학생이 *오늘 무엇을 배웠는지* 설명하지 못한다
        - 강사가 *과제 검사*를 건너뛴다
        - 학부모 질문에 답변이 24시간 이상 늦어진다
        - 첫 평가 시험 점수가 *오히려 떨어진다*

        교체는 실패가 아니라 **시장 신호**입니다. 매칭은 본질적으로 *시행착오를 빠르게 줄이는 과정*이고,
        잘못된 매칭에 6개월을 버티는 것보다 첫 달에 교체하는 것이 학생에게 훨씬 유리합니다. 우리는 첫
        4주 안의 교체에 대해서는 별도 매칭 비용 없이 후속 강사를 추천합니다. 이 정책은 학부모가
        *교체를 망설이지 않게* 만드는 안전 장치이며, 실제로 첫 달 교체 후 두 번째 매칭의 만족도가 첫
        매칭보다 높은 경우가 적지 않습니다.

        ## 학부모가 자주 빠지는 세 가지 함정

        매칭 결정 과정에서 학부모가 가장 자주 빠지는 함정이 있습니다. 이름값에 끌리는 것, 첫 인상에
        과하게 의존하는 것, 그리고 *주변 평판*을 무비판적으로 받아들이는 것입니다. 이 세 가지는 모두
        강사 자체보다 *강사를 둘러싼 정보*에 결정을 내맡기는 패턴입니다.

        이름값은 학력으로 대표됩니다. 하지만 앞서 짚었듯 *가르치는 능력*과 *학력*은 서로 다른 변수입니다.
        명문대 출신 강사 중에도 자신이 잘 풀었던 문제만 가르치려는 사람이 있고, 학력은 평범하지만
        학생의 사고 흐름을 정확히 진단하는 사람도 있습니다. 학력은 *후보군을 좁히는 필터*로 쓰되,
        결정 변수로는 쓰지 마세요.

        첫 인상은 강사가 가장 잘 준비한 모습입니다. 첫 수업의 친절함이 일상 수업에서 같은 강도로
        유지되지 않는 경우가 많습니다. 첫 인상이 좋다면 *왜 좋았는지*를 구체적으로 적어두고, 4주 뒤
        그 인상이 유지되는지 다시 확인하세요. 인상은 기록과 비교를 통해서만 객관화됩니다.

        주변 평판, 특히 *우리 아이와 다른 아이의 사례*는 결정에 도움이 되지 않을 때가 많습니다. 학생의
        성향·현재 수준·학습 목표가 다르면 같은 강사라도 결과가 다릅니다. *내 아이*에게 맞는 강사인지를
        직접 확인하는 일은 결국 *내 아이의 첫 수업*을 통해서만 가능합니다.

        ## 마무리 — 결정의 체크리스트

        강사를 결정하기 전 다음 질문에 모두 *예*라고 답할 수 있어야 합니다. 하나라도 불확실하면 결정을
        미루고, 첫 수업을 한 번 더 시도하거나 다른 강사를 추가로 만나보는 것이 좋습니다.

        1. 강사가 학생의 **현재 상태**를 정확히 짚었는가?
        2. *3개월 목표*가 구체적으로 합의되었는가?
        3. 수업 후 *피드백 루프*(메시지·과제 검사)가 정해졌는가?
        4. **시간당 단가**보다 *목표 도달까지의 총 비용*을 비교했는가?
        5. 첫 수업의 분위기가 **학생에게 편안**했는가?

        모두 *예*가 아니라면 결정을 미루세요. 좋은 매칭은 *서두르는 것*보다 *맞는 것*이 우선입니다.
        한 학기를 함께 갈 강사를 고르는 일이고, 며칠을 더 들여 더 잘 맞는 사람을 찾는 것이 *몇 달의
        잘못된 시간보다* 훨씬 가치 있습니다. 매칭 자체는 무료이며, 첫 수업이 맞지 않으면 환불과 재매칭
        모두 가능합니다. 결정의 부담을 *비용*이 아니라 *맞음*에 두세요.
        MD;
    }

    /**
     * @return list<array{kind: DataPointKind, title: string, value: mixed}>
     */
    private function buildGuideDataPoints(Theme $theme): array
    {
        // 가이드 게이트는 verified FAQ ≥ 3을 요구. 4건을 기본 + 슬러그별 보조 DataPoint.
        $faq = [
            [
                'kind' => DataPointKind::Faq,
                'title' => '강사 교체는 언제 하는 게 좋나요?',
                'value' => '첫 4주 안에 다섯 가지 신호 중 두 개 이상이 보이면 교체를 권유합니다. 6개월을 버티는 것보다 빠른 교체가 학생에게 유리합니다.',
            ],
            [
                'kind' => DataPointKind::Faq,
                'title' => '시급이 높으면 더 좋은가요?',
                'value' => '시급이 아니라 목표 단원에 도달하는 총비용으로 비교하세요. 시급 6만원 강사가 8회에 끝낸 것이 시급 4만원 강사가 12회에 끝낸 것보다 저렴합니다.',
            ],
            [
                'kind' => DataPointKind::Faq,
                'title' => '첫 수업에서 무엇을 봐야 하나요?',
                'value' => '진단·질문·과제·피드백·요약 메시지의 다섯 축. 셋 이상이 부족하면 다음 수업 전에 교체를 검토하세요.',
            ],
            [
                'kind' => DataPointKind::Faq,
                'title' => '매칭 비용이 발생하나요?',
                'value' => '매칭 자체는 무료이며, 첫 수업 후 마음에 들지 않으면 100% 환불해드립니다.',
            ],
        ];

        $extras = match ($theme->getSlug()) {
            'tutoring-vs-academy' => [
                [
                    'kind' => DataPointKind::Comparison,
                    'title' => '과외 vs 학원 — 평균 시간당 단가',
                    'value' => '과외 4.5~7만원, 학원 1.5~3만원/시간 (시간당 단가만 보면 학원 우위, 1:1 집중도와 진도 맞춤은 과외 우위).',
                ],
                [
                    'kind' => DataPointKind::Comparison,
                    'title' => '과외 vs 학원 — 진도 맞춤도',
                    'value' => '과외는 100% 학생 페이스, 학원은 반 평균 페이스. 학생 수준이 평균과 차이가 클수록 과외 효율이 큼.',
                ],
                [
                    'kind' => DataPointKind::Comparison,
                    'title' => '과외 vs 학원 — 동기 부여',
                    'value' => '학원은 또래 경쟁, 과외는 1:1 관계. 외향형은 학원, 내향형이거나 학습 자존감이 낮은 학생은 과외가 효과적.',
                ],
            ],
            'how-to-choose-tutor' => [
                [
                    'kind' => DataPointKind::Quantitative,
                    'title' => '첫 매칭 후 만족도',
                    'value' => '92%',
                ],
                [
                    'kind' => DataPointKind::Comparison,
                    'title' => '시급보다 중요한 지표',
                    'value' => '단위 비용당 학습 진도(목표 단원 도달까지의 총비용)가 시급보다 효율을 더 잘 예측합니다.',
                ],
            ],
            default => [
                [
                    'kind' => DataPointKind::Quantitative,
                    'title' => '평균 매칭 소요',
                    'value' => '5분',
                ],
            ],
        };

        return array_merge($faq, $extras);
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

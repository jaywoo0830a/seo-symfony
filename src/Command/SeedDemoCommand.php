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

    /** 매트릭스(지역별) 시드 대상에서 제외하는 테마 slug — 가이드 허브는 region=null만. */
    private const MATRIX_EXCLUDED_SLUGS = ['guides'];

    /** 가이드 3종 시연 (longform/comparison/faq) — body_template, slug, 변종을 묶음. */
    private const GUIDE_VARIANTS = [
        ['slug' => 'how-to-choose-tutor', 'name' => '과외 강사 선택법', 'template' => BodyTemplate::GuideLongform],
        ['slug' => 'tutoring-vs-academy', 'name' => '과외 vs 학원 비교', 'template' => BodyTemplate::GuideComparison],
        ['slug' => 'tutoring-faq', 'name' => '과외 자주 묻는 질문', 'template' => BodyTemplate::GuideFaq],
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

        $io->success(sprintf('매트릭스 노드 %d개 신규 + 가이드 %d개 신규 생성', $created, $guideCount));
        return Command::SUCCESS;
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

            $this->createGuideNode($childTheme, $author, $variant['template']);
            $created++;
        }

        return $created;
    }

    private function createGuideNode(Theme $theme, Author $author, BodyTemplate $template): void
    {
        $node = (new ContentNode())
            ->setTheme($theme)
            ->setRegion(null)
            ->setAuthor($author)
            ->setStatus(ContentStatus::Draft)
            ->setIntroText($this->buildGuideIntro($template))
            ->setBodyTemplate($template)
            ->setBodyMarkdown($this->buildGuideMarkdown());

        $this->em->persist($node);

        foreach ($this->buildGuideDataPoints($template) as $dpData) {
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

    private function buildGuideIntro(BodyTemplate $template): string
    {
        return match ($template) {
            BodyTemplate::GuideLongform => '강사 선택은 과외 성공의 80%를 결정합니다. 학력·경력·수업 스타일 세 축을 어떻게 비교하고, 첫 수업에서 무엇을 확인해야 하는지 단계별로 정리했습니다.',
            BodyTemplate::GuideComparison => '과외와 학원 중 어디가 우리 아이에게 맞을까. 비용·진도·집중도·맞춤성 네 축을 데이터로 비교해, 학년과 성향별로 어떤 선택이 합리적인지 정리했습니다.',
            BodyTemplate::GuideFaq => '과외 시작 전 가장 자주 받는 질문 모음. 비용 정산, 강사 교체, 환불 정책, 첫 수업 진행 방식까지 운영팀이 직접 답변합니다.',
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
    private function buildGuideDataPoints(BodyTemplate $template): array
    {
        // 가이드 게이트는 verified FAQ ≥ 3을 요구. 4건을 기본 + 변종별 보조 DataPoint.
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

        $extras = match ($template) {
            BodyTemplate::GuideComparison => [
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
            BodyTemplate::GuideLongform => [
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

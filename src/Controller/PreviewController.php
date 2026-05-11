<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Author;
use App\Entity\ContentNode;
use App\Entity\DataPoint;
use App\Entity\Enum\BodyTemplate;
use App\Entity\Enum\ContentStatus;
use App\Entity\Enum\DataPointKind;
use App\Entity\Region;
use App\Entity\Theme;
use App\Service\UrlBuilder;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * 디자인 프리뷰 — DB 무관, 시드 무관, publish gate 무관.
 *
 * 페이지 템플릿(matrix shell, prose 4종, home 등)을 *메모리 객체*로 만든
 * fake context로 즉시 렌더. ContentNode 영속화 X — 트리거 비발동.
 *
 * 보안:
 *   - /admin/* 는 security.yaml access_control 로 ROLE_ADMIN 필수
 *   - 모든 응답에 X-Robots-Tag: noindex 추가
 *
 * 라우트:
 *   /admin/preview/                  — 인덱스 (링크 목록)
 *   /admin/preview/home              — home.html.twig
 *   /admin/preview/matrix/{depth}    — matrix shell, depth=0..3
 *   /admin/preview/{template}        — guide | essay | report | case
 */
#[Route('/admin/preview', name: 'admin_preview_')]
final class PreviewController extends AbstractController
{
    public function __construct(
        private readonly UrlBuilder $urls,
        private readonly Environment $twig,
    ) {}

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->noindex($this->render('dashboard/preview_index.html.twig'));
    }

    #[Route('/home', name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->noindex($this->render('public/home.html.twig', [
            'themes' => $this->fakeRootThemes(),
        ]));
    }

    #[Route('/matrix/{depth}', name: 'matrix', requirements: ['depth' => '[0-3]'], methods: ['GET'])]
    public function matrix(int $depth): Response
    {
        return $this->noindex($this->render('public/node.html.twig', $this->buildMatrixContext($depth)));
    }

    #[Route('/{template}', name: 'prose', requirements: ['template' => 'guide|essay|report|case'], methods: ['GET'])]
    public function prose(string $template): Response
    {
        $bt = match ($template) {
            'guide'  => BodyTemplate::Guide,
            'essay'  => BodyTemplate::Essay,
            'report' => BodyTemplate::Report,
            'case'   => BodyTemplate::CaseStudy,
        };
        return $this->noindex($this->render('public/node.html.twig', $this->buildProseContext($bt)));
    }

    // ─── helpers ──────────────────────────────────────────────────────

    private function noindex(Response $r): Response
    {
        $r->headers->set('X-Robots-Tag', 'noindex, nofollow');
        return $r;
    }

    /**
     * @return list<Theme>
     */
    private function fakeRootThemes(): array
    {
        return [
            (new Theme())->setSlug('tutoring')->setName('초등 과외')->setDepth(0)
                ->setDescription('초등 1~6학년 전 과목 1:1 학습. 지역별 학습 정보가 모이는 매트릭스 테마.'),
            (new Theme())->setSlug('guides')->setName('학습 가이드')->setDepth(0)
                ->setDescription('학년별 학습 로드맵, 과목별 자기주도 학습법 등 정보형 가이드.'),
            (new Theme())->setSlug('reports')->setName('데이터 리포트')->setDepth(0)
                ->setDescription('자체 수집·정리한 초등 학습 데이터 리포트.'),
            (new Theme())->setSlug('cases')->setName('사례 연구')->setDepth(0)
                ->setDescription('익명화한 실제 학습 변화 사례.'),
        ];
    }

    private function fakeAuthor(): Author
    {
        return (new Author())
            ->setSlug('preview-author')
            ->setRealName('학습 상담 데스크')
            ->setCredentials('초등 학습 상담 — 진단·매칭·학습 동선 설계 담당.')
            ->setBio('미리보기용 가짜 작성자.')
            ->setVerifiedAt(new \DateTimeImmutable('2026-01-01'));
    }

    private function fakeDP(DataPointKind $kind, string $title, mixed $value): DataPoint
    {
        return (new DataPoint())
            ->setKind($kind)
            ->setTitle($title)
            ->setValue($value)
            ->setVerified(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMatrixContext(int $depth): array
    {
        $tutoring = (new Theme())->setSlug('tutoring')->setName('초등 과외')->setDepth(0);

        $kr = (new Region())->setSlug('kr')->setName('대한민국')->setDepth(0);
        $region = null;
        $regionLabel = '전국';

        if ($depth >= 1) {
            $region = (new Region())->setSlug('seoul')->setName('서울특별시')->setDepth(1)->setParent($kr);
            $regionLabel = '서울';
        }
        if ($depth >= 2) {
            $region = (new Region())->setSlug('gangnam-gu')->setName('강남구')->setDepth(2)->setParent($region);
            $regionLabel = '강남구';
        }
        if ($depth >= 3) {
            $region = (new Region())->setSlug('daechi-dong')->setName('대치동')->setDepth(3)->setParent($region);
            $regionLabel = '대치동';
        }

        $author = $this->fakeAuthor();
        $now = new \DateTimeImmutable('2026-04-15');

        $node = (new ContentNode())
            ->setTheme($tutoring)
            ->setRegion($region)
            ->setAuthor($author)
            ->setStatus(ContentStatus::Live)
            ->setIntroText("{$regionLabel} 지역의 초등 학습 정보. 학습 환경·진단 데이터·자치구 비교까지. 진단 결과를 기반으로 한 학습 동선 설계.");
        $node->setFirstPublishedAt($now);
        $node->setLastReviewAt($now);

        $h1 = $depth === 0 ? '초등 1:1 학습' : "{$regionLabel} 초등 1:1 학습";
        $children = $this->fakeMatrixChildren($depth, $tutoring, $region, $author);

        return [
            'node' => $node,
            'url' => '/admin/preview/matrix/' . $depth,
            'h1' => $h1,
            'template' => BodyTemplate::Matrix,
            'body_html' => null,
            'template_override' => null,
            'matrix_template' => $this->twig->getLoader()->exists('public/matrix/tutoring.html.twig')
                ? 'public/matrix/tutoring.html.twig'
                : 'public/_matrix.html.twig',
            'byKind' => $this->fakeByKindMatrix($regionLabel),
            'parent' => null,
            'siblings' => $depth === 3 ? $children : [],
            'children' => $depth < 3 ? $children : [],
            'theme_children' => [],
            'theme_siblings' => [],
            'urls' => $this->urls,
            'crumbs' => $this->fakeCrumbs($depth, $regionLabel, $tutoring),
            'json_ld' => '{}',
            'breadcrumbs_jsonld' => '{}',
        ];
    }

    /**
     * @return array<string, list<DataPoint>>
     */
    private function fakeByKindMatrix(string $regionLabel): array
    {
        return [
            'quantitative' => [
                $this->fakeDP(DataPointKind::Quantitative, '매칭 가능 지도자', ['figure' => '142명', 'label' => '검증 완료']),
                $this->fakeDP(DataPointKind::Quantitative, '평균 학습 시작', ['figure' => '7일', 'label' => '상담 → 시작']),
                $this->fakeDP(DataPointKind::Quantitative, '진단 정확도', ['figure' => '94%', 'label' => '학부모 응답']),
                $this->fakeDP(DataPointKind::Quantitative, '재매칭 비율', ['figure' => '14%', 'label' => '4주 시점']),
            ],
            'qualitative' => [
                $this->fakeDP(DataPointKind::Qualitative, '주요 학습 환경', ['초등학교', '학원가', '도서관', '문화센터']),
                $this->fakeDP(DataPointKind::Qualitative, '인기 과목', ['수학', '영어', '국어', '독서·논술']),
            ],
            'comparison' => [
                $this->fakeDP(DataPointKind::Comparison, '인접 지역 대비', "{$regionLabel} 평균은 인접 지역과 약 6% 차이."),
            ],
            'case' => [],
            'faq' => [
                $this->fakeDP(DataPointKind::Faq, "{$regionLabel}에서 방문 수업 가능한가요?", '네, 방문·화상 모두 지원합니다. 학생 학년·집중도·생활 패턴에 따라 적합한 형태를 함께 정합니다.'),
                $this->fakeDP(DataPointKind::Faq, '학년 제한이 있나요?', '초등 1~6학년 전 학년 가능합니다.'),
                $this->fakeDP(DataPointKind::Faq, '진단검사는 어떻게 진행되나요?', '약 30분간 학생의 학습 상태·성향·습관을 분석합니다.'),
            ],
        ];
    }

    /**
     * @return list<ContentNode>
     */
    private function fakeMatrixChildren(int $depth, Theme $theme, ?Region $currentRegion, Author $author): array
    {
        $childRegions = match ($depth) {
            0 => [['seoul', '서울특별시', 1], ['gyeonggi', '경기도', 1], ['busan', '부산광역시', 1], ['incheon', '인천광역시', 1]],
            1 => [['gangnam-gu', '강남구', 2], ['seocho-gu', '서초구', 2], ['songpa-gu', '송파구', 2], ['mapo-gu', '마포구', 2], ['yongsan-gu', '용산구', 2], ['gangdong-gu', '강동구', 2]],
            2 => [['apgujeong-dong', '압구정동', 3], ['cheongdam-dong', '청담동', 3], ['daechi-dong', '대치동', 3], ['yeoksam-dong', '역삼동', 3]],
            3 => [['apgujeong-dong', '압구정동', 3], ['cheongdam-dong', '청담동', 3], ['yeoksam-dong', '역삼동', 3]], // siblings
            default => [],
        };

        $parentRegion = $depth === 3 ? $currentRegion?->getParent() : $currentRegion;
        $kr = (new Region())->setSlug('kr')->setName('대한민국')->setDepth(0);
        $parentRegion = $parentRegion ?? $kr;

        $children = [];
        foreach ($childRegions as [$slug, $name, $childDepth]) {
            $r = (new Region())->setSlug($slug)->setName($name)->setDepth($childDepth)->setParent($parentRegion);
            $node = (new ContentNode())
                ->setTheme($theme)
                ->setRegion($r)
                ->setAuthor($author)
                ->setStatus(ContentStatus::Live);
            $children[] = $node;
        }
        return $children;
    }

    /**
     * @return list<array{label: string, url: string, current: bool}>
     */
    private function fakeCrumbs(int $depth, string $regionLabel, Theme $theme): array
    {
        $crumbs = [
            ['label' => '홈', 'url' => '/', 'current' => false],
            ['label' => $theme->getName(), 'url' => '/' . $theme->getSlug() . '/', 'current' => $depth === 0],
        ];
        if ($depth >= 1) {
            $crumbs[] = ['label' => $regionLabel, 'url' => '#', 'current' => true];
        }
        return $crumbs;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProseContext(BodyTemplate $bt): array
    {
        [$rootSlug, $rootName, $childSlug, $childName] = match ($bt) {
            BodyTemplate::Guide     => ['guides', '학습 가이드', 'sample-guide', '초등 학년별 학습 로드맵 (샘플)'],
            BodyTemplate::Essay     => ['contents', '학습 콘텐츠', 'sample-essay', '학습 루틴의 원칙 (샘플)'],
            BodyTemplate::Report    => ['reports', '데이터 리포트', 'sample-report', '2026 1분기 초등 학습 동향 (샘플)'],
            BodyTemplate::CaseStudy => ['cases', '사례 연구', 'sample-case', '학습 루틴 변경 사례 (샘플)'],
            default                 => ['guides', '학습 가이드', 'sample', '샘플'],
        };

        $rootTheme = (new Theme())->setSlug($rootSlug)->setName($rootName)->setDepth(0);
        $childTheme = (new Theme())->setSlug($childSlug)->setName($childName)->setDepth(1)->setParent($rootTheme);
        $author = $this->fakeAuthor();
        $now = new \DateTimeImmutable('2026-04-15');

        $node = (new ContentNode())
            ->setTheme($childTheme)
            ->setRegion(null)
            ->setAuthor($author)
            ->setStatus(ContentStatus::Live)
            ->setIntroText('미리보기 — 본문 디자인 시각 검증용. 실제 콘텐츠는 진짜 데이터로 교체됩니다.')
            ->setBodyTemplate($bt)
            ->setBodyMarkdown($this->fakeMarkdown());
        $node->setFirstPublishedAt($now);
        $node->setLastReviewAt($now);

        return [
            'node' => $node,
            'url' => '/admin/preview/' . strtolower($bt->name),
            'h1' => $childName,
            'template' => $bt,
            'body_html' => $this->renderMarkdown($node->getBodyMarkdown()),
            'template_override' => null,
            'matrix_template' => 'public/_matrix.html.twig',
            'byKind' => $this->fakeByKindProse($bt),
            'parent' => null,
            'siblings' => [],
            'children' => [],
            'theme_children' => [],
            'theme_siblings' => [],
            'urls' => $this->urls,
            'crumbs' => [
                ['label' => '홈', 'url' => '/', 'current' => false],
                ['label' => $rootName, 'url' => '/' . $rootSlug . '/', 'current' => false],
                ['label' => $childName, 'url' => '#', 'current' => true],
            ],
            'json_ld' => '{}',
            'breadcrumbs_jsonld' => '{}',
        ];
    }

    /**
     * @return array<string, list<DataPoint>>
     */
    private function fakeByKindProse(BodyTemplate $bt): array
    {
        $base = ['quantitative' => [], 'qualitative' => [], 'comparison' => [], 'case' => [], 'faq' => []];

        if ($bt === BodyTemplate::Guide) {
            $base['faq'] = [
                $this->fakeDP(DataPointKind::Faq, '진단검사는 어떻게 진행되나요?', '약 30분간 학생의 학습 상태·성향·습관을 분석합니다.'),
                $this->fakeDP(DataPointKind::Faq, '몇 학년부터 가능한가요?', '초등 1~6학년 전 학년 가능합니다.'),
                $this->fakeDP(DataPointKind::Faq, '시작은 어떻게 하나요?', '전화 상담으로 시작합니다. 약 10분간 학년·과목·고민을 듣습니다.'),
            ];
        }
        if ($bt === BodyTemplate::Report) {
            $base['quantitative'] = [
                $this->fakeDP(DataPointKind::Quantitative, '집계 표본', '1,247명'),
                $this->fakeDP(DataPointKind::Quantitative, '학년 분포', '1~6학년 균등'),
                $this->fakeDP(DataPointKind::Quantitative, '진단 정확도', '94%'),
                $this->fakeDP(DataPointKind::Quantitative, '학습 시작 평균', '7일'),
            ];
        }
        if ($bt === BodyTemplate::CaseStudy) {
            $base['quantitative'] = [
                $this->fakeDP(DataPointKind::Quantitative, '학기 시작 점수', '62점'),
                $this->fakeDP(DataPointKind::Quantitative, '학기 말 점수', '88점'),
            ];
            $base['qualitative'] = [
                $this->fakeDP(DataPointKind::Qualitative, '학생 프로필', [
                    '학년' => '초등 3학년',
                    '거주' => '서울',
                    '학부모 동의' => '익명화 후 발행 동의',
                ]),
            ];
        }
        return $base;
    }

    private function fakeMarkdown(): string
    {
        return <<<MD
        ## 첫 번째 섹션

        본문 첫 단락. 디자인 시각 확인용 placeholder. 실제 콘텐츠는 본인이 직접 작성합니다.
        진단검사 결과를 토대로 학생에게 맞는 학습 동선을 설계하는 과정에 대해 다룹니다.

        ## 두 번째 섹션

        ### 하위 제목

        본문 hierarchies와 prose 톤을 시각 확인할 수 있습니다. *기울임*과 **굵게**, 그리고
        링크 스타일도 함께 검증됩니다. 한국어 본문에서 Noto Serif KR 폰트가 어떻게 표시되는지
        확인해주세요.

        > 인용 박스 시각. 강조 라인. 학습교재 권위 톤이 어떻게 보이는지.

        ## 세 번째 섹션

        목록 구조도 시각 확인:

        - 항목 1 — 진단으로 시작
        - 항목 2 — 학습 동선 설계
        - 항목 3 — 지속 검토와 조정

        본문 길이는 publish gate 무관 (메모리 객체라 트리거 안 발동). 디자인만 확인하시면 됩니다.
        MD;
    }

    private function renderMarkdown(string $markdown): string
    {
        return (string) (new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]))->convert($markdown);
    }
}

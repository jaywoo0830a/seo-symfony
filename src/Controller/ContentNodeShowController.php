<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentNode;
use App\Entity\Enum\BodyTemplate;
use App\Entity\Enum\ContentStatus;
use App\Entity\Enum\DataPointKind;
use App\Repository\ContentNodeRepository;
use App\Service\UrlBuilder;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/nodes/{id}', name: 'admin_nodes_show', methods: ['GET'], requirements: ['id' => '\d+'])]
final class ContentNodeShowController extends AbstractController
{
    #[Template('dashboard/nodes/show.html.twig')]
    public function __invoke(
        #[MapEntity(id: 'id')] ContentNode $node,
        ContentNodeRepository $nodes,
        UrlBuilder $urls,
    ): array {
        $dataPointsByKind = [];
        foreach (DataPointKind::cases() as $kind) {
            $dataPointsByKind[$kind->value] = [];
        }
        foreach ($node->getDataPoints() as $dp) {
            $dataPointsByKind[$dp->getKind()->value][] = $dp;
        }

        $parent = $nodes->findParentOf($node);
        $children = $nodes->findChildrenOf($node);

        return [
            'node' => $node,
            'url' => $urls->build($node),
            'parent' => $parent,
            'children' => $children,
            'dataPointsByKind' => $dataPointsByKind,
            'derivedTemplate' => $this->deriveTemplate($node),
            'gate' => $this->checkPublishGate($node, $parent),
        ];
    }

    private function deriveTemplate(ContentNode $node): BodyTemplate
    {
        return $node->getBodyTemplate() ?? BodyTemplate::Matrix;
    }

    /**
     * @return list<array{label: string, met: bool}>
     */
    private function checkPublishGate(ContentNode $node, ?ContentNode $parent): array
    {
        $intro = $node->getIntroText();
        $author = $node->getAuthor();

        return [
            ['label' => '인트로 텍스트 작성', 'met' => $intro !== null && trim($intro) !== ''],
            ['label' => '검증된 데이터 포인트 ≥ 5', 'met' => $node->getDataCount() >= 5],
            ['label' => '인증된 작성자', 'met' => $author !== null && $author->isVerified()],
            ['label' => '부모 노드 라이브', 'met' => $node->getRegion() === null || ($parent !== null && $parent->getStatus() === ContentStatus::Live)],
        ];
    }
}

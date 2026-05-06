<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentNode;
use App\Entity\DataPoint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/admin/nodes/{nodeId}/datapoints/{id}/delete',
    name: 'admin_datapoints_delete',
    methods: ['POST'],
    requirements: ['nodeId' => '\d+', 'id' => '\d+'],
)]
final class DataPointDeleteController extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'nodeId')] ContentNode $node,
        #[MapEntity(id: 'id')] DataPoint $datapoint,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if ($datapoint->getNode()->getId() !== $node->getId()) {
            throw new NotFoundHttpException('데이터 포인트가 노드에 속하지 않습니다.');
        }

        if (!$this->isCsrfTokenValid('delete-dp-' . $datapoint->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($datapoint);
        $em->flush();
        $this->addFlash('success', '데이터 포인트를 삭제했습니다.');

        return $this->redirectToRoute('admin_nodes_show', ['id' => $node->getId()]);
    }
}

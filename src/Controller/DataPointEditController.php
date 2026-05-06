<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentNode;
use App\Entity\DataPoint;
use App\Form\DataPointType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/admin/nodes/{nodeId}/datapoints/{id}/edit',
    name: 'admin_datapoints_edit',
    methods: ['GET', 'POST'],
    requirements: ['nodeId' => '\d+', 'id' => '\d+'],
)]
final class DataPointEditController extends AbstractController
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

        $datapoint->setUpdatedAt(new \DateTimeImmutable());
        $form = $this->createForm(DataPointType::class, $datapoint);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', '데이터 포인트를 저장했습니다.');

            return $this->redirectToRoute('admin_nodes_show', ['id' => $node->getId()]);
        }

        return $this->render('dashboard/datapoints/edit.html.twig', [
            'node' => $node,
            'datapoint' => $datapoint,
            'form' => $form,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentNode;
use App\Entity\DataPoint;
use App\Entity\Enum\DataPointKind;
use App\Form\DataPointType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/admin/nodes/{nodeId}/datapoints/new',
    name: 'admin_datapoints_new',
    methods: ['GET', 'POST'],
    requirements: ['nodeId' => '\d+'],
)]
final class DataPointNewController extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'nodeId')] ContentNode $node,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $datapoint = (new DataPoint())->setNode($node);

        // Optional ?kind= preset from "+ 추가" button on node show page
        $kindParam = $request->query->get('kind');
        if (is_string($kindParam) && ($kind = DataPointKind::tryFrom($kindParam)) !== null) {
            $datapoint->setKind($kind);
        }

        $form = $this->createForm(DataPointType::class, $datapoint);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($datapoint);
            $em->flush();
            $this->addFlash('success', '데이터 포인트를 추가했습니다.');

            return $this->redirectToRoute('admin_nodes_show', ['id' => $node->getId()]);
        }

        return $this->render('dashboard/datapoints/new.html.twig', [
            'node' => $node,
            'form' => $form,
        ]);
    }
}

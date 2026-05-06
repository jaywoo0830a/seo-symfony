<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentNode;
use App\Form\ContentNodeType;
use App\Util\DbError;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/nodes/{id}/edit', name: 'admin_nodes_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
final class ContentNodeEditController extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] ContentNode $node,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $form = $this->createForm(ContentNodeType::class, $node);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->flush();
                $this->addFlash('success', '노드를 저장했습니다.');

                return $this->redirectToRoute('admin_nodes_show', ['id' => $node->getId()]);
            } catch (DbalException $e) {
                $this->addFlash('error', '저장 실패: ' . DbError::summarise($e));
            }
        }

        return $this->render('dashboard/nodes/edit.html.twig', [
            'node' => $node,
            'form' => $form,
        ]);
    }
}

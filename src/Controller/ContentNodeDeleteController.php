<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentNode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/nodes/{id}/delete', name: 'admin_nodes_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
final class ContentNodeDeleteController extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] ContentNode $node,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('delete-node-' . $node->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($node);
        $em->flush();
        $this->addFlash('success', '노드를 삭제했습니다.');

        return $this->redirectToRoute('admin_nodes');
    }
}

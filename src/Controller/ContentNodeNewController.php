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
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/nodes/new', name: 'admin_nodes_new', methods: ['GET', 'POST'])]
final class ContentNodeNewController extends AbstractController
{
    public function __invoke(Request $request, EntityManagerInterface $em): Response
    {
        $node = new ContentNode();
        $form = $this->createForm(ContentNodeType::class, $node);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->persist($node);
                $em->flush();
                $this->addFlash('success', '노드를 생성했습니다.');

                return $this->redirectToRoute('admin_nodes_show', ['id' => $node->getId()]);
            } catch (DbalException $e) {
                $this->addFlash('error', '저장 실패: ' . DbError::summarise($e));
            }
        }

        return $this->render('dashboard/nodes/new.html.twig', ['form' => $form]);
    }
}

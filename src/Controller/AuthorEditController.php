<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Author;
use App\Form\AuthorType;
use App\Util\DbError;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/authors/{id}/edit', name: 'admin_authors_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
final class AuthorEditController extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Author $author,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $form = $this->createForm(AuthorType::class, $author);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->flush();
                $this->addFlash('success', '작성자를 저장했습니다.');

                return $this->redirectToRoute('admin_authors_show', ['id' => $author->getId()]);
            } catch (DbalException $e) {
                $this->addFlash('error', '저장 실패: ' . DbError::summarise($e));
            }
        }

        return $this->render('dashboard/authors/edit.html.twig', [
            'author' => $author,
            'form' => $form,
        ]);
    }
}

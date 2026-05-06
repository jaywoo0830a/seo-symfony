<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Author;
use App\Util\DbError;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/authors/{id}/delete', name: 'admin_authors_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
final class AuthorDeleteController extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Author $author,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('delete-author-' . $author->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $em->remove($author);
            $em->flush();
            $this->addFlash('success', '작성자를 삭제했습니다.');

            return $this->redirectToRoute('admin_authors');
        } catch (DbalException $e) {
            $this->addFlash('error', '삭제 실패: ' . DbError::summarise($e));

            return $this->redirectToRoute('admin_authors_show', ['id' => $author->getId()]);
        }
    }
}

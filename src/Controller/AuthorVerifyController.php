<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Author;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/authors/{id}/verify', name: 'admin_authors_verify', methods: ['POST'], requirements: ['id' => '\d+'])]
final class AuthorVerifyController extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Author $author,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('verify-author-' . $author->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $author->setVerifiedAt($author->isVerified() ? null : new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', $author->isVerified() ? '작성자를 인증했습니다.' : '인증을 해제했습니다.');

        return $this->redirectToRoute('admin_authors_show', ['id' => $author->getId()]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Author;
use App\Form\AuthorType;
use App\Util\DbError;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/authors/new', name: 'admin_authors_new', methods: ['GET', 'POST'])]
final class AuthorNewController extends AbstractController
{
    public function __invoke(Request $request, EntityManagerInterface $em): Response
    {
        $author = new Author();
        $form = $this->createForm(AuthorType::class, $author);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->persist($author);
                $em->flush();
                $this->addFlash('success', '작성자를 생성했습니다.');

                return $this->redirectToRoute('admin_authors_show', ['id' => $author->getId()]);
            } catch (DbalException $e) {
                $this->addFlash('error', '저장 실패: ' . DbError::summarise($e));
            }
        }

        return $this->render('dashboard/authors/new.html.twig', ['form' => $form]);
    }
}

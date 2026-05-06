<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Theme;
use App\Form\ThemeType;
use App\Util\DbError;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/themes/new', name: 'admin_themes_new', methods: ['GET', 'POST'])]
final class ThemeNewController extends AbstractController
{
    public function __invoke(Request $request, EntityManagerInterface $em): Response
    {
        $theme = new Theme();
        $form = $this->createForm(ThemeType::class, $theme);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $theme->setDepth($theme->getParent() ? $theme->getParent()->getDepth() + 1 : 0);

            try {
                $em->persist($theme);
                $em->flush();
                $this->addFlash('success', '테마를 생성했습니다.');

                return $this->redirectToRoute('admin_themes_show', ['id' => $theme->getId()]);
            } catch (DbalException $e) {
                $this->addFlash('error', '저장 실패: ' . DbError::summarise($e));
            }
        }

        return $this->render('dashboard/themes/new.html.twig', ['form' => $form]);
    }
}

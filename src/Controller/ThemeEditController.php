<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Theme;
use App\Form\ThemeType;
use App\Util\DbError;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/themes/{id}/edit', name: 'admin_themes_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
final class ThemeEditController extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Theme $theme,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $form = $this->createForm(ThemeType::class, $theme, ['for_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->flush();
                $this->addFlash('success', '테마를 저장했습니다.');

                return $this->redirectToRoute('admin_themes_show', ['id' => $theme->getId()]);
            } catch (DbalException $e) {
                $this->addFlash('error', '저장 실패: ' . DbError::summarise($e));
            }
        }

        return $this->render('dashboard/themes/edit.html.twig', [
            'theme' => $theme,
            'form' => $form,
        ]);
    }
}

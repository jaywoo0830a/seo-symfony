<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Theme;
use App\Util\DbError;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/themes/{id}/delete', name: 'admin_themes_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
final class ThemeDeleteController extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Theme $theme,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('delete-theme-' . $theme->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $em->remove($theme);
            $em->flush();
            $this->addFlash('success', '테마를 삭제했습니다.');

            return $this->redirectToRoute('admin_themes');
        } catch (DbalException $e) {
            $this->addFlash('error', '삭제 실패: ' . DbError::summarise($e));

            return $this->redirectToRoute('admin_themes_show', ['id' => $theme->getId()]);
        }
    }
}

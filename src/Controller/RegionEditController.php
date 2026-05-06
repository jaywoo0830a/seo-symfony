<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Region;
use App\Form\RegionType;
use App\Util\DbError;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/regions/{id}/edit', name: 'admin_regions_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
final class RegionEditController extends AbstractController
{
    public function __invoke(
        #[MapEntity(id: 'id')] Region $region,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $form = $this->createForm(RegionType::class, $region, ['for_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->flush();
                $this->addFlash('success', '지역을 저장했습니다.');

                return $this->redirectToRoute('admin_regions_show', ['id' => $region->getId()]);
            } catch (DbalException $e) {
                $this->addFlash('error', '저장 실패: ' . DbError::summarise($e));
            }
        }

        return $this->render('dashboard/regions/edit.html.twig', [
            'region' => $region,
            'form' => $form,
        ]);
    }
}

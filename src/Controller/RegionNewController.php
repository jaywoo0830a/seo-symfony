<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Region;
use App\Form\RegionType;
use App\Util\DbError;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/regions/new', name: 'admin_regions_new', methods: ['GET', 'POST'])]
final class RegionNewController extends AbstractController
{
    public function __invoke(Request $request, EntityManagerInterface $em): Response
    {
        $region = new Region();
        $form = $this->createForm(RegionType::class, $region);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $region->setDepth($region->getParent() ? $region->getParent()->getDepth() + 1 : 0);

            try {
                $em->persist($region);
                $em->flush();
                $this->addFlash('success', '지역을 생성했습니다.');

                return $this->redirectToRoute('admin_regions_show', ['id' => $region->getId()]);
            } catch (DbalException $e) {
                $this->addFlash('error', '저장 실패: ' . DbError::summarise($e));
            }
        }

        return $this->render('dashboard/regions/new.html.twig', ['form' => $form]);
    }
}

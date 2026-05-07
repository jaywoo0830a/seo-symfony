<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Repository\ThemeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'public_home', methods: ['GET'])]
final class PublicHomeController extends AbstractController
{
    public function __invoke(ThemeRepository $themes): Response
    {
        $response = new Response();
        $response->setPublic();
        $response->setMaxAge(300);
        $response->setSharedMaxAge(1800);

        return $this->render('public/home.html.twig', [
            'themes' => $themes->findRootThemes(),
        ], $response);
    }
}

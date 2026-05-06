<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Repository\ThemeRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'public_home', methods: ['GET'])]
final class PublicHomeController extends AbstractController
{
    #[Template('public/home.html.twig')]
    public function __invoke(ThemeRepository $themes): array
    {
        return [
            'themes' => $themes->findRootThemes(),
        ];
    }
}

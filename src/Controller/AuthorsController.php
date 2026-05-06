<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AuthorRepository;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/authors', name: 'admin_authors', methods: ['GET'])]
final class AuthorsController extends AbstractController
{
    #[Template('dashboard/authors/index.html.twig')]
    public function __invoke(AuthorRepository $authors): array
    {
        return [
            'authors' => $authors->findBy([], ['realName' => 'ASC']),
        ];
    }
}

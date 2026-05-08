<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AuthorRepository;
use App\Util\Paginator;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/authors', name: 'admin_authors', methods: ['GET'])]
final class AuthorsController extends AbstractController
{
    #[Template('dashboard/authors/index.html.twig')]
    public function __invoke(Request $request, AuthorRepository $authors): array
    {
        return [
            'pager' => new Paginator(
                $authors->indexQueryBuilder(),
                $request->query->getInt('page', 1),
            ),
        ];
    }
}

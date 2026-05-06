<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Author;
use App\Repository\ContentNodeRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/authors/{id}', name: 'admin_authors_show', methods: ['GET'], requirements: ['id' => '\d+'])]
final class AuthorShowController extends AbstractController
{
    #[Template('dashboard/authors/show.html.twig')]
    public function __invoke(
        #[MapEntity(id: 'id')] Author $author,
        ContentNodeRepository $nodes,
    ): array {
        return [
            'author' => $author,
            'nodeCount' => $nodes->count(['author' => $author]),
        ];
    }
}

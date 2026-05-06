<?php

declare(strict_types=1);

namespace App\Controller\Security;

use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
final class LoginController extends AbstractController
{
    #[Template('security/login.html.twig')]
    public function __invoke(AuthenticationUtils $authenticationUtils): array
    {
        return [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ];
    }
}

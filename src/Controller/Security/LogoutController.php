<?php

declare(strict_types=1);

namespace App\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/logout', name: 'app_logout', methods: ['GET'])]
final class LogoutController extends AbstractController
{
    public function __invoke(): never
    {
        // The firewall intercepts this route. Method body is never executed.
        throw new \LogicException('This method should never be reached — handled by firewall.logout.');
    }
}

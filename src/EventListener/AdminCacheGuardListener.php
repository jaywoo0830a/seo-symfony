<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * /admin/* 와 /login 응답에 명시적으로 private + no-store 강제.
 *
 * 세션이 시작되면 Symfony가 자동으로 private 마킹을 하긴 하지만,
 * 세션을 안 건드린 admin 페이지(없을 가능성은 낮지만)도 안전하게
 * 보호하기 위해 라우트 prefix 기준으로 명시적 차단.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -10)]
final class AdminCacheGuardListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = (string) $event->getRequest()->attributes->get('_route', '');
        $isAdmin = str_starts_with($route, 'admin_') || $route === 'app_login';

        if (!$isAdmin) {
            return;
        }

        $event->getResponse()->headers->set('Cache-Control', 'private, no-store, max-age=0');
    }
}

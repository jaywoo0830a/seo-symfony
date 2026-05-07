<?php

use App\Kernel;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpCache\Store;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);

    // Symfony HttpCache 리버스 프록시 (응답 단위 캐싱).
    // 프로덕션 기본 ON. dev에서도 SYMFONY_HTTP_CACHE=1로 켤 수 있음.
    // 외부 CDN/Varnish가 있으면 둘 중 하나만 사용.
    $enableCache = $context['APP_ENV'] === 'prod'
        || filter_var($context['SYMFONY_HTTP_CACHE'] ?? '0', FILTER_VALIDATE_BOOL);

    if ($enableCache) {
        return new HttpCache(
            $kernel,
            new Store(dirname(__DIR__) . '/var/cache/' . $context['APP_ENV'] . '/http_cache'),
        );
    }

    return $kernel;
};

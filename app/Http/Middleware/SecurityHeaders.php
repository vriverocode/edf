<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        $csp = "default-src 'self'; ".
               "script-src 'self' https://js.culqi.com; ".
               "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com; ".
               "img-src 'self' data: https:; ".
               "font-src 'self' https://fonts.bunny.net https://fonts.gstatic.com; ".
               "connect-src 'self' https://js.culqi.com wss://ws-us2.pusher.com https://sockjs-us2.pusher.com; ".
               "frame-ancestors 'none'";
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}

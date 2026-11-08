<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrivateWorkflowResponses
{
    public static function protect(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        $response->setVary('Cookie', false);
        return $response;
    }

    public function handle(Request $request, Closure $next): Response
    {
        return self::protect($next($request));
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Adapt the existing authorized workflows to the first-party browser API. */
class ApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        $response = $next($request);

        if ($response instanceof RedirectResponse) {
            return response()->json([
                'redirect' => $response->getTargetUrl(),
                'message' => $request->session()->get('status'),
            ])->header('Cache-Control', 'no-store, private');
        }

        if (method_exists($response, 'getOriginalContent') && $response->getOriginalContent() instanceof View) {
            // Only return content already visible to this user, never raw model attributes.
            return response()->json(['html' => $response->getContent()])
                ->header('Cache-Control', 'no-store, private');
        }

        // Downloads and previews retain their binary/streaming response types.
        return $response;
    }
}

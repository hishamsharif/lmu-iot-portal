<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalReportingToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerName = (string) config('reporting.api.token_header', 'X-Reporting-Token');
        $expectedToken = (string) config('reporting.api.token', '');
        $receivedToken = $request->header($headerName);

        if ($expectedToken === '' || ! is_string($receivedToken) || ! hash_equals($expectedToken, $receivedToken)) {
            return new JsonResponse([
                'message' => 'Unauthorized internal reporting request.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}

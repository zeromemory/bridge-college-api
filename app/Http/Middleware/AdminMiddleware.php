<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->error(
                message: 'Admin access required.',
                errorCode: 'UNAUTHORIZED',
                status: 403,
            );
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentMiddleware
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isStudent()) {
            return $this->error(
                message: 'Student access required.',
                errorCode: 'UNAUTHORIZED',
                status: 403,
            );
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeacherMiddleware
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isTeacher()) {
            return $this->error(
                message: 'Teacher access required.',
                errorCode: 'UNAUTHORIZED',
                status: 403,
            );
        }

        return $next($request);
    }
}

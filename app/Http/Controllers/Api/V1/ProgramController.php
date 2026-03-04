<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProgramController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $programs = Program::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'level', 'description']);

        return $this->success(
            data: ['programs' => $programs],
            message: 'Programs retrieved successfully.',
        );
    }

    public function show(string $slug): JsonResponse
    {
        $program = Program::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $program) {
            return $this->error(
                message: 'Program not found.',
                errorCode: 'NOT_FOUND',
                status: 404,
            );
        }

        return $this->success(
            data: ['program' => $program],
            message: 'Program retrieved successfully.',
        );
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $branches = Branch::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'address', 'city', 'phones', 'whatsapp', 'whatsapp_link']);

        return $this->success(
            data: ['branches' => $branches],
            message: 'Branches retrieved successfully.',
        );
    }

    public function show(int $id): JsonResponse
    {
        $branch = Branch::where('id', $id)
            ->where('is_active', true)
            ->first();

        if (! $branch) {
            return $this->error(
                message: 'Branch not found.',
                errorCode: 'NOT_FOUND',
                status: 404,
            );
        }

        return $this->success(
            data: ['branch' => $branch],
            message: 'Branch retrieved successfully.',
        );
    }
}

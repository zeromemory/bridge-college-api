<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $applications = $request->user()->applications()
            ->with(['program:id,name,slug', 'branch:id,name,city', 'challans:id,application_id,status'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($app) {
                $feeStatus = $app->challans->isEmpty()
                    ? null
                    : ($app->challans->contains('status', 'paid') ? 'paid' : 'pending');

                return [
                    'id' => $app->id,
                    'application_number' => $app->application_number,
                    'program' => $app->program,
                    'branch' => $app->branch,
                    'status' => $app->status,
                    'study_mode' => $app->study_mode,
                    'submitted_at' => $app->submitted_at,
                    'fee_status' => $feeStatus,
                    'created_at' => $app->created_at,
                ];
            });

        return $this->success(
            data: ['applications' => $applications],
            message: 'Dashboard loaded successfully.',
        );
    }
}

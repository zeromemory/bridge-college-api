<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAcademicSessionRequest;
use App\Http\Requests\Admin\UpdateAcademicSessionRequest;
use App\Models\AcademicSession;
use App\Services\AcademicSessionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AcademicSessionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AcademicSessionService $service,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(
            data: $this->service->list(),
            message: 'Academic sessions retrieved',
        );
    }

    public function store(StoreAcademicSessionRequest $request): JsonResponse
    {
        $session = $this->service->create($request->validated());

        return $this->success(
            data: $session,
            message: 'Academic session created',
            status: 201,
        );
    }

    public function update(UpdateAcademicSessionRequest $request, AcademicSession $session): JsonResponse
    {
        $session = $this->service->update($session, $request->validated());

        return $this->success(
            data: $session,
            message: 'Academic session updated',
        );
    }

    public function activate(AcademicSession $session): JsonResponse
    {
        $session = $this->service->activate($session);

        return $this->success(
            data: $session,
            message: 'Academic session activated',
        );
    }
}

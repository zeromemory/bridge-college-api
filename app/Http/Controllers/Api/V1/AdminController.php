<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\FeeChallan;
use App\Models\User;
use App\Services\ChallanService;
use App\Models\ApplicationDocument;
use App\Notifications\ApplicationAcceptedNotification;
use App\Notifications\ApplicationRejectedNotification;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ChallanService $challanService,
    ) {}

    // ── Dashboard Stats ──

    public function stats(): JsonResponse
    {
        $total = Application::count();
        $byStatus = Application::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');
        $byProgram = Application::selectRaw('program_id, count(*) as count')
            ->groupBy('program_id')
            ->with('program:id,name')
            ->get()
            ->map(fn ($row) => [
                'program' => $row->program->name ?? 'Unknown',
                'count' => $row->count,
            ]);
        $byBranch = Application::selectRaw('branch_id, count(*) as count')
            ->groupBy('branch_id')
            ->with('branch:id,name')
            ->get()
            ->map(fn ($row) => [
                'branch' => $row->branch->name ?? 'Unknown',
                'count' => $row->count,
            ]);
        $todayCount = Application::whereDate('created_at', today())->count();

        return $this->success(
            data: [
                'total' => $total,
                'by_status' => $byStatus,
                'by_program' => $byProgram,
                'by_branch' => $byBranch,
                'today' => $todayCount,
            ],
            message: 'Admin stats retrieved successfully.',
        );
    }

    // ── Application Management ──

    public function applications(Request $request): JsonResponse
    {
        $query = Application::with(['user:id,name,email,cnic', 'program:id,name', 'branch:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('program_id')) {
            $query->where('program_id', $request->input('program_id'));
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('application_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('cnic', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 50);
        $applications = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->success(
            data: ['applications' => $applications],
            message: 'Applications retrieved successfully.',
        );
    }

    public function showApplication(int $id): JsonResponse
    {
        $application = Application::with([
            'user:id,name,email,cnic,mobile',
            'program', 'branch',
            'personalDetail', 'education', 'documents', 'extras', 'challans',
        ])->findOrFail($id);

        return $this->success(
            data: ['application' => $application],
            message: 'Application detail retrieved successfully.',
        );
    }

    public function acceptApplication(Request $request, int $id): JsonResponse
    {
        $application = Application::findOrFail($id);

        if ($application->status !== 'submitted') {
            return $this->error(
                message: 'Only submitted applications can be accepted.',
                errorCode: 'VALIDATION_ERROR',
                status: 422,
            );
        }

        $application->update([
            'status' => 'accepted',
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'admin_notes' => $request->input('admin_notes'),
        ]);

        $application->load(['program', 'branch', 'user']);
        $application->user->notify(new ApplicationAcceptedNotification($application));

        return $this->success(
            data: ['application' => $application->fresh()],
            message: 'Application accepted successfully.',
        );
    }

    public function rejectApplication(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'admin_notes' => ['required', 'string', 'max:1000'],
        ]);

        $application = Application::findOrFail($id);

        if ($application->status !== 'submitted') {
            return $this->error(
                message: 'Only submitted applications can be rejected.',
                errorCode: 'VALIDATION_ERROR',
                status: 422,
            );
        }

        $application->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'admin_notes' => $request->input('admin_notes'),
        ]);

        $application->load(['program', 'user']);
        $application->user->notify(new ApplicationRejectedNotification($application));

        return $this->success(
            data: ['application' => $application->fresh()],
            message: 'Application rejected.',
        );
    }

    // ── Student Management ──

    public function students(Request $request): JsonResponse
    {
        $query = User::where('role', 'student');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('cnic', 'like', "%{$search}%");
            });
        }

        $students = $query->withCount('applications')
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->success(
            data: ['students' => $students],
            message: 'Students retrieved successfully.',
        );
    }

    public function showStudent(int $id): JsonResponse
    {
        $student = User::where('role', 'student')
            ->with(['applications' => function ($q) {
                $q->with(['program:id,name', 'branch:id,name'])->orderByDesc('created_at');
            }])
            ->findOrFail($id);

        return $this->success(
            data: ['student' => $student],
            message: 'Student profile retrieved successfully.',
        );
    }

    public function toggleStudentStatus(int $id): JsonResponse
    {
        $student = User::where('role', 'student')->findOrFail($id);

        $student->update(['is_active' => ! $student->is_active]);

        return $this->success(
            data: ['student' => $student->fresh()],
            message: $student->is_active ? 'Student activated.' : 'Student deactivated.',
        );
    }

    // ── Fee Management (Phase 1: simple toggle) ──

    public function downloadDocument(int $id): StreamedResponse
    {
        $document = ApplicationDocument::findOrFail($id);

        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_name,
            ['Content-Type' => $document->mime_type],
        );
    }

    public function toggleFeeStatus(int $id): JsonResponse
    {
        $application = Application::findOrFail($id);

        $newStatus = $application->fee_status === 'paid' ? 'pending' : 'paid';
        $application->update(['fee_status' => $newStatus]);

        return $this->success(
            data: ['application' => $application],
            message: $newStatus === 'paid' ? 'Fee marked as paid.' : 'Fee marked as unpaid.',
        );
    }
}

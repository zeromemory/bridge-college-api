<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use App\Notifications\ApplicationSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ApplicationService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phps',
        'js', 'jsx', 'ts', 'tsx',
        'svg', 'svgz',
        'html', 'htm', 'xhtml',
        'exe', 'bat', 'cmd', 'sh', 'bash',
    ];

    public function create(User $user, array $data): Application
    {
        $data['user_id'] = $user->id;
        $data['application_number'] = $this->generateApplicationNumber();
        $data['status'] = 'draft';

        return Application::create($data);
    }

    public function updatePersonalDetails(Application $application, array $data): Application
    {
        $educationData = $data['education'];
        unset($data['education']);

        DB::transaction(function () use ($application, $data, $educationData) {
            $application->personalDetail()->updateOrCreate(
                ['application_id' => $application->id],
                $data,
            );

            $application->education()->delete();
            foreach ($educationData as $index => $edu) {
                $edu['sort_order'] = $index;
                $application->education()->create($edu);
            }
        });

        return $application->load(['personalDetail', 'education']);
    }

    public function updateExtras(Application $application, array $data): Application
    {
        $application->extras()->updateOrCreate(
            ['application_id' => $application->id],
            $data,
        );

        return $application->load('extras');
    }

    public function uploadDocument(Application $application, UploadedFile $file, string $documentType): ApplicationDocument
    {
        $this->validateFile($file);

        // For photo type, enforce 1MB limit
        if ($documentType === 'photo' && $file->getSize() > 1048576) {
            throw new \InvalidArgumentException('Photo must not exceed 1MB.');
        }

        // Store locally for now (S3 later)
        $path = $file->store("documents/{$application->id}", 'local');

        // For non-additional types, replace existing document
        if ($documentType !== 'additional') {
            $existing = $application->documents()
                ->where('document_type', $documentType)
                ->first();

            if ($existing) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($existing->file_path);
                $existing->delete();
            }
        }

        return $application->documents()->create([
            'document_type' => $documentType,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_at' => now(),
        ]);
    }

    public function deleteDocument(Application $application, int $documentId): void
    {
        $document = $application->documents()->findOrFail($documentId);
        \Illuminate\Support\Facades\Storage::disk('local')->delete($document->file_path);
        $document->delete();
    }

    public function getReview(Application $application): Application
    {
        return $application->load([
            'program',
            'branch',
            'personalDetail',
            'education',
            'documents',
            'extras',
        ]);
    }

    public function submit(Application $application): Application
    {
        $application->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $application->load(['program', 'branch']);
        $application->user->notify(new ApplicationSubmittedNotification($application));

        return $application;
    }

    private function generateApplicationNumber(): string
    {
        $year = date('Y');

        do {
            $number = 'BCI-' . $year . '-' . str_pad(random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (Application::where('application_number', $number)->exists());

        return $number;
    }

    private function validateFile(UploadedFile $file): void
    {
        // Check actual MIME type (server-side, not client-provided)
        $mimeType = $file->getMimeType();
        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new \InvalidArgumentException('File type not allowed. Accepted: JPEG, PNG, WebP, PDF.');
        }

        // Check extension against blocklist
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, self::BLOCKED_EXTENSIONS)) {
            throw new \InvalidArgumentException('File extension not allowed.');
        }
    }
}

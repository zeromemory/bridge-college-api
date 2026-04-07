<?php

namespace App\Services;

use App\Models\ClassMaterial;
use App\Models\ClassRoom;
use App\Models\ClassSubjectTeacher;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClassMaterialService
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/png',
        'image/jpeg',
    ];

    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phps',
        'js', 'jsx', 'ts', 'tsx',
        'svg', 'svgz',
        'html', 'htm', 'xhtml',
        'exe', 'bat', 'cmd', 'sh', 'bash',
    ];

    public function __construct(
        private readonly TeacherDashboardService $teacherDashboard,
    ) {}

    /**
     * Returns all materials for a class (which the given teacher must have
     * access to), grouped by subject_id. Used by the teacher class detail
     * Materials tab.
     */
    public function listForTeacherClass(User $teacher, ClassRoom $class)
    {
        $this->teacherDashboard->ensureTeacherCanAccessClass($teacher, $class);

        return ClassMaterial::where('class_id', $class->id)
            ->with(['subject:id,name,code', 'uploader:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('subject_id');
    }

    public function create(User $teacher, ClassRoom $class, array $data, ?UploadedFile $file): ClassMaterial
    {
        // Verify the teacher actually teaches this (class, subject) pair —
        // not just any subject in this class.
        $teachesPair = ClassSubjectTeacher::where('class_id', $class->id)
            ->where('subject_id', $data['subject_id'])
            ->where('teacher_id', $teacher->id)
            ->exists();

        if (! $teachesPair) {
            throw new AuthorizationException('You do not teach this subject in this class.');
        }

        $payload = [
            'class_id' => $class->id,
            'subject_id' => $data['subject_id'],
            'uploaded_by' => $teacher->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
        ];

        if ($data['type'] === 'file') {
            if (! $file) {
                throw new \InvalidArgumentException('A file is required for file-type materials.');
            }
            $this->validateFile($file);

            $disk = config('filesystems.default');
            $directory = "class-materials/{$class->id}";
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($directory, $filename, $disk);

            $payload['file_path'] = $path;
            $payload['file_name'] = $file->getClientOriginalName();
            $payload['file_size'] = $file->getSize();
            $payload['mime_type'] = $file->getMimeType();
        } else {
            // type === 'link'
            $payload['external_url'] = $data['external_url'];
        }

        $material = ClassMaterial::create($payload);

        Log::info('Class material created', [
            'material_id' => $material->id,
            'class_id' => $class->id,
            'subject_id' => $data['subject_id'],
            'teacher_id' => $teacher->id,
            'type' => $data['type'],
        ]);

        return $material->load(['subject:id,name,code', 'uploader:id,name']);
    }

    public function delete(User $teacher, ClassMaterial $material): void
    {
        if ($material->uploaded_by !== $teacher->id) {
            throw new AuthorizationException('You can only delete materials you uploaded.');
        }

        // Soft-delete the row; leave the file on disk for forensic recovery.
        // A separate cleanup job can hard-delete orphaned files later.
        $material->delete();

        Log::info('Class material deleted', [
            'material_id' => $material->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    private function validateFile(UploadedFile $file): void
    {
        // Defense in depth — FormRequest already validates these, but the
        // service is the last line before storage so we re-check.
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw new \InvalidArgumentException('File must not exceed 10MB.');
        }

        $mimeType = $file->getMimeType();
        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('File type not allowed.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('File extension not allowed.');
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClassMaterial extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'class_id',
        'subject_id',
        'uploaded_by',
        'title',
        'description',
        'type',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'external_url',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Log structural fields only — never the file contents or external URL.
        return LogOptions::defaults()
            ->logOnly(['class_id', 'subject_id', 'uploaded_by', 'title', 'type'])
            ->logOnlyDirty();
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

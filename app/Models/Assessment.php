<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Assessment extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'class_id',
        'subject_id',
        'teacher_id',
        'title',
        'type',
        'total_marks',
        'date',
        'description',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'total_marks' => 'decimal:1',
            'date' => 'date:Y-m-d',
            'is_published' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['class_id', 'subject_id', 'teacher_id', 'title', 'type', 'total_marks', 'date', 'is_published'])
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

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(AssessmentMark::class);
    }
}

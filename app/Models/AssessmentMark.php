<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssessmentMark extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'assessment_id',
        'student_id',
        'marks_obtained',
        'is_absent',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'marks_obtained' => 'decimal:1',
            'is_absent' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['assessment_id', 'student_id', 'marks_obtained', 'is_absent'])
            ->logOnlyDirty();
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Attendance extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'class_id',
        'student_id',
        'marked_by',
        'date',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['class_id', 'student_id', 'marked_by', 'date', 'status'])
            ->logOnlyDirty();
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function markedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}

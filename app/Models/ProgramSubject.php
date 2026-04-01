<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramSubject extends Model
{
    protected $table = 'program_subject';

    protected $fillable = [
        'program_id',
        'subject_id',
        'is_elective',
    ];

    protected function casts(): array
    {
        return [
            'is_elective' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationEducation extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'qualification',
        'board_university',
        'roll_no',
        'registration_no',
        'exam_type',
        'exam_year',
        'total_marks',
        'obtained_marks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'exam_year' => 'integer',
            'total_marks' => 'integer',
            'obtained_marks' => 'integer',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

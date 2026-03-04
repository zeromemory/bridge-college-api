<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationExtra extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'study_from',
        'prior_computer_knowledge',
        'has_computer',
        'internet_type',
        'heard_about_us',
        'scholarship_interest',
    ];

    protected function casts(): array
    {
        return [
            'prior_computer_knowledge' => 'boolean',
            'has_computer' => 'boolean',
            'scholarship_interest' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

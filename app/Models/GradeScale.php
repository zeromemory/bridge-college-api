<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeScale extends Model
{
    protected $fillable = [
        'grade',
        'min_percentage',
        'max_percentage',
        'remarks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_percentage' => 'decimal:2',
            'max_percentage' => 'decimal:2',
        ];
    }
}

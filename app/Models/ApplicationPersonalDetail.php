<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationPersonalDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'father_name',
        'father_cnic',
        'father_phone',
        'guardian_name',
        'guardian_relationship',
        'guardian_income',
        'gender',
        'date_of_birth',
        'nationality',
        'religion',
        'mother_tongue',
        'postal_address',
        'permanent_address',
        'same_address',
        'cnic_issuance_date',
        'phone_landline',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'cnic_issuance_date' => 'date',
            'same_address' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}

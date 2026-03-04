<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'program_id',
        'branch_id',
        'application_number',
        'status',
        'study_mode',
        'city',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function personalDetail(): HasOne
    {
        return $this->hasOne(ApplicationPersonalDetail::class);
    }

    public function education(): HasMany
    {
        return $this->hasMany(ApplicationEducation::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function extras(): HasOne
    {
        return $this->hasOne(ApplicationExtra::class);
    }

    public function challans(): HasMany
    {
        return $this->hasMany(FeeChallan::class);
    }
}

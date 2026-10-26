<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pharmacy extends Model
{
    protected $fillable = [
        'name',
        'client_type',
        'category',
        'neighborhood',
        'address',
        'city',
        'department',
        'frequency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PharmacyAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(PharmacyAssignment::class)
            ->whereNull('ended_at');
    }
}

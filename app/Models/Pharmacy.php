<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pharmacy extends Model
{
    protected $fillable = [
        'source_reference',
        'name',
        'client_type',
        'category',
        'neighborhood',
        'address',
        'city',
        'department',
        'frequency',
        'dependents',
        'shifts',
        'phone',
        'email',
        'source_responsible',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => 'integer',
            'dependents' => 'integer',
            'shifts' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PharmacyAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(PharmacyAssignment::class)->whereNull('ended_at');
    }

    /** Texto de ubicación calculado: NO es otra columna editable. */
    public function getLocationSummaryAttribute(): string
    {
        $pieces = collect([$this->neighborhood, $this->city, $this->department])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->unique(fn ($value) => mb_strtolower(trim($value)))
            ->values()
            ->all();

        $place = implode(' · ', $pieces);
        $address = trim((string) $this->address);

        return $address === '' ? $place : trim($place.' | '.$address, ' |');
    }
}

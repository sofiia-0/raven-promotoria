<?php

namespace App\Models;

use App\Support\EstablishmentPresentation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pharmacy extends Model
{
    use HasFactory;

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

    /** Auditoría de correcciones de datos de este establecimiento. */
    public function changes(): HasMany
    {
        return $this->hasMany(PharmacyChange::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PharmacyAssignment::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(PharmacyAssignment::class)->whereNull('ended_at');
    }

    public function getDisplayNameAttribute(): string
    {
        return EstablishmentPresentation::title($this->name, $this->department);
    }

    public function getBranchSummaryAttribute(): string
    {
        return EstablishmentPresentation::reference($this->name, $this->department, $this->city);
    }

    public function getDisplayLocalityAttribute(): string
    {
        return EstablishmentPresentation::locality($this->department, $this->city);
    }

    public function getDisplayAddressAttribute(): string
    {
        return EstablishmentPresentation::address($this->address, $this->neighborhood, $this->department, $this->city);
    }

    /** Resumen de lectura; los identificadores comerciales no se convierten en dirección. */
    public function getLocationSummaryAttribute(): string
    {
        return collect([$this->display_locality, $this->display_address])
            ->filter(fn ($value) => $value !== '')->join(' | ');
    }
}

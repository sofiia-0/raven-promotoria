<?php

namespace App\Models;

use App\Support\EstablishmentSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Catálogo de motivos (visita no realizada / reprogramación). */
class VisitReason extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sort_order', 'is_active', 'requires_explanation'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'requires_explanation' => 'boolean',
        ];
    }

    public function getNeedsExplanationAttribute(): bool
    {
        return $this->requires_explanation ?? trim(EstablishmentSearch::normalize($this->name)) === 'otro motivo';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}

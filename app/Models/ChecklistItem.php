<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Catálogo de puntos a revisar en una visita realizada. */
class ChecklistItem extends Model
{
    use HasFactory;

    public const OBSOLETE_NAMES = ['Capacitación o revisión de plan de exhibición', 'Entrega de Plan paciente'];

    protected $fillable = ['name', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotIn('name', self::OBSOLETE_NAMES)->orderBy('sort_order')->orderBy('name');
    }
}

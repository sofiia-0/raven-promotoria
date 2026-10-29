<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Catálogo extensible; su administración queda para Coordinadora. */
class PromoterReasonCategory extends Model
{
    protected $fillable = ['domain', 'code', 'name', 'is_active', 'is_provisional', 'is_other', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_provisional' => 'boolean', 'is_other' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeAvailable(Builder $query, string $domain): Builder
    {
        return $query->where('domain', $domain)->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}

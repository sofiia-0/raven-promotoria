<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyChange extends Model
{
    /** Únicamente registra cambios de datos; las reasignaciones ya tienen su historial independiente. */
    protected $fillable = ['pharmacy_id', 'actor_id', 'before', 'after'];

    public const LABELS = [
        'name' => 'Nombre',
        'client_type' => 'Tipo de establecimiento',
        'category' => 'Categoría',
        'neighborhood' => 'Barrio / zona',
        'address' => 'Dirección',
        'city' => 'Ciudad',
        'department' => 'Departamento',
        'frequency' => 'Frecuencia',
        'dependents' => 'Dependientes',
        'shifts' => 'Turnos',
        'phone' => 'Teléfono',
        'email' => 'Correo',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

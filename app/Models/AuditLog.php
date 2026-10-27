<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Bitácora append-only: se puede insertar, nunca modificar ni borrar. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'action', 'auditable_type', 'auditable_id', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('La bitácora no admite modificaciones.'));
        static::deleting(fn () => throw new LogicException('La bitácora no admite borrados.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

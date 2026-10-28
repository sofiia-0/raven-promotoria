<?php

namespace App\Models;

use App\Enums\ActivityProgress;
use App\Enums\ActivityType;
use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlannedActivity extends Model
{
    protected $fillable = [
        'planned_week_id',
        'activity_type',
        'pharmacy_id',
        'scheduled_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'activity_type' => ActivityType::class,
            'scheduled_date' => 'immutable_date',
            'reschedule_count' => 'integer',
        ];
    }

    public function week(): BelongsTo
    {
        return $this->belongsTo(PlannedWeek::class, 'planned_week_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class)->orderBy('attempt');
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(ActivityReschedule::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Pendiente: no hay registro para el intento vigente.
     * Requiere la relación `visits` cargada para no generar N+1.
     */
    public function progress(): ?ActivityProgress
    {
        if ($this->activity_type !== ActivityType::VISITA) {
            return null;
        }

        if ($this->visits->contains('status', VisitStatus::REALIZADA)) {
            return ActivityProgress::REALIZADA;
        }

        $current = $this->visits->firstWhere('attempt', $this->reschedule_count);

        return $current ? ActivityProgress::NO_REALIZADA : ActivityProgress::PENDIENTE;
    }
}

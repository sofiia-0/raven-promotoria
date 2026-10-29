<?php

namespace App\Models;

use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Se crea solo mediante VisitRecorder (idempotencia, checklist y bitácora). */
class Visit extends Model
{
    protected $fillable = [
        'idempotency_key',
        'promoter_id',
        'pharmacy_id',
        'planned_activity_id',
        'attempt',
        'status',
        'visit_reason_id',
        'notes',
        'latitude',
        'longitude',
        'location_accuracy',
        'visited_at',
    ];

    public function evidences(): HasMany
    {
        return $this->hasMany(VisitEvidence::class)->orderBy('uploaded_at')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'attempt' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'location_accuracy' => 'integer',
            'visited_at' => 'datetime',
        ];
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoter_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function plannedActivity(): BelongsTo
    {
        return $this->belongsTo(PlannedActivity::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(VisitReason::class, 'visit_reason_id');
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(VisitChecklistEntry::class);
    }

    public function isUnplanned(): bool
    {
        return $this->planned_activity_id === null;
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}

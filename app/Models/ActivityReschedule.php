<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityReschedule extends Model
{
    protected $fillable = [
        'planned_activity_id',
        'actor_id',
        'from_date',
        'to_date',
        'visit_reason_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'immutable_date',
            'to_date' => 'immutable_date',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(PlannedActivity::class, 'planned_activity_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(VisitReason::class, 'visit_reason_id');
    }
}

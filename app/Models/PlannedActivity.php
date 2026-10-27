<?php

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}

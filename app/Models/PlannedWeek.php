<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlannedWeek extends Model
{
    protected $fillable = [
        'monthly_schedule_id',
        'week_number',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'week_number' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MonthlySchedule::class, 'monthly_schedule_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PlannedActivity::class)->orderBy('scheduled_date')->orderBy('id');
    }
}

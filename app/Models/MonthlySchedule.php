<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use App\Services\SchedulePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class MonthlySchedule extends Model
{
    protected $fillable = [
        'promoter_id',
        'year',
        'month',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'status' => ScheduleStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoter_id');
    }

    public function weeks(): HasMany
    {
        return $this->hasMany(PlannedWeek::class)->orderBy('week_number');
    }

    public function activities(): HasManyThrough
    {
        return $this->hasManyThrough(PlannedActivity::class, PlannedWeek::class);
    }

    public function isDraft(): bool
    {
        return $this->status === ScheduleStatus::BORRADOR;
    }

    public function firstDay(): CarbonImmutable
    {
        return CarbonImmutable::create($this->year, $this->month, 1);
    }

    public function getPeriodLabelAttribute(): string
    {
        return SchedulePlanner::monthLabel($this->firstDay());
    }
}

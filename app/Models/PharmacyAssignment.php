<?php

namespace App\Models;

use App\Services\SchedulePlanner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyAssignment extends Model
{
    use HasFactory;

    protected $attributes = ['assignment_kind' => 'responsible'];

    protected $fillable = [
        'pharmacy_id',
        'promoter_id',
        'assigned_at',
        'ended_at',
        'assignment_kind', 'coverage_starts_on', 'coverage_ends_on',
        'created_by', 'ended_by', 'replacement_assignment_id',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
            'coverage_starts_on' => 'immutable_date',
            'coverage_ends_on' => 'immutable_date',
        ];
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoter_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    /** Inclusive Managua calendar dates; expiration revokes access without a scheduled write. */
    public static function constrainOperational(Builder $query, string $prefix = '', ?string $on = null): Builder
    {
        $day = $on ?? SchedulePlanner::today()->toDateString();

        return $query->whereNull($prefix.'ended_at')->where(fn (Builder $q) => $q
            ->where($prefix.'assignment_kind', 'responsible')
            ->orWhere(fn (Builder $coverage) => $coverage->where($prefix.'assignment_kind', 'coverage')
                ->whereDate($prefix.'coverage_starts_on', '<=', $day)->whereDate($prefix.'coverage_ends_on', '>=', $day)));
    }

    public function scopeOperational(Builder $query): Builder
    {
        return self::constrainOperational($query);
    }

    public function scopeOperationalOn(Builder $query, string $day): Builder
    {
        return self::constrainOperational($query, '', $day);
    }

    public function getOperationalAttribute(): bool
    {
        if ($this->ended_at !== null) {
            return false;
        }
        if ($this->assignment_kind === 'responsible') {
            return true;
        }
        $day = SchedulePlanner::today()->toDateString();

        return $this->assignment_kind === 'coverage' && $this->coverage_starts_on?->toDateString() <= $day
            && $this->coverage_ends_on?->toDateString() >= $day;
    }

    public function getPeriodStatusAttribute(): string
    {
        if ($this->ended_at) {
            return 'Terminada';
        }
        if ($this->assignment_kind !== 'coverage') {
            return 'Vigente';
        }

        return $this->coverage_starts_on?->isAfter(SchedulePlanner::today()) ? 'Programada'
            : ($this->operational ? 'Vigente' : 'Vencida');
    }
}

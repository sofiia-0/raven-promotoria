<?php

namespace App\Models;

use App\Support\EstablishmentPresentation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyRequest extends Model
{
    public const TYPE_ALTA = 'alta';

    public const TYPE_BAJA = 'baja';

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'promoter_id', 'pharmacy_id', 'type', 'status',
        'data', 'reason', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }

    public function scopeReviewQueue(Builder $query): Builder
    {
        return $query->pending()->with(['promoter:id,name', 'pharmacy:id,name,department,city'])
            ->oldest()->oldest('id');
    }

    public function establishmentTitle(): string
    {
        return $this->type === self::TYPE_ALTA
            ? EstablishmentPresentation::title($this->data['name'] ?? 'Nuevo establecimiento', $this->data['department'] ?? null)
            : ($this->pharmacy?->display_name ?? 'Establecimiento');
    }

    public function establishmentLocality(): string
    {
        return $this->type === self::TYPE_ALTA
            ? EstablishmentPresentation::locality($this->data['department'] ?? null, $this->data['city'] ?? null)
            : ($this->pharmacy?->display_locality ?? '');
    }

    public function reasonLabel(): string
    {
        return $this->data['reason_category']['label'] ?? $this->reason ?? '';
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoter_id');
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

<?php

namespace App\Models;

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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitObjective extends Model
{
    protected $fillable = ['visit_id', 'promoter_reason_category_id', 'category_code', 'category_label', 'is_provisional', 'explanation'];

    protected function casts(): array
    {
        return ['is_provisional' => 'boolean', 'promoter_reason_category_id' => 'integer'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PromoterReasonCategory::class, 'promoter_reason_category_id');
    }
}

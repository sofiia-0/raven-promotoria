<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitChecklistEntry extends Model
{
    protected $table = 'visit_checklist';

    public $timestamps = false;

    protected $fillable = ['visit_id', 'checklist_item_id', 'checked'];

    protected function casts(): array
    {
        return ['checked' => 'boolean'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitEvidence extends Model
{
    protected $table = 'visit_evidences';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['disk', 'path', 'batch_key'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'uploaded_at' => 'immutable_datetime', 'attached_at' => 'immutable_datetime'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function previewable(): bool
    {
        return in_array($this->extension, ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    public function displaySize(): string
    {
        if ($this->size < 1024) {
            return $this->size.' B';
        }
        if ($this->size < 1024 * 1024) {
            return number_format($this->size / 1024, 1, ',', '.').' KiB';
        }

        return number_format($this->size / 1024 / 1024, 2, ',', '.').' MiB';
    }
}

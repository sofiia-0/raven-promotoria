<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstablishmentImport extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'actor_id', 'status', 'file_sha256', 'manifest_sha256', 'manifest', 'applied_at'];

    protected function casts(): array
    {
        return ['manifest' => 'array', 'applied_at' => 'datetime'];
    }
}

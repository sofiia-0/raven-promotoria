<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserRole;
use App\Notifications\RavenPasswordResetNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Laravel genera el token; Raven personaliza únicamente el mensaje.
     * Nunca se envían contraseñas por correo.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RavenPasswordResetNotification($token));
    }

    public function pharmacyAssignments(): HasMany
    {
        return $this->hasMany(PharmacyAssignment::class, 'promoter_id');
    }

    /**
     * Establecimientos asignados actualmente a esta promotora.
     * No reemplaza la validación de autorización por rol/usuario.
     */
    public function assignedPharmacies(): BelongsToMany
    {
        return $this->belongsToMany(Pharmacy::class, 'pharmacy_assignments', 'promoter_id', 'pharmacy_id')
            ->wherePivotNull('ended_at')
            ->withPivot(['id', 'assigned_at', 'ended_at'])
            ->withTimestamps();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Brukere. Base44 har én rolle per bruker (admin|user|guest) i feltet `role`;
 * det beholdes som "hovedrolle". Finere roller kommer via spatie/permission senere
 * (hasRole() sjekker begge).
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public $incrementing = false;
    protected $keyType = 'string';
    public const CREATED_AT = 'created_date';
    public const UPDATED_AT = 'updated_date';

    protected $fillable = [
        'id', 'app_id', 'email', 'full_name', 'role', 'password',
        // Felt fra Base44 User-entiteten (Appendix Properties)
        'mobile', 'account_number', 'admin_approved', 'onboarding_completed',
        'national_id_hash', 'national_id_last4', 'national_id_verified_at', 'national_id_source',
        'bankid_verified', 'bankid_verified_at', 'bankid_match_method',
        'nin_hash', 'nin_level', 'nin_verified_at',
    ];

    protected $hidden = ['password', 'remember_token', 'national_id_hash', 'nin_hash'];

    protected $casts = [
        'admin_approved' => 'boolean',
        'onboarding_completed' => 'boolean',
        'bankid_verified' => 'boolean',
        'national_id_verified_at' => 'datetime',
        'bankid_verified_at' => 'datetime',
        'nin_verified_at' => 'datetime',
        'nin_level' => 'integer',
        'password' => 'hashed',
    ];

    public function hasRole(string $role): bool
    {
        if ($this->role === $role) {
            return true;
        }
        // spatie/permission, når den er satt opp
        if (method_exists($this, 'roles') && $this->relationLoaded('roles')) {
            return $this->roles->contains('name', $role);
        }
        return false;
    }

    /** Samme form som base44.auth.me() */
    public function toBase44Array(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'role' => $this->role,
            'app_id' => $this->app_id,
            'created_date' => $this->created_date,
            'mobile' => $this->mobile,
            'admin_approved' => $this->admin_approved,
            'onboarding_completed' => $this->onboarding_completed,
            'bankid_verified' => $this->bankid_verified,
            'national_id_last4' => $this->national_id_last4,
        ];
    }
}

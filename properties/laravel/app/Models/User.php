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
        // Fase 6 (identitet): innloggingsmåte = hvem du er; brukerposten = hva du får gjøre
        'identity_provider', 'entra_oid', 'access_until', 'last_login_at', 'last_login_provider',
    ];

    /** Innloggingsmåter. Admin krever alltid ENTRA – BankID kan aldri gi admin. */
    public const PROVIDER_ENTRA = 'entra';
    public const PROVIDER_BANKID = 'bankid';
    public const PROVIDER_PASSWORD = 'password';

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
        'access_until' => 'date',
        'last_login_at' => 'datetime',
    ];

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Kan brukeren logge inn via gitt innloggingsmåte (entra|bankid|password|webauthn)?
     *  - access_until (konsulenter): passert dato → nei
     *  - admin-rolle krever Entra (faste ansatte); BankID/passord/QR gir aldri admin-innlogging
     * Hvorfor den avvises hentes med loginDeniedReason().
     */
    public function canLogin(string $provider): bool
    {
        return $this->loginDeniedReason($provider) === null;
    }

    /** null = OK, ellers en kort norsk begrunnelse som kan vises til brukeren. */
    public function loginDeniedReason(string $provider): ?string
    {
        if ($this->access_until && $this->access_until->endOfDay()->isPast()) {
            return 'Tilgangen utløp ' . $this->access_until->format('d.m.Y') . '. Kontakt administrator for forlengelse.';
        }
        if ($this->isAdmin() && $provider !== self::PROVIDER_ENTRA) {
            return 'Administratorer må logge inn med Microsoft-kontoen sin.';
        }
        return null;
    }

    /** Registrer vellykket innlogging (kalles av Entra/BankID-callback). */
    public function markLoggedIn(string $provider): void
    {
        $this->forceFill([
            'identity_provider' => $provider === self::PROVIDER_PASSWORD ? ($this->identity_provider ?: $provider) : $provider,
            'last_login_at' => now(),
            'last_login_provider' => $provider,
        ])->save();
    }

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
            'identity_provider' => $this->identity_provider,
            'access_until' => $this->access_until?->toDateString(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_login_provider' => $this->last_login_provider,
        ];
    }
}

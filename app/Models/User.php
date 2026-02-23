<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'password'          => 'hashed',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Companies this user has access to.
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Returns true if the user can access the given company.
     * Superadmins can access any company.
     */
    public function canAccessCompany(int $companyId): bool
    {
        if ($this->hasRole('superadmin')) {
            return true;
        }

        return $this->companies()->where('companies.id', $companyId)->exists();
    }

    /**
     * Returns the companies this user is allowed to see.
     * Superadmins get all companies.
     */
    public function accessibleCompanies(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->hasRole('superadmin')) {
            return Company::all();
        }

        return $this->companies;
    }
}

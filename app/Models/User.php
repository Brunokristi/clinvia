<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'global_role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->global_role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->global_role, ['super_admin', 'admin'], true);
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function userCompanies(): HasMany
    {
        return $this->hasMany(UserCompany::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_companies')
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function userBranches(): HasMany
    {
        return $this->hasMany(UserBranch::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branches')
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function canAccessCompany(int $companyId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function canAccessBranch(Branch $branch): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->canAccessCompany($branch->company_id)) {
            return true;
        }

        return $this->branches()
            ->where('branches.id', $branch->id)
            ->wherePivot('is_active', true)
            ->exists();
    }
}
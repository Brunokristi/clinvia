<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'company_id_number',
        'tax_id',
        'vat_id',
        'description',
        'email',
        'phone',
        'website',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function userCompanies(): HasMany
    {
        return $this->hasMany(UserCompany::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_companies')
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function apiClients(): HasMany
    {
        return $this->hasMany(ApiClient::class);
    }
}
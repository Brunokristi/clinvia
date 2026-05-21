<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'company_id',
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'icon',
        'featured_image_path',
        'duration_minutes',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function branchServices(): HasMany
    {
        return $this->hasMany(BranchService::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_services')
            ->withPivot(['custom_title', 'custom_description', 'is_available', 'sort_order'])
            ->withTimestamps();
    }
    public function information(): HasMany
    {
        return $this->hasMany(ServiceInformation::class)->orderBy('sort_order');
    }

    public function necessities(): HasMany
    {
        return $this->hasMany(ServiceNecessity::class)->orderBy('sort_order');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ServiceStep::class)->orderBy('sort_order');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(ServiceTag::class)->orderBy('sort_order');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ServiceFile::class)->orderBy('sort_order');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'provider_nit',
        'provider_code',
        'provider_name',
        'default_regime',
        'default_phone',
        'default_municipality_code',
        'settings',
        'active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'active' => 'boolean',
        ];
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ImportAlias::class);
    }

    public function importHistories(): HasMany
    {
        return $this->hasMany(ImportHistory::class);
    }
}
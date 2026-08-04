<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type',
        'internal_field',
        'original_header',
        'normalized_header',
        'source',
        'approved',
        'times_used',
        'average_confidence',
        'import_profile_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'approved' => 'boolean',
            'times_used' => 'integer',
            'average_confidence' => 'float',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(
            ImportProfile::class,
            'import_profile_id'
        );
    }
}
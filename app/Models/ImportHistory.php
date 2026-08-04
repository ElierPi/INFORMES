<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type',
        'import_profile_id',
        'original_filename',
        'original_path',
        'file_hash',
        'sheet_name',
        'header_row',
        'data_start_row',
        'records_count',
        'valid_records_count',
        'invalid_records_count',
        'warnings_count',
        'status',
        'mapping',
        'missing_fields',
        'warnings',
        'statistics',
        'metadata',
        'txt_path',
        'zip_path',
        'error_message',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'missing_fields' => 'array',
            'warnings' => 'array',
            'statistics' => 'array',
            'metadata' => 'array',
            'records_count' => 'integer',
            'valid_records_count' => 'integer',
            'invalid_records_count' => 'integer',
            'warnings_count' => 'integer',
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
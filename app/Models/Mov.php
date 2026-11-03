<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Mov extends Model
{
    public function analysis()
    {
        return $this->morphOne(EvidenceAnalysis::class, 'evidence');
    }

    protected $fillable = [
        'performance_target_id',
        'title',
        'description',
        'file_path',
        'original_name',
        'mime_type',
        'extension',
        'file_size',
        'uploaded_by',
        'submission_context',
    ];

    protected function casts(): array { return ['submission_context' => 'array']; }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function performanceTarget(): BelongsTo
    {
        return $this->belongsTo(PerformanceTarget::class);
    }

    public function getFileSizeLabelAttribute(): string
    {
        if ($this->file_size >= 1048576) {
            return number_format($this->file_size / 1048576, 2).' MB';
        }

        return number_format($this->file_size / 1024, 2).' KB';
    }

    public function getFileUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}

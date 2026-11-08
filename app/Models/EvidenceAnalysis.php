<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvidenceAnalysis extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['findings' => 'array']; }
    public function evidence() { return $this->morphTo(); }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['queued', 'analyzing'], true)
            || in_array($this->findings['processing']['state'] ?? null, ['queued', 'running'], true);
    }

    public function isReady(): bool
    {
        return $this->status === 'completed' && ! $this->isProcessing();
    }
}

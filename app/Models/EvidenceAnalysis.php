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

    public function processingFeedback(): array
    {
        $queued = $this->status === 'queued' && ($this->findings['processing']['state'] ?? null) !== 'running';
        return [
            'status' => $queued ? 'queued' : 'analyzing',
            'label' => $queued ? 'Queued for analysis' : 'Analyzing your MOV',
            'message' => $queued ? 'Your files are saved and waiting for the background worker. You can leave this page.' : 'The background worker is checking your files. Results will appear here automatically.',
            'warning' => $queued && $this->updated_at->lt(now()->subMinutes(2))
                ? 'Still waiting for a worker. Keep serve.ps1 running, or ask the administrator to check the MOV worker.'
                : (! $queued && $this->updated_at->lt(now()->subMinutes(5)) ? 'Analysis is taking longer than expected. Your files remain saved.' : ''),
        ];
    }
}

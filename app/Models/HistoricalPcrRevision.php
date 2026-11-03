<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricalPcrRevision extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'verified_at' => 'datetime'];
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    protected static function booted(): void
    {
        static::updating(function (self $revision) {
            if ($revision->getOriginal('verified_at') || $revision->isDirty(['snapshot', 'reason', 'number', 'historical_pcr_id', 'created_by'])) {
                throw new \LogicException('Save a new transcription revision instead of changing history.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Historical revisions must be retained.'));
    }
}

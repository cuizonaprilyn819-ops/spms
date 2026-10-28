<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TargetDocument extends Model
{
    public function evaluationCycle()
    {
        return $this->belongsTo(EvaluationCycle::class);
    }

    protected $fillable = ['created_by', 'evaluation_cycle_id', 'original_name', 'path', 'mime_type', 'status', 'summary', 'error'];

    public function drafts(): HasMany
    {
        return $this->hasMany(TargetDocumentDraft::class);
    }
}

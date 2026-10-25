<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetDocumentDraft extends Model
{
    protected $fillable = ['proposal', 'office_target_id'];

    protected function casts(): array
    {
        return ['proposal' => 'array'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(TargetDocument::class, 'target_document_id');
    }
}

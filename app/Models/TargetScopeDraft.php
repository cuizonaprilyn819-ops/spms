<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TargetScopeDraft extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function plan()
    {
        return $this->belongsTo(CommitmentPlan::class, 'commitment_plan_id');
    }

    public function documentDraft()
    {
        return $this->belongsTo(TargetDocumentDraft::class, 'document_draft_id');
    }

    public function proposal()
    {
        return $this->belongsTo(TargetProposal::class);
    }
}

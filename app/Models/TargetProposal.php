<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TargetProposal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['proposal' => 'array'];
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function cycle()
    {
        return $this->belongsTo(EvaluationCycle::class, 'evaluation_cycle_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewOffice()
    {
        return $this->belongsTo(Office::class, 'review_office_id');
    }
}

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
}

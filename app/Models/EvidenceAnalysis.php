<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvidenceAnalysis extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['findings' => 'array']; }
    public function evidence() { return $this->morphTo(); }
}

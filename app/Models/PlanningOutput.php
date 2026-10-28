<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanningOutput extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'validated_at' => 'datetime'];
    }

    public function target()
    {
        return $this->belongsTo(PerformanceTarget::class, 'performance_target_id');
    }
}

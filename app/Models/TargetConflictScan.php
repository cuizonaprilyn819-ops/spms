<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TargetConflictScan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['coverage' => 'array', 'snapshot' => 'array', 'published' => 'boolean', 'finished_at' => 'datetime'];
    }

    public function findings()
    {
        return $this->hasMany(TargetConflict::class, 'scan_id');
    }

    public function dispositions()
    {
        return $this->hasMany(TargetConflictDisposition::class, 'scan_id');
    }
}

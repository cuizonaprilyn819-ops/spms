<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TargetConflict extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['references' => 'array', 'blocking' => 'boolean'];
    }

    public function scan()
    {
        return $this->belongsTo(TargetConflictScan::class, 'scan_id');
    }

    public function dispositions()
    {
        return $this->hasMany(TargetConflictDisposition::class, 'conflict_id');
    }
}

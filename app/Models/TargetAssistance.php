<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TargetAssistance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['protected_values' => 'array', 'accepted_at' => 'datetime'];
    }
}

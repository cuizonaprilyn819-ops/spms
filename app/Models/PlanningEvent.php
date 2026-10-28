<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanningEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

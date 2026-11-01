<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanningGrant extends Model
{
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }
}

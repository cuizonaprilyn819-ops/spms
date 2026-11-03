<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricalPcrCorrection extends Model
{
    protected $guarded = ['id'];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}

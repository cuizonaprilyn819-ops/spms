<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReportStatusHistory extends Model
{
    protected $fillable = ['from_status', 'to_status', 'remarks', 'acted_by'];

    public function report(): MorphTo { return $this->morphTo(); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'acted_by'); }
}

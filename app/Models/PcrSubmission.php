<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PcrSubmission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'first_submitted_at' => 'datetime'];
    }

    public function report() { return $this->morphTo(); }

    public function isLate(): bool { return $this->first_submitted_at->gt($this->due_at); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Future provider links are explicit; email lookup must never auto-link an identity. */
class UserIdentity extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['subject', 'provider_email'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

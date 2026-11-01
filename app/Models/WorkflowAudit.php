<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowAudit extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = [];
    protected function casts(): array { return ['before' => 'array', 'after' => 'array']; }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
    public function subject() { return $this->morphTo(); }

    public static function record(Model $subject, string $action, ?array $before, ?array $after): void
    {
        static::create(['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
            'actor_id' => auth()->id(), 'action' => $action, 'before' => $before, 'after' => $after]);
    }
}

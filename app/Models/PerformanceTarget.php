<?php

namespace App\Models;

use Database\Factories\PerformanceTargetFactoryLike;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PerformanceTarget extends Model
{
    protected $fillable = [
        'targetable_type',
        'targetable_id',
        'target',
        'success_indicator',
        'weight',
        'deadline',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'weight' => 'decimal:2',
        ];
    }

    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accomplishments(): HasMany
    {
        return $this->hasMany(Accomplishment::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public static function factoryLike(): PerformanceTargetFactoryLike
    {
        return new PerformanceTargetFactoryLike();
    }
}

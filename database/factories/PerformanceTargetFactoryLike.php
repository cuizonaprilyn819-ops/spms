<?php

namespace Database\Factories;

use App\Models\Ipcr;
use App\Models\PerformanceTarget;
use App\Models\User;

class PerformanceTargetFactoryLike
{
    public function create(array $attributes = []): PerformanceTarget
    {
        $targetable = $attributes['targetable'] ?? Ipcr::factoryLike()->create();
        $createdBy = $attributes['created_by_user'] ?? User::factory()->create();

        return PerformanceTarget::create([
            'targetable_type' => $targetable::class,
            'targetable_id' => $targetable->getKey(),
            'target' => $attributes['target'] ?? 'Submit performance report',
            'success_indicator' => $attributes['success_indicator'] ?? 'Report submitted and accepted',
            'weight' => $attributes['weight'] ?? 20,
            'deadline' => $attributes['deadline'] ?? now()->addMonth()->toDateString(),
            'created_by' => $createdBy->id,
        ]);
    }
}

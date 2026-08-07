<?php

namespace Database\Factories;

use App\Models\Accomplishment;
use App\Models\PerformanceTarget;
use App\Models\User;

class AccomplishmentFactoryLike
{
    public function create(array $attributes = []): Accomplishment
    {
        $target = $attributes['performance_target'] ?? PerformanceTarget::factoryLike()->create();
        $submittedBy = $attributes['submitted_by_user'] ?? User::factory()->create();

        return Accomplishment::create([
            'performance_target_id' => $target->id,
            'details' => $attributes['details'] ?? 'Submitted accomplishment details',
            'completion_percentage' => $attributes['completion_percentage'] ?? 50,
            'submitted_by' => $submittedBy->id,
        ]);
    }
}

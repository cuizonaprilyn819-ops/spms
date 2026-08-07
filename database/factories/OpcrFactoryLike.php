<?php

namespace Database\Factories;

use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\Opcr;
use App\Models\User;

class OpcrFactoryLike
{
    public function create(array $attributes = []): Opcr
    {
        $cycle = $attributes['evaluation_cycle'] ?? EvaluationCycle::create([
            'year' => 2025,
            'semester' => EvaluationCycle::FIRST_SEMESTER,
            'status' => EvaluationCycle::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $office = $attributes['office'] ?? Office::factory()->create();
        $preparedBy = $attributes['prepared_by_user'] ?? User::factory()->create();

        return Opcr::create([
            'evaluation_cycle_id' => $cycle->id,
            'office_id' => $office->id,
            'title' => $attributes['title'] ?? 'Office Performance Commitment and Review',
            'success_indicators' => $attributes['success_indicators'] ?? 'Success indicators',
            'targets' => $attributes['targets'] ?? 'Targets',
            'status' => $attributes['status'] ?? Opcr::STATUS_DRAFT,
            'prepared_by' => $preparedBy->id,
        ]);
    }
}

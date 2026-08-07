<?php

namespace Database\Factories;

use App\Models\Dpcr;
use App\Models\Opcr;
use App\Models\User;

class DpcrFactoryLike
{
    public function create(array $attributes = []): Dpcr
    {
        $opcr = $attributes['opcr'] ?? Opcr::factoryLike()->create();
        $employee = $attributes['employee'] ?? User::factory()->create();
        $preparedBy = $attributes['prepared_by_user'] ?? User::factory()->create();

        return Dpcr::create([
            'opcr_id' => $opcr->id,
            'employee_id' => $employee->id,
            'title' => $attributes['title'] ?? 'Division Performance Commitment and Review',
            'success_indicators' => $attributes['success_indicators'] ?? 'DPCR success indicators',
            'targets' => $attributes['targets'] ?? 'DPCR targets',
            'status' => $attributes['status'] ?? Dpcr::STATUS_DRAFT,
            'prepared_by' => $preparedBy->id,
        ]);
    }
}

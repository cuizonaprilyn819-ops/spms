<?php

namespace Database\Factories;

use App\Models\Dpcr;
use App\Models\Upcr;
use App\Models\User;

class UpcrFactoryLike
{
    public function create(array $attributes = []): Upcr
    {
        $dpcr = $attributes['dpcr'] ?? Dpcr::factoryLike()->create();
        $employee = $attributes['employee'] ?? User::factory()->create();
        $preparedBy = $attributes['prepared_by_user'] ?? User::factory()->create();

        return Upcr::create([
            'dpcr_id' => $dpcr->id,
            'employee_id' => $employee->id,
            'title' => $attributes['title'] ?? 'Unit Performance Commitment and Review',
            'success_indicators' => $attributes['success_indicators'] ?? 'UPCR success indicators',
            'targets' => $attributes['targets'] ?? 'UPCR targets',
            'status' => $attributes['status'] ?? Upcr::STATUS_DRAFT,
            'prepared_by' => $preparedBy->id,
        ]);
    }
}

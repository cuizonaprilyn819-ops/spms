<?php

namespace Database\Factories;

use App\Models\Ipcr;
use App\Models\Upcr;
use App\Models\User;

class IpcrFactoryLike
{
    public function create(array $attributes = []): Ipcr
    {
        $upcr = $attributes['upcr'] ?? Upcr::factoryLike()->create();
        $employee = $attributes['employee'] ?? User::factory()->create();
        $preparedBy = $attributes['prepared_by_user'] ?? User::factory()->create();

        return Ipcr::create([
            'upcr_id' => $upcr->id,
            'employee_id' => $employee->id,
            'title' => $attributes['title'] ?? 'Individual Performance Commitment and Review',
            'success_indicators' => $attributes['success_indicators'] ?? 'IPCR success indicators',
            'targets' => $attributes['targets'] ?? 'IPCR targets',
            'status' => $attributes['status'] ?? Ipcr::STATUS_DRAFT,
            'prepared_by' => $preparedBy->id,
        ]);
    }
}

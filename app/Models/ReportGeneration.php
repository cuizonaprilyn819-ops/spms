<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportGeneration extends Model
{
    protected $fillable = ['generated_by', 'report_type', 'format', 'record_ids', 'snapshot', 'ip_address'];

    protected function casts(): array
    {
        return ['record_ids' => 'array', 'snapshot' => 'array'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportAttachment extends Model
{
    protected $fillable = ['report_type', 'report_id', 'uploaded_by', 'original_name', 'path'];
}

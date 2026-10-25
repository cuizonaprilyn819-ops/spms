<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class TargetMov extends Model
{
    protected $fillable = ['office_target_id', 'user_id', 'office_id', 'accomplishment', 'path', 'original_name'];
    public function employee() { return $this->belongsTo(User::class, 'user_id'); }
    public function target() { return $this->belongsTo(OfficeTarget::class, 'office_target_id'); }
}

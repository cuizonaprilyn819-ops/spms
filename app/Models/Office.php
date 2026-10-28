<?php

namespace App\Models;

use Database\Factories\OfficeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    /** @use HasFactory<OfficeFactory> */
    use HasFactory;

    protected $fillable = [
        'legacy_department_id',
        'name',
        'code',
        'description',
        'campus',
        'department_head_reference',
        'supervisor_reference',
        'is_active',
        'parent_office_id', 'planning_level', 'mandate', 'head_id', 'planning_reviewer_id', 'hierarchy_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'hierarchy_verified_at' => 'datetime',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function parentOffice()
    {
        return $this->belongsTo(self::class, 'parent_office_id');
    }

    public function head()
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    public function planningReviewer()
    {
        return $this->belongsTo(User::class, 'planning_reviewer_id');
    }

    public function targetHeads(): HasMany
    {
        return $this->employees()->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['Supervisor', 'HR Head']));
    }

    public function getAssignmentLabelAttribute(): string
    {
        return $this->name.' — '.($this->campus !== null ? 'Campus '.$this->campus : 'Campus unspecified')
            .' — '.($this->code ?: 'Office #'.$this->id);
    }

    public function opcrs(): HasMany
    {
        return $this->hasMany(Opcr::class);
    }
}

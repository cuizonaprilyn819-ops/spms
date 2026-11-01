<?php

namespace App\Models;

use App\Services\PlanningOrganization;
use Database\Factories\OfficeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    /** @use HasFactory<OfficeFactory> */
    use HasFactory;

    protected $attributes = ['organization_scope' => 'legacy'];

    protected $fillable = [
        'legacy_department_id',
        'name',
        'code',
        'description',
        'campus',
        'department_head_reference',
        'supervisor_reference',
        'is_active',
        'campus_id', 'organization_scope',
        'parent_office_id', 'planning_level', 'mandate', 'head_id', 'planning_reviewer_id', 'hierarchy_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'hierarchy_verified_at' => 'datetime',
        ];
    }

    public function scopeForCampus($query)
    {
        return $query->where('is_active', true)->where(function ($q) {
            $q->where(fn ($legacy) => $legacy->where('organization_scope', 'legacy')->where('campus', config('hinunangan.campus_id')));
            if (auth()->check()) {
                $ids = app(PlanningOrganization::class)->accessibleOfficeIds(auth()->user());
                $q->orWhere(fn ($scoped) => $scoped->where('organization_scope', '!=', 'legacy')->whereIn('id', $ids));
            }
        });
    }

    public function campusRecord()
    {
        return $this->belongsTo(Campus::class, 'campus_id');
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

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
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(User::class);
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

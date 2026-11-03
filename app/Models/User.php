<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    public function canProposeOfficeTarget(): bool
    {
        return $this->office && $this->office->is_active && (int) $this->office->campus === (int) config('hinunangan.campus_id')
            && ($this->hasAnyRole(['Supervisor', 'HR', 'HR Head', 'Planning Officer', 'Campus Director']) || (int) $this->office->head_id === $this->id);
    }

    public function organizationalReportType(): ?string
    {
        if ($this->hasRole('Campus Director')) return 'opcr';
        if (! $this->hasAnyRole(['Supervisor', 'HR', 'HR Head', 'Planning Officer', 'Campus Director'])) return null;
        if ($this->office?->planning_level === 'unit') return 'upcr';
        if (in_array($this->office?->planning_level, ['office', 'division'], true)) return 'dpcr';
        return $this->office_id && Office::where('parent_office_id', $this->office_id)->exists() ? 'dpcr' : 'upcr';
    }

    public function usesMyReports(): bool
    {
        return $this->hasRole('Employee') && ! $this->hasAnyRole(['Administrator', 'HR', 'HR Head', 'HRM Office II', 'Campus Director', 'Supervisor']);
    }

    public function reportAssignees(): Builder
    {
        return self::query()->where(function ($query) {
            $query->whereKey($this->id);
            if ($this->hasRole('Supervisor') && $this->office_id) {
                $query->orWhere(fn ($employees) => $employees->where('office_id', $this->office_id)
                    ->whereHas('roles', fn ($roles) => $roles->where('name', 'Employee')));
            }
        });
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'office_id',
        'position_title_id',
        'password',
        'planning_duties',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function positionTitle(): BelongsTo
    {
        return $this->belongsTo(PositionTitle::class);
    }
}

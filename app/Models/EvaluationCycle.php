<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationCycle extends Model
{
    protected $attributes = ['planning_enabled' => false];

    public const FIRST_SEMESTER = '1st Semester';

    public const SECOND_SEMESTER = '2nd Semester';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'year',
        'semester',
        'status',
        'opened_at',
        'closed_at',
        'planning_enabled', 'planning_readiness',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'planning_enabled' => 'boolean',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function semesters(): array
    {
        return [
            self::FIRST_SEMESTER,
            self::SECOND_SEMESTER,
        ];
    }

    public function getLabelAttribute(): string
    {
        return "{$this->year} - ".self::semesterLabel($this->semester);
    }

    public static function semesterLabel(string $semester): string
    {
        return match ($semester) {
            self::FIRST_SEMESTER => '1st Semester (January–June)',
            self::SECOND_SEMESTER => '2nd Semester (July–December)',
            default => $semester,
        };
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function opcrs(): HasMany
    {
        return $this->hasMany(Opcr::class);
    }
}

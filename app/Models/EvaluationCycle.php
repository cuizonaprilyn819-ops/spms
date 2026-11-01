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
        'planning_enabled', 'planning_readiness', 'pmt_chairperson_id',
        'pcr_creation_closes_at', 'pcr_submission_due_at', 'pcr_reminder_days',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'planning_enabled' => 'boolean',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'pcr_creation_closes_at' => 'datetime',
            'pcr_submission_due_at' => 'datetime',
            'pcr_reminder_days' => 'integer',
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

    public function periodStart(): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::create($this->year, $this->semester === self::FIRST_SEMESTER ? 1 : 7, 1)->startOfDay();
    }

    public function periodEnd(): \Illuminate\Support\Carbon
    {
        return $this->periodStart()->addMonths(5)->endOfMonth();
    }

    public function validateSchedule(array $data): void
    {
        if (! $this->isOpen()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['evaluation_cycle_id' => 'The referenced evaluation period is closed.']);
        }
        foreach (['deadline', 'start_date'] as $field) {
            if (! empty($data[$field]) && ! \Illuminate\Support\Carbon::parse($data[$field])->betweenIncluded($this->periodStart(), $this->periodEnd())) {
                throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Choose a date within '.$this->label.' ('.$this->periodStart()->toDateString().' to '.$this->periodEnd()->toDateString().').']);
            }
        }
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function ensurePcrCreationOpen(): void
    {
        if (! $this->isOpen() || ($this->pcr_creation_closes_at && now()->gt($this->pcr_creation_closes_at))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['evaluation_cycle_id' => 'PCR creation is closed for this period. Contact the Planning Officer.']);
        }
    }

    public function submissionDueAt(): \Illuminate\Support\Carbon
    {
        return $this->pcr_submission_due_at ?? $this->periodEnd();
    }

    public function pmtChairperson()
    {
        return $this->belongsTo(User::class, 'pmt_chairperson_id');
    }

    public function opcrs(): HasMany
    {
        return $this->hasMany(Opcr::class);
    }
}

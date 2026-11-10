<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

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
        'multi_campus_enabled', 'university_office_id', 'planning_root_level',
        'pcr_creation_closes_at', 'pcr_submission_due_at', 'pcr_reminder_days',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'planning_enabled' => 'boolean',
            'multi_campus_enabled' => 'boolean',
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

    public function usesUniversityOpcr(): bool
    {
        return $this->multi_campus_enabled && ($this->planning_root_level ?? 'university') === 'university';
    }

    public function planningLevels(): array
    {
        $levels = ['opcr' => $this->multi_campus_enabled ? 'Campus OPCR' : 'OPCR', 'dpcr' => 'DPCR', 'upcr' => 'UPCR', 'ipcr' => 'IPCR'];

        return $this->usesUniversityOpcr() ? ['university' => 'University OPCR', ...$levels] : $levels;
    }

    public function getLabelAttribute(): string
    {
        return "{$this->year} - ".self::semesterLabel($this->semester);
    }

    public static function semesterLabel(string $semester): string
    {
        return match ($semester) {
            self::FIRST_SEMESTER => '1st Quarter (January–June)',
            self::SECOND_SEMESTER => '2nd Quarter (July–December)',
            default => $semester,
        };
    }

    public function periodStart(): Carbon
    {
        return Carbon::create($this->year, $this->semester === self::FIRST_SEMESTER ? 1 : 7, 1)->startOfDay();
    }

    public function periodEnd(): Carbon
    {
        return $this->periodStart()->addMonths(5)->endOfMonth();
    }

    public function validateSchedule(array $data): void
    {
        if (! $this->isOpen()) {
            throw ValidationException::withMessages(['evaluation_cycle_id' => 'The referenced evaluation period is closed.']);
        }
        foreach (['deadline', 'start_date'] as $field) {
            if (! empty($data[$field]) && ! Carbon::parse($data[$field])->betweenIncluded($this->periodStart(), $this->periodEnd())) {
                throw ValidationException::withMessages([$field => 'Choose a date within '.$this->label.' ('.$this->periodStart()->toDateString().' to '.$this->periodEnd()->toDateString().').']);
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
            throw ValidationException::withMessages(['evaluation_cycle_id' => 'PCR creation is closed for this period. Contact the Planning Officer.']);
        }
    }

    public function submissionDueAt(): Carbon
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

<?php

namespace App\Console\Commands;

use App\Models\Office;
use App\Models\PositionTitle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ImportHrmisCatalogues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'catalogues:import
                            {departments : Full path to the department SQL export}
                            {positions : Full path to the CSC item-name SQL export}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import HRMIS department and CSC position catalogues without executing the source SQL';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $departmentRows = $this->recordsFromDump((string) $this->argument('departments'), 'department');
        $positionRows = $this->recordsFromDump((string) $this->argument('positions'), 'cscitemname');

        $offices = $this->officeRecords($departmentRows);
        $positions = $this->positionRecords($positionRows);

        DB::transaction(function () use ($offices, $positions): void {
            foreach (array_chunk($offices, 100) as $chunk) {
                Office::query()->upsert(
                    $chunk,
                    ['legacy_department_id'],
                    [
                        'name',
                        'code',
                        'description',
                        'campus',
                        'department_head_reference',
                        'supervisor_reference',
                        'is_active',
                        'updated_at',
                    ],
                );
            }

            foreach (array_chunk($positions, 100) as $chunk) {
                PositionTitle::query()->upsert(
                    $chunk,
                    ['legacy_csc_item_id'],
                    [
                        'title',
                        'description',
                        'salary_grade',
                        'campus',
                        'is_active',
                        'updated_at',
                    ],
                );
            }
        });

        $this->components->info(sprintf(
            'Imported %d departments and %d CSC position titles.',
            count($offices),
            count($positions),
        ));
        $this->line('Inactive legacy records were retained but are not offered in the employee assignment form.');

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return list<array<string, mixed>>
     */
    private function officeRecords(array $rows): array
    {
        /** @var array<string, int|null> $usedNames */
        $usedNames = [];
        /** @var array<string, int|null> $usedCodes */
        $usedCodes = [];

        Office::query()
            ->get(['name', 'code', 'legacy_department_id'])
            ->each(function (Office $office) use (&$usedNames, &$usedCodes): void {
                $usedNames[$this->comparisonKey($office->name)] = $office->legacy_department_id;

                if ($office->code !== null) {
                    $usedCodes[$this->comparisonKey($office->code)] = $office->legacy_department_id;
                }
            });

        $now = now();
        $records = [];

        foreach ($rows as $row) {
            $legacyId = (int) ($row['id'] ?? 0);

            if ($legacyId === 0) {
                continue;
            }

            $sourceName = $this->firstMeaningfulValue($row['Description'] ?? null, $row['DepartmentName'] ?? null);
            $sourceCode = $this->firstMeaningfulValue($row['DepartmentName'] ?? null, 'DEPT');

            if ($sourceName === null) {
                continue;
            }

            $records[] = [
                'legacy_department_id' => $legacyId,
                'name' => $this->uniqueValue($usedNames, $sourceName, $legacyId, 255, " #{$legacyId}"),
                'code' => $this->uniqueValue($usedCodes, $sourceCode, $legacyId, 50, "-{$legacyId}"),
                'description' => $this->nullableValue($row['Description'] ?? null),
                'campus' => $this->nullableInteger($row['Campus'] ?? null),
                'department_head_reference' => $this->nullableValue($row['DepartmentHead'] ?? null),
                'supervisor_reference' => $this->nullableValue($row['Supervisor'] ?? null),
                'is_active' => $this->isActive($row),
                'created_at' => $this->dateOrNow($row['created_at'] ?? null, $now),
                'updated_at' => $this->dateOrNow($row['updated_at'] ?? null, $now),
            ];
        }

        return $records;
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return list<array<string, mixed>>
     */
    private function positionRecords(array $rows): array
    {
        $now = now();
        $records = [];

        foreach ($rows as $row) {
            $legacyId = (int) ($row['id'] ?? 0);
            $title = $this->nullableValue($row['ItemName'] ?? null);

            if ($legacyId === 0 || $title === null) {
                continue;
            }

            $records[] = [
                'legacy_csc_item_id' => $legacyId,
                'title' => Str::limit($title, 255, ''),
                'description' => Str::limit((string) ($this->nullableValue($row['Description'] ?? null) ?? ''), 255, '') ?: null,
                'salary_grade' => $this->nullableValue($row['SalaryGrade'] ?? null),
                'campus' => $this->nullableInteger($row['campus'] ?? null),
                'is_active' => $this->isActive($row),
                'created_at' => $this->dateOrNow($row['created_at'] ?? null, $now),
                'updated_at' => $this->dateOrNow($row['updated_at'] ?? null, $now),
            ];
        }

        return $records;
    }

    /**
     * @param  array<string, int|null>  $usedValues
     */
    private function uniqueValue(array &$usedValues, string $value, int $legacyId, int $length, string $suffix): string
    {
        $base = Str::limit(trim($value), $length, '');
        $candidate = $base;
        $candidateKey = $this->comparisonKey($candidate);

        if (! isset($usedValues[$candidateKey]) || $usedValues[$candidateKey] === $legacyId) {
            $usedValues[$candidateKey] = $legacyId;

            return $candidate;
        }

        $number = 1;

        do {
            $candidateSuffix = $number === 1 ? $suffix : "{$suffix}-{$number}";
            $candidate = Str::limit($base, $length - Str::length($candidateSuffix), '').$candidateSuffix;
            $number++;
            $candidateKey = $this->comparisonKey($candidate);
        } while (isset($usedValues[$candidateKey]) && $usedValues[$candidateKey] !== $legacyId);

        $usedValues[$candidateKey] = $legacyId;

        return $candidate;
    }

    private function comparisonKey(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function recordsFromDump(string $path, string $table): array
    {
        $sql = @file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException("Unable to read {$path}.");
        }

        $pattern = sprintf('/INSERT INTO `%s` \(([^)]+)\) VALUES\s*/', preg_quote($table, '/'));

        if (preg_match($pattern, $sql, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            throw new RuntimeException("No INSERT statement for {$table} was found in {$path}.");
        }

        $columns = array_map(
            static fn (string $column): string => trim($column, " `\t\r\n"),
            explode(',', $matches[1][0]),
        );
        $valuesStart = $matches[0][1] + strlen($matches[0][0]);
        $tuples = $this->tuplesUntilStatementEnd($sql, $valuesStart);
        $records = [];

        foreach ($tuples as $tuple) {
            $fields = $this->fieldsInTuple($tuple);

            if (count($fields) !== count($columns)) {
                throw new RuntimeException("A {$table} row has an unexpected number of fields.");
            }

            /** @var array<string, string|null> $record */
            $record = array_combine($columns, $fields);
            $records[] = $record;
        }

        return $records;
    }

    /**
     * @return list<string>
     */
    private function tuplesUntilStatementEnd(string $sql, int $start): array
    {
        $tuples = [];
        $depth = 0;
        $quote = false;
        $escaped = false;
        $tupleStart = null;
        $length = strlen($sql);

        for ($index = $start; $index < $length; $index++) {
            $character = $sql[$index];

            if ($quote) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === "'") {
                    $quote = false;
                }

                continue;
            }

            if ($character === "'") {
                $quote = true;
            } elseif ($character === '(') {
                if ($depth === 0) {
                    $tupleStart = $index + 1;
                }

                $depth++;
            } elseif ($character === ')') {
                $depth--;

                if ($depth === 0 && $tupleStart !== null) {
                    $tuples[] = substr($sql, $tupleStart, $index - $tupleStart);
                    $tupleStart = null;
                }
            } elseif ($character === ';' && $depth === 0) {
                return $tuples;
            }
        }

        throw new RuntimeException('The INSERT statement has no terminating semicolon.');
    }

    /**
     * @return list<string|null>
     */
    private function fieldsInTuple(string $tuple): array
    {
        $fields = [];
        $field = '';
        $quote = false;
        $escaped = false;
        $length = strlen($tuple);

        for ($index = 0; $index < $length; $index++) {
            $character = $tuple[$index];

            if ($quote) {
                $field .= $character;

                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === "'") {
                    $quote = false;
                }

                continue;
            }

            if ($character === "'") {
                $quote = true;
                $field .= $character;
            } elseif ($character === ',') {
                $fields[] = $this->decodeSqlValue($field);
                $field = '';
            } else {
                $field .= $character;
            }
        }

        $fields[] = $this->decodeSqlValue($field);

        return $fields;
    }

    private function decodeSqlValue(string $value): ?string
    {
        $value = trim($value);

        if (strtoupper($value) === 'NULL') {
            return null;
        }

        if (Str::startsWith($value, "'") && Str::endsWith($value, "'")) {
            $value = substr($value, 1, -1);
            $value = str_replace(
                ['\\\\', "\\'", '\\0', '\\n', '\\r', '\\Z'],
                ['\\', "'", "\0", "\n", "\r", "\x1a"],
                $value,
            );
        }

        return $value;
    }

    private function firstMeaningfulValue(?string ...$values): ?string
    {
        foreach ($values as $value) {
            $value = $this->nullableValue($value);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function nullableValue(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function nullableInteger(?string $value): ?int
    {
        return $value !== null && is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function isActive(array $row): bool
    {
        return (int) ($row['Active'] ?? 1) === 1 && ($row['deleted_at'] ?? null) === null;
    }

    private function dateOrNow(?string $date, mixed $fallback): mixed
    {
        return $this->nullableValue($date) ?? $fallback;
    }
}

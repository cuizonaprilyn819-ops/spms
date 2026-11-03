<?php

namespace Database\Seeders;

use App\Models\EvaluationCycle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EvaluationCycleSeeder extends Seeder
{
    public function run(bool $setCurrent = false): void
    {
        DB::transaction(function () use ($setCurrent): void {
            $initialize = ! EvaluationCycle::query()->exists();

            foreach (range(2025, 2028) as $year) {
                foreach (EvaluationCycle::semesters() as $semester) {
                    EvaluationCycle::firstOrCreate(
                        ['year' => $year, 'semester' => $semester],
                        ['status' => EvaluationCycle::STATUS_CLOSED],
                    );
                }
            }

            // Later seeding must preserve an administrator's period selection,
            // including the deliberate choice to keep every period closed.
            if (! $initialize && ! $setCurrent) {
                return;
            }

            $current = EvaluationCycle::where('year', 2026)
                ->where('semester', EvaluationCycle::SECOND_SEMESTER)
                ->firstOrFail();

            EvaluationCycle::whereKeyNot($current->id)
                ->where('status', EvaluationCycle::STATUS_OPEN)
                ->update(['status' => EvaluationCycle::STATUS_CLOSED, 'closed_at' => now()]);

            if (! $current->isOpen()) {
                $current->update([
                    'status' => EvaluationCycle::STATUS_OPEN,
                    'opened_at' => now(),
                    'closed_at' => null,
                ]);
            }
        });
    }
}

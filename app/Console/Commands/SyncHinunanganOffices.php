<?php

namespace App\Console\Commands;

use App\Models\Office;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncHinunanganOffices extends Command
{
    protected $signature = 'offices:sync-hinunangan {--dry-run : Preview without changing records}';

    protected $description = 'Apply the approved active office list to Hinunangan Campus only';

    public function handle(): int
    {
        $campus = (int) config('hinunangan.campus_id');
        $names = config('hinunangan.active_offices');
        DB::transaction(function () use ($campus, $names) {
            $offices = Office::where('campus', $campus)->lockForUpdate()->get();
            $selected = [];
            $aliases = [
                'Office of the Faculty Dean' => 'College Dean',
                'Accounting Office' => 'Accounting',
                'Bachelor of Science in Agriculture' => 'Bachelor of Science in Agriculture (BSA)',
            ];
            foreach ($names as $name) {
                // Match imported codes first: BSA and the full agriculture name are distinct entries.
                $matchName = $aliases[$name] ?? $name;
                $matches = $offices->filter(fn ($office) => ! in_array($office->id, $selected, true)
                    && $this->key($office->code ?? '') === $this->key($matchName));
                if ($matches->isEmpty()) {
                    $matches = $offices->filter(fn ($office) => ! in_array($office->id, $selected, true)
                        && in_array($this->key($office->name), [$this->key($matchName), $this->key($name)], true));
                }
                $office = $matches->sortByDesc('is_active')->first();
                if ($office) {
                    $selected[] = $office->id;
                    $this->line("Activate #{$office->id}: {$office->name} => {$name}");
                    if (! $this->option('dry-run')) {
                        $storedName = Office::where('name', $name)->where('id', '!=', $office->id)->exists()
                            ? $name.' (Hinunangan)' : $name;
                        $office->update(['name' => $storedName, 'is_active' => true]);
                    }
                } else {
                    $this->line("Create: {$name}");
                    if (! $this->option('dry-run')) {
                        $storedName = Office::where('name', $name)->exists() ? $name.' (Hinunangan)' : $name;
                        $selected[] = Office::create(['name' => $storedName, 'campus' => $campus, 'is_active' => true])->id;
                    }
                }
            }
            $inactive = $offices->whereNotIn('id', $selected);
            foreach ($inactive as $office) {
                $this->line("Deactivate #{$office->id}: {$office->name}");
            }
            if (! $this->option('dry-run')) {
                Office::where('campus', $campus)->whereNotIn('id', $selected)->update(['is_active' => false]);
            }
            $this->info(count($names).' approved offices; campus '.$campus.'. Employee assignments preserved.');
        });

        return self::SUCCESS;
    }

    private function key(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', preg_replace('/(?: #\d+|-\d+| \(Hinunangan\))$/', '', $value))));
    }
}

<?php

namespace App\Services;

use App\Models\{Office, PositionTitle, User};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ReferenceData
{
    private function namespace(): string
    {
        $connection = config('database.default');
        return 'reference:v1:'.hash('sha256', json_encode([$connection, config("database.connections.$connection.database"), config("database.connections.$connection.host"), config("database.connections.$connection.url")])).':';
    }

    public function clear(): void
    {
        // New readers use a new generation. An older in-flight lookup cannot
        // repopulate a key that new readers will use after a change.
        $cache = Cache::store('reference');
        $namespace = $this->namespace();
        $previous = $cache->get($namespace.'generation');
        $cache->forever($namespace.'generation', (string) Str::uuid());
        if ($previous) foreach (['office-labels', 'position-titles'] as $name) $cache->forget($namespace.$previous.':'.$name);
    }

    private function remember(string $name, \Closure $load): array
    {
        // Never publish uncommitted records into a shared file cache.
        if (\Illuminate\Support\Facades\DB::connection()->transactionLevel() > 0) return $load();
        $cache = Cache::store('reference');
        $key = $this->namespace().'generation';
        $cache->add($key, (string) Str::uuid(), 86400);
        $generation = $cache->get($key);
        return $cache->remember($this->namespace().$generation.':'.$name, 300, $load);
    }

    public function officeOptions(?User $employee = null): Collection
    {
        // Eligibility remains live and uses the existing campus/authorization scope.
        $ids = Office::where(function ($query) use ($employee) {
            $query->where(fn ($active) => $active->forCampus());
            if ($employee?->office_id) $query->orWhere('id', $employee->office_id);
        })->pluck('id');
        $rows = $this->remember('office-labels', fn () => Office::orderBy('name')->get(['id', 'name', 'code', 'campus', 'is_active'])->toArray());
        return Office::hydrate($rows)->whereIn('id', $ids)->values();
    }

    public function positionOptions(): Collection
    {
        return PositionTitle::hydrate($this->remember('position-titles', fn () => PositionTitle::where('is_active', true)
            ->orderBy('title')->get(['id', 'title', 'salary_grade', 'is_active'])->toArray()));
    }
}

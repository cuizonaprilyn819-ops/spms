<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class TargetAllocation
{
    /** Drivers are confirmed values for one measure and period, never AI estimates. */
    public function suggest(float $total, array $drivers, bool $whole = true): array
    {
        if (! $drivers || array_sum(array_column($drivers, 'value')) <= 0) {
            return ['shares' => [], 'unallocated' => $total];
        }
        $scale = $whole ? 1 : 100;
        $remaining = (int) round($total * $scale);
        $shares = array_fill_keys(array_keys($drivers), 0);
        $active = $drivers;
        while ($remaining > 0 && $active) {
            $sum = array_sum(array_column($active, 'value'));
            if ($sum <= 0) {
                break;
            }
            $parts = [];
            $before = $remaining;
            foreach ($active as $id => $driver) {
                $cap = isset($driver['capacity']) ? (int) floor($driver['capacity'] * $scale) : PHP_INT_MAX;
                $raw = $before * $driver['value'] / $sum;
                $give = min((int) floor($raw), max(0, $cap - $shares[$id]));
                $shares[$id] += $give;
                $remaining -= $give;
                $parts[$id] = ['fraction' => $raw - floor($raw), 'cap' => $cap, 'office_id' => $driver['office_id'] ?? $id];
            }
            uksort($parts, fn ($a, $b) => ($parts[$b]['fraction'] <=> $parts[$a]['fraction']) ?: (($parts[$a]['office_id'] <=> $parts[$b]['office_id']) ?: ($a <=> $b)));
            foreach ($parts as $id => $part) {
                if ($remaining > 0 && $shares[$id] < $part['cap']) {
                    $shares[$id]++;
                    $remaining--;
                }
                if ($shares[$id] >= $part['cap']) {
                    unset($active[$id]);
                }
            }
            if ($before === $remaining) {
                break;
            }
        }

        return ['shares' => array_map(fn ($n) => $n / $scale, $shares), 'unallocated' => $remaining / $scale];
    }

    public function assertAvailable(float $parent, float $used, float $requested): void
    {
        if ((int) round(($used + $requested) * 100) > (int) round($parent * 100)) {
            throw ValidationException::withMessages(['quantity' => 'This quantity exceeds the parent’s remaining allocation.']);
        }
    }
}

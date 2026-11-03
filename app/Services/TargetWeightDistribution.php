<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class TargetWeightDistribution
{
    /** Budgets are absolute percentages of the report, never percentages per assignee. */
    public function distribute(array $targets, array $plan): array
    {
        $validator = Validator::make($plan, [
            'pools' => ['required', 'array', 'min:1', 'max:120'],
            'pools.*.id' => ['required', 'string', 'distinct', 'max:100'],
            'pools.*.label' => ['required', 'string', 'max:2000'],
            'pools.*.budget' => ['required', 'numeric', 'min:0.01', 'max:100', 'decimal:0,2'],
            'pools.*.source_basis' => ['required', 'string', 'max:5000'],
            'mappings' => ['required', 'array', 'size:'.count($targets)],
            'mappings.*.target_index' => ['required', 'integer', 'distinct', Rule::in(array_keys($targets))],
            'mappings.*.pool_id' => ['required', 'string', Rule::in(array_column($plan['pools'] ?? [], 'id'))],
            'mappings.*.share' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'mappings.*.fixed_weight' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:100', 'decimal:0,2'],
            'mappings.*.reason' => ['required', 'string', 'max:3000'],
            'warnings' => ['present', 'nullable', 'string', 'max:6000'],
        ]);
        if ($validator->fails()) throw new RuntimeException('AI returned an incomplete weight distribution. No weights were changed.');
        $plan = $validator->validated();
        if (array_sum(array_map(fn ($p) => (int) round($p['budget'] * 100), $plan['pools'])) !== 10000) {
            throw new RuntimeException('The proposed section/program budgets must total exactly 100%. Review conflicting source percentages. No weights were changed.');
        }
        foreach ($plan['pools'] as $pool) {
            $rows = array_values(array_filter($plan['mappings'], fn ($r) => $r['pool_id'] === $pool['id']));
            $budget = (int) round($pool['budget'] * 100);
            if (! $rows || $budget < count($rows)) throw new RuntimeException('A weight budget is empty or too small for its targets. No weights were changed.');
            // Give every target at least 0.01%, then distribute integer hundredths.
            $fixed = array_filter($rows, fn ($r) => isset($r['fixed_weight']));
            $variable = array_filter($rows, fn ($r) => ! isset($r['fixed_weight']));
            $fixedTotal = array_sum(array_map(fn ($r) => (int) round($r['fixed_weight'] * 100), $fixed));
            if ($fixedTotal + count($variable) > $budget || (! $variable && $fixedTotal !== $budget)) {
                throw new RuntimeException('Explicit source target weights conflict with their budget. No weights were changed; review the source percentages.');
            }
            $available = $budget - $fixedTotal - count($variable);
            $shares = array_sum(array_column($variable, 'share'));
            $allocations = [];
            foreach ($rows as $row) {
                if (isset($row['fixed_weight'])) {
                    $allocations[] = [...$row, 'cents' => (int) round($row['fixed_weight'] * 100), 'remainder' => -1];
                    continue;
                }
                $exact = $available * $row['share'] / $shares;
                $allocations[] = [...$row, 'cents' => 1 + (int) floor($exact), 'remainder' => $exact - floor($exact)];
            }
            $left = $budget - array_sum(array_column($allocations, 'cents'));
            usort($allocations, fn ($a, $b) => ($b['remainder'] <=> $a['remainder']) ?: ($a['target_index'] <=> $b['target_index']));
            foreach ($allocations as $i => $allocation) {
                $index = $allocation['target_index'];
                $targets[$index]['weight'] = ($allocation['cents'] + ($i < $left ? 1 : 0)) / 100;
                $targets[$index]['weight_reason'] = $allocation['reason'];
                $targets[$index]['weight_budget'] = ['id' => $pool['id'], 'label' => $pool['label'], 'budget' => $pool['budget'], 'source_basis' => $pool['source_basis']];
                $targets[$index]['weight_warnings'] = $plan['warnings'];
            }
        }
        return $targets;
    }
}

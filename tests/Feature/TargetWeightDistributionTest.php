<?php

namespace Tests\Feature;

use App\Models\{EvaluationCycle, TargetDocument, User};
use App\Services\TargetWeightDistribution;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Storage};
use Tests\TestCase;

class TargetWeightDistributionTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): array
    {
        return ['pools' => [
            ['id' => 'strategic', 'label' => 'Strategic objectives', 'budget' => 60, 'source_basis' => 'Page 1: 60%'],
            ['id' => 'core', 'label' => 'Core functions', 'budget' => 30, 'source_basis' => 'Page 8: 30%'],
            ['id' => 'support', 'label' => 'Support functions', 'budget' => 10, 'source_basis' => 'Page 9: 10%'],
        ], 'mappings' => [
            ['target_index' => 0, 'pool_id' => 'strategic', 'share' => 1, 'reason' => 'Comparable scope'],
            ['target_index' => 1, 'pool_id' => 'strategic', 'share' => 1, 'reason' => 'Comparable scope'],
            ['target_index' => 2, 'pool_id' => 'core', 'share' => 1, 'reason' => 'Source target weight', 'fixed_weight' => 20],
            ['target_index' => 3, 'pool_id' => 'core', 'share' => 1, 'reason' => 'Remaining core budget'],
            ['target_index' => 4, 'pool_id' => 'support', 'share' => 1, 'reason' => 'Support workload'],
            ['target_index' => 5, 'pool_id' => 'support', 'share' => 1, 'reason' => 'Support workload'],
            ['target_index' => 6, 'pool_id' => 'support', 'share' => 1, 'reason' => 'Support workload'],
        ], 'warnings' => 'Source objective percentages conflict: review proposed reconciliation.'];
    }

    public function test_rounding_preserves_budgets_fixed_weights_and_independent_measures(): void
    {
        $targets = array_fill(0, 7, ['target_value' => 2, 'unit_of_measure' => 'maintenance activities']);
        $result = app(TargetWeightDistribution::class)->distribute($targets, $this->plan());
        $this->assertEquals(100, array_sum(array_column($result, 'weight')));
        $this->assertEquals([30, 30, 20, 10, 3.34, 3.33, 3.33], array_column($result, 'weight'));
        foreach ($result as $target) {
            $this->assertSame(2, $target['target_value']);
            $this->assertSame('maintenance activities', $target['unit_of_measure']);
            $this->assertStringContainsString('conflict', $target['weight_warnings']);
        }
    }

    public function test_invalid_total_and_duplicate_or_missing_targets_are_rejected(): void
    {
        $targets = array_fill(0, 7, []);
        foreach (['budget', 'duplicate', 'fixed'] as $case) {
            $plan = $this->plan();
            if ($case === 'budget') $plan['pools'][0]['budget'] = 70;
            if ($case === 'duplicate') $plan['mappings'][1]['target_index'] = 0;
            if ($case === 'fixed') $plan['mappings'][2]['fixed_weight'] = 40;
            try {
                app(TargetWeightDistribution::class)->distribute($targets, $plan);
                $this->fail('Invalid weight distribution was accepted.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('No weights were changed', $exception->getMessage());
            }
        }
    }

    public function test_owner_can_generate_reviewable_weights_without_changing_quantities(): void
    {
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        Storage::disk('local')->put('source.pdf', 'synthetic fixture');
        config(['spms.ai.enabled' => true, 'spms.ai.api_key' => 'test-key', 'spms.ai.document_model' => 'test-model']);
        $owner = User::factory()->create()->assignRole('Planning Officer');
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER, 'status' => 'open']);
        $document = TargetDocument::create(['created_by' => $owner->id, 'evaluation_cycle_id' => $cycle->id, 'original_name' => 'source.pdf', 'path' => 'source.pdf', 'mime_type' => 'application/pdf', 'status' => 'review']);
        for ($i = 0; $i < 7; $i++) $document->drafts()->create(['proposal' => ['title' => 'Target '.$i, 'success_indicator' => 'Two activities', 'source_excerpt' => 'Two activities', 'target_value' => 2, 'unit_of_measure' => 'activities', 'weight' => null]]);
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($this->plan())]]]]])]);
        $other = User::factory()->create()->assignRole('Planning Officer');
        $this->actingAs($other)->post(route('target-documents.weights', $document))->assertForbidden();
        Http::assertNothingSent();
        $this->actingAs($owner)->post(route('target-documents.weights', $document))->assertRedirect();
        $this->assertSame('review', $document->fresh()->status);
        $this->assertNull($document->fresh()->error);
        $this->assertEquals(100, $document->drafts()->get()->sum(fn ($d) => $d->proposal['weight']));
        $this->get(route('target-documents.show', $document))->assertOk()->assertSee('100.00%')->assertSee('2 activities')->assertSee('Source objective percentages conflict');
        $this->assertDatabaseCount('office_targets', 0);
        $assigned = \App\Models\OfficeTarget::create(['title' => 'Assigned', 'success_indicator' => 'Two activities', 'target_value' => 2, 'unit_of_measure' => 'activities', 'weight' => 30, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $owner->id]);
        $document->drafts()->first()->update(['office_target_id' => $assigned->id]);
        $before = $document->drafts()->pluck('proposal')->all();
        $this->post(route('target-documents.weights', $document))->assertRedirect();
        $this->assertStringContainsString('before assigning', $document->fresh()->error);
        $this->assertSame($before, $document->drafts()->pluck('proposal')->all());
        Http::assertSentCount(1);
    }
}

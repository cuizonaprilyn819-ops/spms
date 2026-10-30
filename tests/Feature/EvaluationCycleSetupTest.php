<?php

namespace Tests\Feature;

use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\Opcr;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EvaluationCycleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationCycleSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_setup_creates_the_eight_periods_with_july_december_2026_current(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('evaluation_cycles', 8);
        foreach (range(2025, 2028) as $year) {
            foreach (EvaluationCycle::semesters() as $semester) {
                $this->assertDatabaseHas('evaluation_cycles', [
                    'year' => $year,
                    'semester' => $semester,
                    'status' => $year === 2026 && $semester === EvaluationCycle::SECOND_SEMESTER
                        ? EvaluationCycle::STATUS_OPEN : EvaluationCycle::STATUS_CLOSED,
                    'planning_enabled' => false,
                ]);
            }
        }
        $this->assertDatabaseCount('opcrs', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_repeated_setup_preserves_the_current_period_and_historical_report_links(): void
    {
        $cycle = EvaluationCycle::create([
            'year' => 2027, 'semester' => EvaluationCycle::FIRST_SEMESTER,
            'status' => EvaluationCycle::STATUS_OPEN, 'opened_at' => now()->subDay(),
            'planning_enabled' => true, 'planning_readiness' => 'Existing readiness record',
        ]);
        $report = Opcr::create([
            'evaluation_cycle_id' => $cycle->id, 'office_id' => Office::factory()->create()->id,
            'prepared_by' => User::factory()->create()->id, 'title' => 'Existing office commitment',
        ]);
        $before = $cycle->fresh()->getAttributes();

        $this->seed(EvaluationCycleSeeder::class);
        $ids = EvaluationCycle::orderBy('id')->pluck('id')->all();
        $this->seed(EvaluationCycleSeeder::class);

        $this->assertSame($ids, EvaluationCycle::orderBy('id')->pluck('id')->all());
        $this->assertSame($before, $cycle->fresh()->getAttributes());
        $this->assertSame($cycle->id, $report->fresh()->evaluation_cycle_id);
        $this->assertSame(1, EvaluationCycle::where('status', EvaluationCycle::STATUS_OPEN)->count());
    }

    public function test_setup_does_not_reopen_periods_an_administrator_has_closed(): void
    {
        $this->seed(EvaluationCycleSeeder::class);
        EvaluationCycle::where('status', EvaluationCycle::STATUS_OPEN)
            ->update(['status' => EvaluationCycle::STATUS_CLOSED, 'closed_at' => now()]);

        $this->artisan('spms:prepare-periods')->assertSuccessful();

        $this->assertDatabaseCount('evaluation_cycles', 8);
        $this->assertSame(0, EvaluationCycle::where('status', EvaluationCycle::STATUS_OPEN)->count());
    }

    public function test_explicit_setup_switches_the_current_period_without_replacing_existing_records(): void
    {
        $other = EvaluationCycle::create([
            'year' => 2029, 'semester' => EvaluationCycle::FIRST_SEMESTER,
            'status' => EvaluationCycle::STATUS_OPEN, 'opened_at' => now(),
        ]);
        $default = EvaluationCycle::create([
            'year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER,
            'status' => EvaluationCycle::STATUS_CLOSED, 'closed_at' => now(),
            'planning_enabled' => true, 'planning_readiness' => 'Previously verified readiness',
        ]);

        $this->artisan('spms:prepare-periods', ['--set-current' => true])->assertSuccessful();

        $this->assertDatabaseCount('evaluation_cycles', 9);
        $this->assertFalse($other->fresh()->isOpen());
        $this->assertNotNull($other->fresh()->closed_at);
        $this->assertTrue($default->fresh()->isOpen());
        $this->assertNull($default->fresh()->closed_at);
        $this->assertTrue($default->fresh()->planning_enabled);
        $this->assertSame('Previously verified readiness', $default->fresh()->planning_readiness);

        $openedAt = $default->fresh()->opened_at;
        $this->travel(1)->hours();
        $this->artisan('spms:prepare-periods', ['--set-current' => true])->assertSuccessful();
        $this->assertEquals($openedAt, $default->fresh()->opened_at);
    }

    public function test_new_forms_default_to_the_current_period_and_keep_validation_input(): void
    {
        $this->withoutVite();
        $this->seed([RoleSeeder::class, EvaluationCycleSeeder::class]);
        $director = User::factory()->create(['office_id' => Office::factory()->create()->id]);
        $director->assignRole('Campus Director');
        $cycle = EvaluationCycle::where('status', EvaluationCycle::STATUS_OPEN)->firstOrFail();

        foreach (['opcrs.create', 'target-documents.index'] as $route) {
            $response = $this->actingAs($director)->get(route($route))->assertOk();
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $selected = (new \DOMXPath($document))->query('//select[@name="evaluation_cycle_id"]/option[@selected]');
            $this->assertCount(1, $selected, $route);
            $this->assertSame((string) $cycle->id, $selected->item(0)->getAttribute('value'), $route);
        }

        // An explicitly cleared field returned by validation must not be silently reselected.
        $response = $this->withSession(['_old_input' => ['evaluation_cycle_id' => '']])
            ->get(route('opcrs.create'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $selected = (new \DOMXPath($document))->query('//select[@name="evaluation_cycle_id"]/option[@selected]');
        $this->assertCount(0, $selected);
    }
}

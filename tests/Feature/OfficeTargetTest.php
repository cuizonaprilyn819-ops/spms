<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\OfficeTarget;
use App\Models\EvaluationCycle;
use App\Models\User;
use App\Models\StrategicGoal;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_office_picker_identifies_heads_and_notification_recipients(): void
    {
        $this->seed(RoleSeeder::class);
        $director = User::factory()->create();
        $director->assignRole('Campus Director');
        $office = Office::factory()->create(['name' => 'Human Resource Management Office', 'campus' => 1, 'code' => 'HR-ONE']);
        $otherOffice = Office::factory()->create(['name' => 'Human Resource Management Office #349', 'campus' => 2, 'code' => 'HR-TWO']);
        $head = User::factory()->create(['name' => 'Assigned HR Supervisor', 'office_id' => $office->id]);
        $head->assignRole(['HR Head', 'Supervisor']);
        $otherHead = User::factory()->create(['name' => 'Other HR Head', 'office_id' => $otherOffice->id]);
        $otherHead->assignRole('HR Head');
        Office::factory()->create(['name' => 'Office without a head']);
        $target = OfficeTarget::create(['title' => 'Review target', 'success_indicator' => 'Measured', 'target_value' => 1, 'unit_of_measure' => 'report', 'weight' => 10, 'deadline' => '2026-12-31', 'created_by' => $director->id]);
        $this->actingAs($director)->get(route('office-targets.edit', $target))->assertOk()
            ->assertSee('Campus 1')->assertSee('Campus 2')->assertSee('HR-ONE')->assertSee('HR-TWO')
            ->assertSee('Will notify: Assigned HR Supervisor')
            ->assertSee('Other HR Head')->assertSee('Will notify: Other HR Head')
            ->assertSee('No notification recipient configured')
            ->assertViewHas('offices', function ($offices) use ($office, $otherOffice, $head, $otherHead) {
                return $offices->find($office->id)->targetHeads->sole()->is($head)
                    && $offices->find($otherOffice->id)->targetHeads->sole()->is($otherHead);
            });
    }

    public function test_campus_director_can_encode_a_quantifiable_target_for_an_office(): void
    {
        $this->seed(RoleSeeder::class);
        $directorOffice = Office::factory()->create(['campus' => 1]);
        $receivingOffice = Office::factory()->create(['campus' => 1]);
        $secondReceivingOffice = Office::factory()->create(['campus' => 2, 'is_active' => false]);
        $director = User::factory()->create(['office_id' => $directorOffice->id]);
        $director->assignRole('Campus Director');
        $supervisor = User::factory()->create(['office_id' => $receivingOffice->id]);
        $supervisor->assignRole('Supervisor');
        $outsideSupervisor = User::factory()->create(['office_id' => $directorOffice->id]);
        $outsideSupervisor->assignRole('Supervisor');
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::FIRST_SEMESTER, 'status' => EvaluationCycle::STATUS_OPEN]);
        $goal = StrategicGoal::create(['title' => 'Institutional Excellence', 'description' => 'Strengthen transparent and accountable campus operations.', 'created_by' => $director->id]);
        $objective = $goal->objectives()->create(['title' => 'Improve the timeliness of office reporting.']);

        $this->actingAs($director)->get(route('office-targets.create'))
            ->assertRedirect(route('target-documents.index'));

        $this->actingAs($director)->post(route('office-targets.store'), [
            'strategic_objective_id' => $objective->id,
            'title' => 'Quarterly Reports', 'success_indicator' => 'Four approved reports submitted on time.',
            'evaluation_cycle_id' => $cycle->id,
            'target_value' => 4, 'unit_of_measure' => 'reports', 'weight' => 20,
            'deadline' => '2026-12-31', 'priority' => 'high', 'office_ids' => [(string) $receivingOffice->id, (string) $secondReceivingOffice->id],
            'office_allocations' => [$receivingOffice->id => 1.5, $secondReceivingOffice->id => 2.5],
        ])->assertRedirect();

        $target = OfficeTarget::firstOrFail();
        $notice = $supervisor->notifications()->sole();
        $this->assertEquals($target->id, $notice->data['target_id']);
        $this->assertSame(route('office-targets.show', $target, false), $notice->data['url']);
        $this->assertSame(0, $outsideSupervisor->notifications()->count());
        $target->notifyAssignedSupervisors([$receivingOffice->id, $secondReceivingOffice->id]);
        $this->assertSame(1, $supervisor->notifications()->count());
        $this->assertSame('4.00', $target->target_value);
        $this->assertSame('Institutional Excellence', $target->strategic_goal);
        $this->assertSame('Improve the timeliness of office reporting.', $target->strategic_objective);
        $this->assertTrue($target->offices->contains($receivingOffice));
        $this->assertTrue($target->offices->contains($secondReceivingOffice));
        $this->assertEquals(1.5, $target->offices->firstWhere('id', $receivingOffice->id)->pivot->allocated_target_value);
        $this->assertEquals(2.5, $target->offices->firstWhere('id', $secondReceivingOffice->id)->pivot->allocated_target_value);

        $this->actingAs($director)->get(route('office-targets.show', $target))
            ->assertOk()
            ->assertSee('Institutional Excellence')
            ->assertSee('Improve the timeliness of office reporting.');

        $this->get(route('office-targets.edit', $target))
            ->assertOk()->assertViewHas('offices', fn ($offices) => $offices->contains($secondReceivingOffice));
    }

    public function test_office_head_only_sees_targets_assigned_to_their_office(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->create(); $otherOffice = Office::factory()->create();
        $head = User::factory()->create(['office_id' => $office->id]); $head->assignRole('Office Head');
        $creator = User::factory()->create();
        $assigned = OfficeTarget::create(['title' => 'Assigned Target', 'success_indicator' => 'Measured', 'target_value' => 1, 'unit_of_measure' => 'report', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $creator->id]);
        $assigned->offices()->attach($office);
        $hidden = OfficeTarget::create(['title' => 'Hidden Target', 'success_indicator' => 'Measured', 'target_value' => 1, 'unit_of_measure' => 'report', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $creator->id]);
        $hidden->offices()->attach($otherOffice);

        $this->actingAs($head)->get(route('office-targets.index'))->assertOk()->assertSee('Assigned Target')->assertDontSee('Hidden Target');
        $this->actingAs($head)->get(route('office-targets.show', $hidden))->assertForbidden();
    }

    public function test_campus_director_add_target_action_opens_office_assignment_form(): void
    {
        $this->seed(RoleSeeder::class);
        $director = User::factory()->create();
        $director->assignRole('Campus Director');

        $this->actingAs($director)->get(route('performance-targets.create'))
            ->assertRedirect(route('office-targets.create'));

        $this->actingAs($director)->get(route('office-targets.create'))
            ->assertRedirect(route('target-documents.index'));
        $this->get(route('target-documents.index'))->assertOk()->assertSee('Upload the Main Campus document');
    }

    public function test_administrator_add_target_action_also_opens_office_assignment_form(): void
    {
        $this->seed(RoleSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole('Administrator');

        $this->actingAs($administrator)->get(route('performance-targets.create'))
            ->assertRedirect(route('office-targets.create'));

        $this->actingAs($administrator)->get(route('performance-targets.create', ['linked_form' => 1]))
            ->assertRedirect(route('office-targets.create'));
    }

    public function test_creator_can_edit_target_and_change_assigned_offices(): void
    {
        $this->seed(RoleSeeder::class);
        $directorOffice = Office::factory()->create(['campus' => 1]);
        $firstOffice = Office::factory()->create(['campus' => 1]);
        $secondOffice = Office::factory()->create(['campus' => 1]);
        $director = User::factory()->create(['office_id' => $directorOffice->id]);
        $director->assignRole('Campus Director');
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::FIRST_SEMESTER, 'status' => EvaluationCycle::STATUS_OPEN]);
        $goal = StrategicGoal::create(['title' => 'Responsive Governance', 'description' => 'Provide responsive and evidence-based campus services.', 'created_by' => $director->id]);
        $objective = $goal->objectives()->create(['title' => 'Standardize periodic office reports.']);
        $target = OfficeTarget::create([
            'evaluation_cycle_id' => $cycle->id, 'title' => 'Original Target',
            'success_indicator' => 'Measured', 'target_value' => 1, 'unit_of_measure' => 'report',
            'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $director->id,
        ]);
        $target->offices()->attach($firstOffice);

        $this->actingAs($director)->get(route('office-targets.edit', $target))
            ->assertOk()->assertSee('Edit Office Target')->assertSee('Original Target')->assertSee('Allocated:');

        $this->actingAs($director)->put(route('office-targets.update', $target), [
            'strategic_objective_id' => $objective->id,
            'evaluation_cycle_id' => $cycle->id, 'title' => 'Updated Target',
            'success_indicator' => 'Two approved reports', 'target_value' => 2,
            'unit_of_measure' => 'reports', 'weight' => 20, 'deadline' => '2026-12-31',
            'priority' => 'high', 'office_ids' => [$secondOffice->id],
            'office_allocations' => [$secondOffice->id => 2],
        ])->assertRedirect(route('office-targets.show', $target));

        $this->assertDatabaseHas('office_targets', [
            'id' => $target->id,
            'strategic_goal' => 'Responsive Governance',
            'strategic_objective' => 'Standardize periodic office reports.',
            'title' => 'Updated Target',
        ]);
        $this->assertDatabaseMissing('office_target_office', ['office_target_id' => $target->id, 'office_id' => $firstOffice->id]);
        $this->assertDatabaseHas('office_target_office', ['office_target_id' => $target->id, 'office_id' => $secondOffice->id, 'allocated_target_value' => 2]);
    }

    public function test_office_head_cannot_edit_an_office_target(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->create();
        $head = User::factory()->create(['office_id' => $office->id]);
        $head->assignRole('Office Head');
        $creator = User::factory()->create();
        $target = OfficeTarget::create([
            'title' => 'Managed Target', 'success_indicator' => 'Measured', 'target_value' => 1,
            'unit_of_measure' => 'report', 'weight' => 10, 'deadline' => '2026-12-31',
            'priority' => 'medium', 'created_by' => $creator->id,
        ]);
        $target->offices()->attach($office);

        $this->actingAs($head)->get(route('office-targets.edit', $target))->assertForbidden();
    }
}

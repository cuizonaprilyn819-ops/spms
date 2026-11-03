<?php

namespace Tests\Feature;

use App\Models\{Dpcr, EvaluationCycle, Ipcr, Office, OfficeTarget, Opcr, TargetDocument, Upcr, User};
use App\Services\{TargetDocumentAi, TargetHierarchy};
use App\Support\OfficeTargetProgress;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeAssignmentHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function hierarchy(): array
    {
        $this->seed(RoleSeeder::class);
        $root = Office::factory()->create(['name' => 'Campus office', 'campus' => 6]);
        $department = Office::factory()->create(['name' => 'ICT Department', 'campus' => 6, 'parent_office_id' => $root->id]);
        $unit = Office::factory()->create(['name' => 'CISA', 'campus' => 6, 'is_active' => true, 'parent_office_id' => $department->id]);
        $director = User::factory()->create(['office_id' => $root->id])->assignRole('Campus Director');
        $departmentHead = User::factory()->create(['office_id' => $department->id])->assignRole('Supervisor');
        $head = User::factory()->create(['name' => 'CISA Head', 'office_id' => $unit->id])->assignRole('Supervisor');
        $employee = User::factory()->create(['name' => 'Emerson', 'office_id' => $unit->id])->assignRole('Employee');
        foreach ([[$root, $director], [$department, $departmentHead], [$unit, $head]] as [$office, $person]) $office->update(['head_id' => $person->id]);
        $planner = User::factory()->create()->assignRole('Planning Officer');
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER, 'status' => 'open']);
        $opcr = Opcr::create(['office_id' => $root->id, 'evaluation_cycle_id' => $cycle->id, 'prepared_by' => $director->id, 'title' => 'Campus OPCR', 'status' => 'approved']);
        $dpcr = Dpcr::create(['opcr_id' => $opcr->id, 'employee_id' => $departmentHead->id, 'prepared_by' => $departmentHead->id, 'title' => 'ICT DPCR', 'status' => 'approved']);
        $upcr = Upcr::create(['dpcr_id' => $dpcr->id, 'employee_id' => $head->id, 'prepared_by' => $head->id, 'title' => 'CISA UPCR', 'status' => 'approved']);
        return compact('root', 'department', 'unit', 'director', 'departmentHead', 'head', 'employee', 'planner', 'cycle', 'upcr', 'dpcr');
    }

    private function assign(array $context, bool $individual): OfficeTarget
    {
        extract($context);
        $document = TargetDocument::create(['created_by' => $planner->id, 'evaluation_cycle_id' => $cycle->id, 'original_name' => 'source.pdf', 'path' => 'source.pdf', 'mime_type' => 'application/pdf', 'status' => 'review']);
        $draft = $document->drafts()->create(['proposal' => ['title' => 'Maintenance', 'success_indicator' => '1 maintenance conducted within rating period', 'source_excerpt' => '1 maintenance conducted', 'cd_only' => false]]);
        $this->mock(TargetDocumentAi::class, fn ($mock) => $mock->shouldReceive('briefings')->once()->andReturn([$unit->id => ['wording' => 'Conduct maintenance', 'summary' => 'Maintenance', 'recommendation' => 'delegate', 'reason' => 'Confirmed assignment']]));
        $data = ['title' => 'Maintenance', 'success_indicator' => '1 maintenance conducted within rating period', 'target_value' => 1, 'unit_of_measure' => 'maintenance', 'weight' => 10, 'priority' => 'medium', 'office_ids' => [$unit->id], 'allocations' => [$unit->id => 1], 'confirmed' => 1, 'assignment_type' => $individual ? 'individual' : 'office'];
        if ($individual) $data['employee_allocations'] = [$employee->id => 1];
        $this->actingAs($planner)->post(route('target-drafts.assign', $draft), $data)->assertSessionHasNoErrors()->assertRedirect();
        return OfficeTarget::sole();
    }

    public function test_direct_emerson_assignment_remains_under_cisa_upcr_and_is_visible_to_reporting_heads(): void
    {
        $context = $this->hierarchy();
        extract($context);
        $target = $this->assign($context, true);
        $this->assertSame([$unit->id], $target->offices->modelKeys());
        $this->assertEquals($upcr->id, $target->offices->sole()->pivot->upcr_id);
        foreach ([$head, $departmentHead, $director] as $reviewer) {
            $this->assertEquals(1, $reviewer->notifications()->count());
            $this->actingAs($reviewer)->get(route('office-targets.show', $target))->assertOk()->assertSee('Emerson')->assertSee('CISA UPCR');
            $this->get(route('office-targets.index'))->assertOk()->assertSee('Maintenance');
            $this->assertEquals(1, app(OfficeTargetProgress::class)->forUser($reviewer)->sole()['required']);
            $this->get(route('dashboard'))->assertOk()->assertViewHas('personalDashboard', fn ($data) => $data['assignedTargets'] === 1);
        }
        $this->actingAs($employee)->patch(route('office-targets.accept', $target))->assertRedirect();
        $this->post(route('ipcrs.store'), ['office_target_id' => $target->id, 'title' => 'Emerson Maintenance IPCR', 'success_indicators' => $target->success_indicator])->assertSessionHasNoErrors();
        $ipcr = Ipcr::sole();
        $this->assertEquals($upcr->id, $ipcr->upcr_id);
        $this->actingAs($head)->get(route('upcrs.show', $upcr))->assertOk()->assertSee('Emerson')->assertSee('Maintenance');
        $this->actingAs($departmentHead)->get(route('ipcrs.show', $ipcr))->assertOk();
        $outside = User::factory()->create(['office_id' => Office::factory()->create(['campus' => 6])->id])->assignRole('Supervisor');
        $this->actingAs($outside)->get(route('office-targets.show', $target))->assertForbidden();
        $this->get(route('upcrs.show', $upcr))->assertForbidden();
        $this->get(route('ipcrs.show', $ipcr))->assertForbidden();
    }

    public function test_cisa_only_assignment_is_visible_to_head_for_later_delegation(): void
    {
        $context = $this->hierarchy();
        extract($context);
        $target = $this->assign($context, false);
        $this->assertCount(0, $target->assignedEmployees);
        $this->actingAs($head)->get(route('office-targets.show', $target))->assertOk()->assertSee('Its head can delegate');
        $this->post(route('office-targets.assign-employees', $target), ['employee_ids' => [$employee->id], 'allocations' => [$employee->id => 1]])->assertSessionHasNoErrors();
        $this->assertEquals($employee->id, $target->assignedEmployees()->sole()->id);
        $this->assertEquals($upcr->id, $target->offices()->sole()->pivot->upcr_id);
    }

    public function test_parent_director_is_notified_without_head_mapping_and_shared_ancestors_receive_one_notification(): void
    {
        $context = $this->hierarchy();
        extract($context);
        $root->update(['head_id' => null]);
        $unrelated = User::factory()->create(['office_id' => Office::factory()->create(['campus' => 6])->id])->assignRole('Campus Director');
        $target = $this->assign($context, false);
        $notification = $director->notifications()->sole()->data;
        $this->assertSame('New target assigned to an office under your supervision', $notification['title']);
        $this->assertStringContainsString('CISA', $notification['message']);
        $this->assertStringNotContainsString('to your office.', $notification['message']);
        $this->assertSame([$unit->id], $notification['assigned_office_ids']);
        $this->assertSame(0, $unrelated->notifications()->count());
        $sibling = Office::factory()->create(['name' => 'Other unit', 'campus' => 6, 'parent_office_id' => $department->id]);
        $target->offices()->attach($sibling->id, ['allocated_target_value' => 1]);
        $target->notifyAssignedSupervisors([$unit->id, $sibling->id]);
        $this->assertSame(1, $director->notifications()->count());
        $this->assertSame(1, $departmentHead->notifications()->count());
        $target->notifyAssignedSupervisors([$unit->id, $sibling->id], true);
        $this->assertSame(2, $director->notifications()->count());
        $updated = $director->notifications()->get()->first(fn ($n) => $n->data['title'] === 'Office target updated')->data;
        $this->assertEqualsCanonicalizing([$unit->id, $sibling->id], $updated['assigned_office_ids']);
        $this->assertStringContainsString('Other unit', $updated['message']);
        $this->assertSame(0, $unrelated->notifications()->count());
    }

    public function test_missing_upcr_keeps_assignment_visible_without_creating_or_approving_reports(): void
    {
        $context = $this->hierarchy();
        extract($context);
        $upcr->delete();
        $target = $this->assign($context, true);
        $this->assertNull($target->offices()->sole()->pivot->upcr_id);
        $this->assertDatabaseCount('upcrs', 0);
        $this->assertCount(1, $target->assignedEmployees);
        $this->actingAs($head)->get(route('office-targets.show', $target))->assertOk()->assertSee('Pending office UPCR link')->assertSee('Emerson');
    }

    public function test_head_cannot_link_a_different_office_or_period_upcr(): void
    {
        $context = $this->hierarchy();
        extract($context);
        $target = $this->assign($context, false);
        $other = Upcr::factoryLike()->create();
        $this->actingAs($head)->post(route('office-targets.link-upcr', $target), ['office_id' => $unit->id, 'upcr_id' => $other->id])->assertSessionHasErrors('upcr_id');
        $this->assertEquals($upcr->id, $target->offices()->sole()->pivot->upcr_id);
    }

    public function test_two_individual_targets_can_share_the_same_office_upcr(): void
    {
        $context = $this->hierarchy();
        extract($context);
        $first = $this->assign($context, true);
        $second = OfficeTarget::create(['title' => 'Second maintenance target', 'success_indicator' => 'One maintenance', 'target_value' => 1, 'weight' => 10, 'unit_of_measure' => 'maintenance', 'priority' => 'medium', 'deadline' => '2026-12-31', 'evaluation_cycle_id' => $cycle->id, 'created_by' => $planner->id]);
        $second->offices()->attach($unit->id, ['allocated_target_value' => 1, 'upcr_id' => $upcr->id]);
        $second->assignedEmployees()->attach($employee->id, ['allocated_target_value' => 1, 'assigned_by' => $planner->id, 'accepted_at' => now()]);
        $first->assignedEmployees()->updateExistingPivot($employee->id, ['accepted_at' => now()]);
        $this->actingAs($employee);
        foreach ([$first, $second] as $target) {
            $this->post(route('ipcrs.store'), ['office_target_id' => $target->id, 'title' => $target->title, 'success_indicators' => $target->success_indicator])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseCount('ipcrs', 2);
        $this->assertSame([$upcr->id], Ipcr::pluck('upcr_id')->unique()->values()->all());
    }
}

<?php

namespace Tests\Feature;

use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\Opcr;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpcrManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_campus_director_prepares_opcr_and_ipcr_and_cannot_prepare_dpcr_or_upcr(): void
    {
        $this->seed(RoleSeeder::class);
        $office = Office::factory()->create(['campus' => 6, 'is_active' => true]);
        $user = User::factory()->create(['office_id' => $office->id])->assignRole('Campus Director');
        $cycle = EvaluationCycle::create(['year' => 2026, 'semester' => EvaluationCycle::SECOND_SEMESTER, 'status' => 'open']);
        $this->actingAs($user)->get(route('opcrs.create'))->assertOk();
        $this->get(route('reports.index'))->assertOk()->assertSee('Prepare OPCR')->assertSee('Prepare IPCR')->assertDontSee('Prepare DPCR')->assertDontSee('Prepare UPCR');
        $this->assertSame(['ipcr', 'opcr'], app(\App\Services\PcrCompliance::class)->requiredReports($user));
        foreach (['dpcrs','upcrs'] as $resource) {
            $this->get(route($resource.'.create'))->assertForbidden();
            $this->post(route($resource.'.store'), [])->assertForbidden();
        }
        $this->post(route('opcrs.store'), ['office_id' => $office->id, 'evaluation_cycle_id' => $cycle->id, 'title' => 'Campus OPCR'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($user->id, Opcr::sole()->prepared_by);
        $other = Office::factory()->create(['campus' => 6, 'is_active' => true]);
        $this->post(route('opcrs.store'), ['office_id' => $other->id, 'evaluation_cycle_id' => $cycle->id, 'title' => 'Another office'])->assertForbidden();
    }

    public function test_users_can_view_opcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('HR');
        $opcr = Opcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('opcrs.show', $opcr));

        $response->assertOk();
        $response->assertSee($opcr->title);
    }

    public function test_campus_directors_can_approve_opcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $approver = User::factory()->create();
        $approver->assignRole('Campus Director');
        $opcr = Opcr::factoryLike()->create(['status' => Opcr::STATUS_SUBMITTED]);

        $response = $this
            ->actingAs($approver)
            ->from(route('opcrs.show', $opcr))
            ->patch(route('report-workflow.approve', ['type' => 'opcr', 'report' => $opcr->id]));

        $response->assertRedirect(route('opcrs.show', $opcr));

        $opcr->refresh();

        $this->assertSame(Opcr::STATUS_APPROVED, $opcr->status);
        $this->assertSame($approver->id, $opcr->approved_by);
        $this->assertNotNull($opcr->approved_at);
    }

    public function test_employees_cannot_approve_opcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');
        $opcr = Opcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('report-workflow.approve', ['type' => 'opcr', 'report' => $opcr->id]));

        $response->assertForbidden();
        $this->assertSame(Opcr::STATUS_DRAFT, $opcr->fresh()->status);
    }

    public function test_opcr_is_unique_per_office_and_period(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Campus Director');
        $office = Office::factory()->create(['campus' => 6, 'is_active' => true]);
        $user->update(['office_id' => $office->id]);
        $cycle = EvaluationCycle::create([
            'year' => 2025,
            'semester' => EvaluationCycle::FIRST_SEMESTER,
            'status' => EvaluationCycle::STATUS_OPEN,
        ]);

        Opcr::create([
            'evaluation_cycle_id' => $cycle->id,
            'office_id' => $office->id,
            'title' => 'Existing OPCR',
            'status' => Opcr::STATUS_DRAFT,
            'prepared_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('opcrs.store'), [
                'evaluation_cycle_id' => $cycle->id,
                'office_id' => $office->id,
                'title' => 'Duplicate OPCR',
            ]);

        $response->assertSessionHasErrors('office_id');
        $this->assertDatabaseCount('opcrs', 1);
    }

    public function test_office_heads_only_see_targets_assigned_to_their_office(): void
    {
        $this->seed(RoleSeeder::class);

        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        $head = User::factory()->create(['office_id' => $office->id]);
        $head->assignRole('Supervisor');
        $assigned = Opcr::factoryLike()->create(['office' => $office, 'title' => 'Assigned Office OPCR']);
        $notAssigned = Opcr::factoryLike()->create([
            'office' => $otherOffice,
            'evaluation_cycle' => $assigned->evaluationCycle,
            'title' => 'Hidden Office OPCR',
        ]);

        $this->actingAs($head)->get(route('opcrs.index'))
            ->assertOk()
            ->assertSee($assigned->title)
            ->assertDontSee($notAssigned->title);

        $this->actingAs($head)->get(route('opcrs.show', $notAssigned))->assertForbidden();
    }
}

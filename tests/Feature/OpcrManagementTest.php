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

    public function test_office_heads_can_create_opcrs_for_open_cycles(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Office Head');
        $office = Office::factory()->create();
        $cycle = EvaluationCycle::create([
            'year' => 2025,
            'semester' => EvaluationCycle::FIRST_SEMESTER,
            'status' => EvaluationCycle::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('opcrs.store'), [
                'evaluation_cycle_id' => $cycle->id,
                'office_id' => $office->id,
                'title' => '2025 Planning OPCR',
                'success_indicators' => 'Approved plans submitted on time.',
                'targets' => 'Submit 100% of required reports.',
            ]);

        $opcr = Opcr::firstOrFail();

        $response->assertRedirect(route('opcrs.show', $opcr));

        $this->assertDatabaseHas('opcrs', [
            'office_id' => $office->id,
            'evaluation_cycle_id' => $cycle->id,
            'status' => Opcr::STATUS_DRAFT,
            'prepared_by' => $user->id,
        ]);
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
        $opcr = Opcr::factoryLike()->create();

        $response = $this
            ->actingAs($approver)
            ->patch(route('opcrs.approve', $opcr));

        $response->assertRedirect(route('opcrs.show', $opcr));

        $opcr->refresh();

        $this->assertSame(Opcr::STATUS_APPROVED, $opcr->status);
        $this->assertSame($approver->id, $opcr->approved_by);
        $this->assertNotNull($opcr->approved_at);
    }

    public function test_office_heads_cannot_approve_opcrs(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Office Head');
        $opcr = Opcr::factoryLike()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('opcrs.approve', $opcr));

        $response->assertForbidden();
        $this->assertSame(Opcr::STATUS_DRAFT, $opcr->fresh()->status);
    }

    public function test_opcr_is_unique_per_office_and_cycle(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Office Head');
        $office = Office::factory()->create();
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
    }

    public function test_office_heads_only_see_targets_assigned_to_their_office(): void
    {
        $this->seed(RoleSeeder::class);

        $office = Office::factory()->create();
        $otherOffice = Office::factory()->create();
        $head = User::factory()->create(['office_id' => $office->id]);
        $head->assignRole('Office Head');
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

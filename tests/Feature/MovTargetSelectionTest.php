<?php

namespace Tests\Feature;

use App\Models\{Office, OfficeTarget, TargetMov, User};
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovTargetSelectionTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\PreparesMovQuestions;

    public function test_upload_mov_selects_only_accepted_personal_assignments_and_stores_against_that_target(): void
    {
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        $user = User::factory()->create(['office_id' => Office::factory()->create()->id])->assignRole('Employee');
        $mine = $this->target('My maintenance', $user);
        $pending = $this->target('Pending acceptance', $user);
        $mine->assignedEmployees()->attach($user, ['accepted_at' => now(), 'allocated_target_value' => 1, 'assigned_by' => $user->id]);
        $pending->assignedEmployees()->attach($user, ['allocated_target_value' => 1, 'assigned_by' => $user->id]);
        $this->actingAs($user)->get(route('movs.create'))->assertOk()->assertSee('Select target for this MOV')->assertSee('My maintenance')->assertDontSee('Pending acceptance')->assertDontSee('General evidence');
        $this->post(route('movs.store'), ['target_ref' => 'office:'.$pending->id, 'title' => 'Evidence', 'file' => UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4 fixture')])->assertForbidden();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $token = $this->prepareMovQuestions('office:'.$mine->id);
        $this->mock(\App\Services\EvidenceAi::class, fn ($mock) => $mock->shouldReceive('analyze')->once()->withArgs(fn ($mov) => $mov instanceof TargetMov && $mov->office_target_id === $mine->id));
        $this->post(route('movs.store'), ['questionnaire_token' => $token, 'completed_value' => 1, 'answers' => ['q1' => 1, 'q2' => 'Maintenance completed.'], 'target_ref' => 'office:'.$mine->id, 'title' => 'Maintenance evidence', 'description' => 'Completed maintenance', 'file' => UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4 fixture')])->assertSessionHasNoErrors()->assertRedirect(route('office-targets.show', $mine).'#target-movs');
        $mov = TargetMov::sole();
        $this->assertEquals($mine->id, $mov->office_target_id);
        $this->assertSame($user->id, $mov->user_id);
        Storage::disk('local')->assertExists($mov->path);
        $this->assertDatabaseCount('movs', 0);
    }

    public function test_missing_target_and_another_persons_report_target_cannot_receive_uploads(): void
    {
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
        $user = User::factory()->create()->assignRole('Employee');
        $other = \App\Models\PerformanceTarget::factoryLike()->create();
        $this->actingAs($user)->get(route('movs.create'))->assertOk()->assertViewHas('targets', fn ($targets) => $targets->isEmpty());
        $this->post(route('movs.store'), ['title' => 'Evidence', 'file' => UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4 fixture')])->assertSessionHasErrors('performance_target_id');
        $this->post(route('movs.store'), ['target_ref' => 'report:'.$other->id, 'title' => 'Evidence', 'file' => UploadedFile::fake()->createWithContent('proof.pdf', '%PDF-1.4 fixture')])->assertForbidden();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private function target(string $title, User $user): OfficeTarget
    {
        $target = OfficeTarget::create(['title' => $title, 'success_indicator' => 'One maintenance', 'target_value' => 1, 'unit_of_measure' => 'maintenance', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $user->id]);
        $target->offices()->attach($user->office_id, ['allocated_target_value' => 1]);
        return $target;
    }
}

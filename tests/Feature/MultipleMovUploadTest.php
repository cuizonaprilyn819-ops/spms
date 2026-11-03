<?php

namespace Tests\Feature;

use App\Models\{Ipcr, Mov, Office, OfficeTarget, PerformanceTarget, TargetMov, User};
use App\Services\EvidenceAi;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultipleMovUploadTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\PreparesMovQuestions;

    private function owner(): User
    {
        config(['spms.ai.enabled' => false]);
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['office_id' => Office::factory()->create()->id])->assignRole('Employee');
        $this->actingAs($user);
        return $user;
    }

    public function test_multiple_images_are_linked_to_one_report_target_and_each_is_analyzed(): void
    {
        $user = $this->owner();
        $report = Ipcr::factoryLike()->create(['employee' => $user, 'prepared_by_user' => $user]);
        $target = PerformanceTarget::factoryLike()->create(['targetable' => $report]);
        $token = $this->prepareMovQuestions('report:'.$target->id);
        $this->mock(EvidenceAi::class, fn ($mock) => $mock->shouldReceive('analyze')->once()->withArgs(fn ($mov) => $mov instanceof Mov && $mov->performance_target_id === $target->id));
        $this->get(route('movs.create', ['questionnaire_token' => $token]))->assertOk()->assertSee('name="files[]"', false)->assertSee(' multiple ', false);
        $this->post(route('movs.store'), ['questionnaire_token' => $token, 'answers' => ['q1' => 1, 'q2' => 'Event delivered.'], 'target_ref' => 'report:'.$target->id, 'title' => 'Event photos', 'files' => [UploadedFile::fake()->create('photo1.jpg', 12, 'image/jpeg'), UploadedFile::fake()->create('photo2.png', 12, 'image/png')]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(['photo1.jpg', 'photo2.png'], Mov::orderBy('id')->pluck('original_name')->all());
        foreach (Mov::all() as $mov) {
            $this->assertEquals($target->id, $mov->performance_target_id);
            $this->assertSame($user->id, $mov->uploaded_by);
            Storage::disk('public')->assertExists($mov->file_path);
        }
    }

    public function test_assigned_target_accepts_batches_and_rejects_bad_batches_without_partial_saves(): void
    {
        $user = $this->owner();
        $target = OfficeTarget::create(['title' => 'Maintenance', 'success_indicator' => 'One maintenance', 'target_value' => 1, 'unit_of_measure' => 'maintenance', 'weight' => 10, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $user->id]);
        $target->offices()->attach($user->office_id, ['allocated_target_value' => 1]);
        $target->assignedEmployees()->attach($user, ['accepted_at' => now(), 'allocated_target_value' => 1, 'assigned_by' => $user->id]);
        $base = ['target_ref' => 'office:'.$target->id, 'title' => 'Maintenance photos'];
        $tooMany = array_map(fn ($i) => UploadedFile::fake()->create('proof'.$i.'.pdf', 1, 'application/pdf'), range(1, 21));
        $this->post(route('movs.store'), [...$base, 'files' => $tooMany])->assertSessionHasErrors('files');
        $this->post(route('movs.store'), [...$base, 'files' => [UploadedFile::fake()->create('good.pdf', 1, 'application/pdf'), UploadedFile::fake()->create('bad.exe', 1, 'application/octet-stream')]])->assertSessionHasErrors('files.1');
        $tooLarge = array_map(fn ($i) => UploadedFile::fake()->create('large'.$i.'.pdf', 9 * 1024, 'application/pdf'), range(1, 4));
        $this->post(route('movs.store'), [...$base, 'files' => $tooLarge])->assertSessionHasErrors('files');
        $this->assertDatabaseCount('target_movs', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->post(route('movs.store'), [...$base, 'files' => array_map(fn ($i) => UploadedFile::fake()->create('photo'.$i.'.jpg', 12, 'image/jpeg'), range(1, 6))])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('target_movs', 6);
        foreach (TargetMov::all() as $mov) {
            $this->assertEquals($target->id, $mov->office_target_id);
            Storage::disk('local')->assertExists($mov->path);
        }
    }
}

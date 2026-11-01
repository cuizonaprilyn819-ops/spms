<?php

namespace Tests\Feature;

use App\Models\Mov;
use App\Models\PerformanceTarget;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerformanceTargetEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_evidence_is_linked_to_the_selected_target_and_visible_in_the_workflow(): void
    {
        $storage = Storage::fake('public');
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Employee');
        $target = PerformanceTarget::factoryLike()->create();
        $this->actingAs($user)->get(route('movs.create', ['performance_target_id' => $target->id]))
            ->assertOk()->assertSee($target->target);
        $this->post(route('movs.store'), [
            'performance_target_id' => $target->id,
            'title' => 'Signed target evidence',
            'file' => UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $mov = $target->movs()->sole();
        $this->assertEquals($target->id, $mov->performanceTarget->id);
        $storage->assertExists($mov->file_path);
        $this->get(route('movs.show', $mov))->assertOk()->assertSee($target->target);
        $this->get(route('movs.index', ['performance_target_id' => $target->id]))
            ->assertOk()->assertSee('Signed target evidence');
        $other = PerformanceTarget::factoryLike()->create(['targetable' => $target->targetable]);
        $this->get(route('movs.index', ['performance_target_id' => $other->id]))
            ->assertOk()->assertDontSee('Signed target evidence');
        $this->actingAs(User::factory()->create()->assignRole('Employee'))
            ->get(route('movs.index'))->assertOk()->assertDontSee('Signed target evidence');
    }

    public function test_unknown_target_is_rejected_before_storing_a_file(): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create()->assignRole('Employee');
        $this->actingAs($user)->post(route('movs.store'), [
            'performance_target_id' => 999999,
            'title' => 'Invalid target',
            'file' => UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('performance_target_id');
        $this->assertSame(0, Mov::count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }
}

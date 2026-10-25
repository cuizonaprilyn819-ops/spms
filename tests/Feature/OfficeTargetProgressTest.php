<?php
namespace Tests\Feature;

use App\Models\{Office, OfficeTarget, User};
use App\Support\OfficeTargetProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeTargetProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_reported_quantity_tracks_allocation_without_double_counting_uploads(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        Storage::fake('local');
        $office = Office::factory()->create();
        $head = User::factory()->create(['office_id' => $office->id]);
        $head->assignRole('HR Head');
        $employee = User::factory()->create(['office_id' => $office->id]);
        $employee->assignRole('Employee');
        $outsider = User::factory()->create();
        $outsider->assignRole('Employee');
        $target = OfficeTarget::create(['title' => 'Two sessions', 'success_indicator' => 'Two completed sessions',
            'target_value' => 2, 'unit_of_measure' => 'sessions', 'weight' => 20,
            'deadline' => now()->subDay()->toDateString(), 'priority' => 'high', 'created_by' => $head->id]);
        $target->offices()->attach($office->id, ['allocated_target_value' => 2]);
        $target->assignedEmployees()->attach($employee->id, ['assigned_by' => $head->id, 'allocated_target_value' => 2, 'accepted_at' => now()]);
        $url = route('target-movs.store', $target);
        $this->actingAs($employee);
        foreach ([1, 1] as $quantity) {
            $this->post($url, ['accomplishment' => 'First session delivered', 'completed_value' => $quantity,
                'files' => [UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf')]])->assertSessionHasNoErrors()->assertRedirect();
        }
        $row = app(OfficeTargetProgress::class)->forUser($head)->sole();
        $this->assertEquals(1, $row['completed']);
        $this->assertEquals(50, $row['percent']);
        $this->assertTrue($row['overdue']);
        $this->assertEmpty(app(OfficeTargetProgress::class)->forUser($outsider));
        $this->post($url, ['accomplishment' => 'Invalid', 'completed_value' => 3,
            'files' => [UploadedFile::fake()->create('invalid.pdf', 10, 'application/pdf')]])->assertSessionHasErrors('completed_value');
        $this->assertSame(2, \App\Models\TargetMov::count());
        $this->actingAs($head)->get(route('dashboard'))->assertOk()->assertSee('50%')
            ->assertViewHas('personalDashboard', fn ($data) => $data['progress']['In progress'] === 1 && $data['progress']['Completed'] === 0);
        $this->actingAs($employee)->patch(route('target-movs.complete', $target))->assertRedirect();
        $this->assertEquals(100, app(OfficeTargetProgress::class)->forUser($head)->sole()['percent']);
    }

    public function test_unallocated_remainder_is_not_marked_complete(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $office = Office::factory()->create();
        $head = User::factory()->create(['office_id' => $office->id]);
        $head->assignRole('HR Head');
        $target = OfficeTarget::create(['title' => 'Two sessions', 'success_indicator' => 'Two sessions',
            'target_value' => 2, 'unit_of_measure' => 'sessions', 'weight' => 20,
            'deadline' => '2026-12-31', 'priority' => 'high', 'created_by' => $head->id]);
        $target->offices()->attach($office->id, ['allocated_target_value' => 2]);
        $target->assignedEmployees()->attach($head->id, ['assigned_by' => $head->id, 'allocated_target_value' => 1, 'completed_at' => now()]);
        $this->assertEquals(50, app(OfficeTargetProgress::class)->forUser($head)->sole()['percent']);
    }
}

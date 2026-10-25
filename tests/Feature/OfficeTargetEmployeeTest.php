<?php
namespace Tests\Feature;

use App\Models\{Office, OfficeTarget, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeTargetEmployeeTest extends TestCase
{
    use RefreshDatabase;
    public function test_only_assigned_office_supervisor_can_assign_employees(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $office = Office::factory()->create();
        $supervisor = User::factory()->create(['office_id' => $office->id]);
        $supervisor->assignRole('Supervisor');
        $employee = User::factory()->create(['office_id' => $office->id]);
        $employee->assignRole('Employee');
        $outsider = User::factory()->create(['office_id' => Office::factory()->create()->id]);
        $outsider->assignRole('Employee');
        $director = User::factory()->create();
        $director->assignRole('Campus Director');
        $target = OfficeTarget::create(['title' => 'Shared work', 'success_indicator' => 'One output', 'target_value' => 1, 'unit_of_measure' => 'report', 'weight' => 20, 'deadline' => '2026-12-31', 'priority' => 'medium', 'created_by' => $director->id]);
        $target->offices()->attach($office->id);
        $url = route('office-targets.assign-employees', $target);
        $this->actingAs($director)->post($url, ['employee_ids' => [$employee->id]])->assertForbidden();
        $this->actingAs($supervisor)->post($url, ['employee_ids' => [$outsider->id]])->assertForbidden();
        $this->get(route('office-targets.show', $target))->assertOk()->assertSee('Assign to Employees')->assertSee($employee->name)->assertDontSee($outsider->name);
        $this->post($url, ['employee_ids' => [(string) $employee->id]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($target->assignedEmployees()->whereKey($employee->id)->exists());
        $this->actingAs($employee)->get(route('performance-targets.index'))->assertOk()->assertSee($target->title)->assertSee(route('office-targets.show', $target));
        $this->actingAs($outsider)->get(route('performance-targets.index'))->assertOk()->assertDontSee($target->title);
        $this->actingAs($supervisor);
        $this->assertSame(1, $employee->notifications()->count());
        $this->post($url, ['employee_ids' => [$employee->id]])->assertRedirect();
        $this->assertSame(1, $employee->notifications()->count());
        $this->actingAs($employee)->get(route('office-targets.show', $target))->assertOk()->assertDontSee('Assign to Employees');
        $this->patch(route('target-movs.complete', $target))->assertStatus(422);
        \Illuminate\Support\Facades\Storage::fake('local');
        $upload = route('target-movs.store', $target);
        $this->post($upload, ['accomplishment' => 'Completed the assigned work.',
            'files' => [\Illuminate\Http\UploadedFile::fake()->create('evidence.pdf', 20, 'application/pdf')]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $mov = \App\Models\TargetMov::sole();
        $this->assertEquals($target->id, $mov->office_target_id);
        foreach ([$employee, $supervisor, $director] as $viewer) {
            $this->actingAs($viewer)->get(route('movs.index'))->assertOk()
                ->assertSee('evidence.pdf')->assertSee($target->title)
                ->assertDontSee('Upload MOV');
        }
        $this->actingAs($outsider)->get(route('movs.index'))->assertOk()->assertDontSee('evidence.pdf');
        $this->actingAs($employee);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($mov->path);
        $this->assertSame(1, $supervisor->notifications()->where('type', \App\Notifications\TargetMovSubmittedNotification::class)->count());
        $this->get(route('target-movs.download', $mov))->assertDownload('evidence.pdf');
        \Illuminate\Support\Facades\Storage::disk('local')->put($mov->path, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
        $this->get(route('target-movs.preview', $mov))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->post($upload, ['accomplishment' => 'Word document', 'files' => [\Illuminate\Http\UploadedFile::fake()->create('document.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')]])->assertSessionHasErrors('files.0');
        $this->post($upload, ['accomplishment' => 'Bad file', 'files' => [\Illuminate\Http\UploadedFile::fake()->create('bad.txt', 10, 'text/plain')]])->assertSessionHasErrors('files.0');
        $this->post($upload, ['accomplishment' => 'Large file', 'files' => [\Illuminate\Http\UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')]])->assertSessionHasErrors('files.0');
        $this->actingAs($supervisor)->get(route('office-targets.show', $target))->assertOk()->assertSee('Completed the assigned work.');
        $this->get(route('target-movs.download', $mov))->assertDownload('evidence.pdf');
        $this->post($upload)->assertForbidden();
        $this->actingAs($outsider)->post($upload)->assertForbidden();
        $this->get(route('target-movs.download', $mov))->assertForbidden();
        $this->get(route('target-movs.preview', $mov))->assertForbidden();
        $this->assertDatabaseCount('target_movs', 1);
        $this->actingAs($employee);
        $completionUrl = route('target-movs.complete', $target);
        $this->patch($completionUrl)->assertRedirect();
        $this->get(route('office-targets.show', $target))->assertOk()->assertSee('Prepare My IPCR Report');
        $this->get(route('ipcrs.create', ['office_target_id' => $target->id]))->assertOk()->assertSee($target->success_indicator);
        $this->get(route('ipcrs.create', ['office_target_id' => $target->id]))->assertDontSee('Linked UPCR')->assertDontSee('No approved UPCR');
        $ipcrData = ['office_target_id' => $target->id, 'title' => 'Completed Target IPCR', 'success_indicators' => $target->success_indicator];
        $this->post(route('ipcrs.store'), $ipcrData)->assertSessionHasNoErrors()->assertRedirect();
        $ipcr = \App\Models\Ipcr::where('office_target_id', $target->id)->sole();
        $this->assertNull($ipcr->upcr_id);
        $this->post(route('ipcrs.store'), $ipcrData)->assertRedirect(route('ipcrs.show', $ipcr));
        $this->assertDatabaseCount('ipcrs', 1);
        $this->get(route('ipcrs.show', $ipcr))->assertOk();
        $this->get(route('reports.show', 'ipcr'))->assertOk()->assertSee('Completed the assigned work.');
        $this->get(route('ipcrs.show', $ipcr))->assertSee('View Target MOV')->assertDontSee('IPCR Files');
        $this->get(route('reports.pdf', 'ipcr'))->assertForbidden();
        $this->get(route('reports.excel', 'ipcr'))->assertForbidden();
        $ipcr->update(['status' => \App\Models\Ipcr::STATUS_SUBMITTED]);
        $approvalUrl = route('report-workflow.approve', ['ipcr', $ipcr]);
        $this->actingAs($supervisor)->patch($approvalUrl)->assertSessionHasErrors(['quality_rating', 'efficiency_rating', 'timeliness_rating']);
        $this->assertSame(\App\Models\Ipcr::STATUS_SUBMITTED, $ipcr->fresh()->status);
        $this->patch($approvalUrl, ['quality_rating' => 6, 'efficiency_rating' => 4, 'timeliness_rating' => 3])->assertSessionHasErrors('quality_rating');
        $this->patch($approvalUrl, ['quality_rating' => 5, 'efficiency_rating' => 4, 'timeliness_rating' => 3, 'average_rating' => 5, 'rating_remarks' => 'Verified output'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(4, $ipcr->fresh()->average_rating);
        $this->actingAs($employee)->get(route('reports.show', 'ipcr'))->assertOk()->assertSee('Verified output')
            ->assertViewHas('forms', fn ($forms) => array_slice($forms[0]['rows'][0], 3, 4) === ['5.00', '4.00', '3.00', '4.00']);
        $this->get(route('reports.pdf', 'ipcr'))->assertOk();
        $this->get(route('reports.excel', 'ipcr'))->assertOk();
        $this->actingAs($outsider)->post(route('ipcrs.store'), $ipcrData)->assertForbidden();
        $this->actingAs($employee);
        $this->patch($completionUrl)->assertRedirect();
        foreach ([$supervisor, $director] as $recipient) {
            $this->assertSame(1, $recipient->notifications()->where('type', \App\Notifications\TargetCompletedNotification::class)->count());
        }
        $this->actingAs($supervisor)->patch($completionUrl)->assertForbidden();
        $this->actingAs($employee);
        $files = [];
        for ($i = 0; $i < 6; $i++) {
            $files[] = \Illuminate\Http\UploadedFile::fake()->create('proof'.$i.'.pdf', 10, 'application/pdf');
        }
        $this->post($upload, ['accomplishment' => 'Too many files', 'files' => $files])->assertSessionHasErrors('files');
        $this->assertDatabaseCount('target_movs', 1);
        $this->post($upload, ['accomplishment' => 'Five supporting files', 'files' => array_slice($files, 0, 5)])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('target_movs', 6);
        $this->assertSame(2, $supervisor->notifications()->where('type', \App\Notifications\TargetMovSubmittedNotification::class)->count());
        foreach ([$supervisor, $director] as $viewer) {
            $this->actingAs($viewer)->get(route('dashboard'))->assertOk()
                ->assertSee($employee->name)->assertDontSee($outsider->name)
                ->assertSee('MOV submitted: 1 / 1 targets')
                ->assertViewHas('personalDashboard', function ($dashboard) {
                    $row = $dashboard['employeeProgress']->first();
                    return $dashboard['assignedTargets'] === 1 && $row['targets'] === 1
                        && $row['submitted'] === 1 && $row['completed'] === 1 && $dashboard['accomplishedTargets'] === 1;
                });
        }
        $this->actingAs($employee);
        $this->post($url, ['employee_ids' => [$outsider->id]])->assertForbidden();
        $this->actingAs($outsider)->get(route('office-targets.show', $target))->assertForbidden();
        $outsider->syncRoles('Supervisor');
        $this->post($url, ['employee_ids' => [$employee->id]])->assertForbidden();
    }
}

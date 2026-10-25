<?php
namespace Tests\Feature;

use App\Models\{Office, User, Ipcr};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpcrApprovalNoticeTest extends TestCase
{
    use RefreshDatabase;
    public function test_employee_receives_notice_when_supervisor_prepared_the_report(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $office = Office::factory()->create();
        $employee = User::factory()->create(['office_id' => $office->id]);
        $employee->assignRole('Employee');
        $supervisor = User::factory()->create(['office_id' => $office->id]);
        $supervisor->assignRole('Supervisor');
        $ipcr = Ipcr::factoryLike()->create(['employee' => $employee, 'prepared_by_user' => $supervisor]);
        $ipcr->update(['status' => Ipcr::STATUS_SUBMITTED]);
        $url = route('report-workflow.approve', ['ipcr', $ipcr]);
        $this->actingAs($supervisor)->patch($url)->assertRedirect();
        $notice = $employee->notifications()->sole();
        $this->assertStringContainsString('PDF or Excel', $notice->data['message']);
        $this->assertSame(route('reports.index', [], false), $notice->data['url']);
        $this->patch($url)->assertStatus(422);
        $this->assertSame(1, $employee->notifications()->count());
    }
}

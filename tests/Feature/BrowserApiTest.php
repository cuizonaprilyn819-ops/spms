<?php

namespace Tests\Feature;

use App\Models\Ipcr;
use App\Models\Mov;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrowserApiTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = 'Administrator'): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_guests_receive_json_unauthorized_even_without_accept_header(): void
    {
        $this->get('/api/offices')->assertUnauthorized()->assertJsonStructure(['message']);
        $this->post('/api/offices', ['name' => 'Forbidden'])->assertUnauthorized();
        $this->assertDatabaseCount('offices', 0);
    }

    public function test_reads_return_authorized_page_content_in_json(): void
    {
        $this->signIn();
        Office::create(['name' => 'API Test Office']);
        $response = $this->getJson('/api/offices')->assertOk()->assertJsonStructure(['html']);
        $this->assertStringContainsString('API Test Office', $response->json('html'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_office_create_update_delete_use_existing_validation_and_persist(): void
    {
        $this->signIn();
        $this->postJson('/api/offices', ['name' => 'API Office'])->assertOk()
            ->assertJsonPath('redirect', route('offices.index'));
        $office = Office::where('name', 'API Office')->firstOrFail();
        $this->patchJson('/api/offices/'.$office->id, ['name' => 'Updated API Office'])->assertOk();
        $this->assertDatabaseHas('offices', ['id' => $office->id, 'name' => 'Updated API Office']);
        $this->deleteJson('/api/offices/'.$office->id)->assertOk();
        $this->assertDatabaseMissing('offices', ['id' => $office->id]);
    }

    public function test_validation_returns_json_and_does_not_write(): void
    {
        $this->signIn();
        $this->post('/api/offices', [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('offices', 0);
    }

    public function test_employee_cannot_use_admin_api(): void
    {
        $this->signIn('Employee');
        $this->getJson('/api/offices')->assertForbidden();
        $this->postJson('/api/offices', ['name' => 'Forbidden'])->assertForbidden();
        $this->assertDatabaseCount('offices', 0);
    }

    public function test_mov_upload_and_download_work_through_api(): void
    {
        Storage::fake('public');
        $user = $this->signIn('Employee');
        $this->post('/api/movs', [
            'title' => 'API Evidence',
            'file' => UploadedFile::fake()->create('evidence.pdf', 50, 'application/pdf'),
        ])->assertOk()->assertJsonStructure(['redirect']);
        $mov = Mov::firstOrFail();
        $this->assertSame($user->id, $mov->uploaded_by);
        Storage::disk('public')->assertExists($mov->file_path);
        $this->getJson('/api/movs/'.$mov->id)->assertOk()->assertJsonStructure(['html']);
        $this->get('/api/movs/'.$mov->id.'/download')->assertDownload('evidence.pdf');
    }

    public function test_ipcr_listing_does_not_expose_another_employees_records(): void
    {
        $user = $this->signIn('Employee');
        $mine = Ipcr::factoryLike()->create(['employee' => $user, 'title' => 'My API IPCR']);
        Ipcr::factoryLike()->create(['upcr' => $mine->upcr, 'title' => 'Other private API IPCR']);
        $html = $this->getJson('/api/ipcrs')->assertOk()->json('html');
        $this->assertStringContainsString('My API IPCR', $html);
        $this->assertStringNotContainsString('Other private API IPCR', $html);
    }

    public function test_api_mutations_require_csrf_token(): void
    {
        $this->signIn();
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->postJson('/api/offices', ['name' => 'Missing CSRF'])->assertStatus(419)->assertJsonStructure(['message']);
        $this->assertDatabaseCount('offices', 0);
        $this->withSession(['_token' => 'test-csrf-token'])
            ->postJson('/api/offices', ['name' => 'Valid CSRF', '_token' => 'test-csrf-token'])->assertOk();
    }

    public function test_unknown_records_and_routes_return_json_404(): void
    {
        $this->signIn();
        $this->get('/api/offices/999/edit')->assertNotFound()->assertJsonStructure(['message']);
        $this->get('/api/unknown')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_pagination_links_remain_normal_web_urls(): void
    {
        $this->signIn();
        Office::factory()->count(11)->create();
        $html = $this->getJson('/api/offices')->assertOk()->json('html');
        $this->assertStringContainsString(url('/offices').'?page=2', $html);
        $this->assertStringNotContainsString(url('/api/offices').'?page=2', $html);
    }

    public function test_invalid_api_upload_is_rejected_without_creating_a_record(): void
    {
        Storage::fake('public');
        $this->signIn('Employee');
        $this->post('/api/movs', [
            'title' => 'Invalid upload',
            'file' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('movs', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MovUploadTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('acceptedMovFiles')]
    public function test_users_can_upload_supported_mov_files(string $filename, string $mimeType): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');

        $response = $this
            ->actingAs($user)
            ->post(route('movs.store'), [
                'title' => 'Quarterly MOV',
                'description' => 'Evidence for submitted accomplishment.',
                'file' => UploadedFile::fake()->create($filename, 256, $mimeType),
            ]);

        $mov = \App\Models\Mov::firstOrFail();

        $response->assertRedirect(route('movs.show', $mov));
        $this->assertTrue(Storage::disk('public')->exists($mov->file_path));

        $this->assertDatabaseHas('movs', [
            'title' => 'Quarterly MOV',
            'original_name' => $filename,
            'uploaded_by' => $user->id,
        ]);
    }

    public function test_users_can_view_mov_uploads(): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Supervisor');

        $this
            ->actingAs($user)
            ->post(route('movs.store'), [
                'title' => 'Monitoring Photo',
                'file' => UploadedFile::fake()->create('monitoring.jpg', 256, 'image/jpeg'),
            ]);

        $response = $this
            ->actingAs($user)
            ->get(route('movs.index'));

        $response->assertOk();
        $response->assertSee('Monitoring Photo');
    }

    public function test_unsupported_mov_file_types_are_rejected(): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Employee');

        $response = $this
            ->actingAs($user)
            ->post(route('movs.store'), [
                'title' => 'Invalid MOV',
                'file' => UploadedFile::fake()->create('script.exe', 12, 'application/octet-stream'),
            ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseCount('movs', 0);
    }

    public static function acceptedMovFiles(): array
    {
        return [
            'pdf' => ['evidence.pdf', 'application/pdf'],
            'docx' => ['evidence.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xlsx' => ['evidence.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'jpg' => ['evidence.jpg', 'image/jpeg'],
        ];
    }
}
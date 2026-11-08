<?php

namespace Tests\Feature;

use App\Models\{Office, PositionTitle, User};
use App\Services\ReferenceData;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{Cache, DB, File};
use Tests\TestCase;

class ReferenceDataCacheTest extends TestCase
{
    use DatabaseMigrations;

    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cachePath = storage_path('framework/testing/reference-'.bin2hex(random_bytes(8)));
        config(['cache.stores.reference.path' => $this->cachePath]);
        Cache::forgetDriver('reference');
    }

    protected function tearDown(): void
    {
        $path = realpath($this->cachePath);
        $root = realpath(storage_path('framework/testing'));
        if ($path && $root && str_starts_with($path, $root.DIRECTORY_SEPARATOR)) File::deleteDirectory($path);
        parent::tearDown();
    }

    public function test_position_options_are_reused_and_refreshed_after_create_update_and_delete(): void
    {
        $catalogue = app(ReferenceData::class);
        $position = PositionTitle::create(['legacy_csc_item_id' => 91001, 'title' => 'Analyst', 'is_active' => true]);
        $this->assertSame('Analyst', $catalogue->positionOptions()->sole()->title);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame('Analyst', $catalogue->positionOptions()->sole()->title);
        $this->assertCount(0, DB::getQueryLog());
        $position->update(['title' => 'Senior analyst']);
        $this->assertSame('Senior analyst', $catalogue->positionOptions()->sole()->title);
        $position->delete();
        $this->assertCount(0, $catalogue->positionOptions());
        PositionTitle::create(['legacy_csc_item_id' => 91002, 'title' => 'Technician', 'is_active' => true]);
        $this->assertSame('Technician', $catalogue->positionOptions()->sole()->title);
    }

    public function test_office_labels_refresh_and_live_scope_excludes_other_campuses_and_inactive_records(): void
    {
        $catalogue = app(ReferenceData::class);
        $office = Office::factory()->activeForCampus()->create(['name' => 'CISA']);
        Office::factory()->create(['campus' => 99, 'is_active' => true]);
        $this->assertSame([$office->id], $catalogue->officeOptions()->modelKeys());
        $office->update(['name' => 'Updated CISA']);
        $this->assertSame('Updated CISA', $catalogue->officeOptions()->sole()->name);
        $office->update(['is_active' => false]);
        $this->assertCount(0, $catalogue->officeOptions());
        $employee = User::factory()->create(['office_id' => $office->id]);
        $this->assertSame([$office->id], $catalogue->officeOptions($employee)->modelKeys());
        $this->assertFalse($catalogue->officeOptions($employee)->sole()->is_active);
    }

    public function test_rollback_does_not_publish_uncommitted_reference_data(): void
    {
        $catalogue = app(ReferenceData::class);
        $position = PositionTitle::create(['legacy_csc_item_id' => 91003, 'title' => 'Original', 'is_active' => true]);
        $catalogue->positionOptions();
        DB::beginTransaction();
        $position->update(['title' => 'Uncommitted']);
        $this->assertSame('Uncommitted', $catalogue->positionOptions()->sole()->title);
        DB::rollBack();
        $this->assertSame('Original', $catalogue->positionOptions()->sole()->title);
        DB::transaction(fn () => $position->fresh()->update(['title' => 'Committed']));
        $this->assertSame('Committed', $catalogue->positionOptions()->sole()->title);
    }

    public function test_bulk_sql_changes_refresh_on_expiry_or_scoped_cache_clear(): void
    {
        $catalogue = app(ReferenceData::class);
        $position = PositionTitle::create(['legacy_csc_item_id' => 91004, 'title' => 'Initial title', 'is_active' => true]);
        $catalogue->positionOptions();
        DB::table('position_titles')->where('id', $position->id)->update(['title' => 'Bulk update']);
        $this->assertSame('Initial title', $catalogue->positionOptions()->sole()->title);
        $this->travel(301)->seconds();
        $this->assertSame('Bulk update', $catalogue->positionOptions()->sole()->title);
        DB::table('position_titles')->where('id', $position->id)->update(['title' => 'Next update']);
        Cache::store('array')->put('other-cache', 'preserved', 600);
        $this->artisan('cache:clear', ['store' => 'reference'])->assertSuccessful();
        $this->assertSame('Next update', $catalogue->positionOptions()->sole()->title);
        $this->assertSame('preserved', Cache::store('array')->get('other-cache'));
    }
}

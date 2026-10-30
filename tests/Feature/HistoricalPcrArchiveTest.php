<?php

namespace Tests\Feature;

use App\Models\EvaluationCycle;
use App\Models\HistoricalPcr;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\DemoPcrImport;
use App\Services\HistoricalPcrArchive;
use App\Services\HistoricalPcrExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class HistoricalPcrArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private User $reviewer;

    private HistoricalPcr $pcr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Administrator');
        $this->owner = User::factory()->create();
        $this->owner->assignRole('Employee');
        $this->reviewer = User::factory()->create();
        $this->reviewer->assignRole('Employee');
        $cycle = EvaluationCycle::create(['year' => 2025, 'semester' => EvaluationCycle::FIRST_SEMESTER, 'status' => 'closed']);
        $disk = Storage::disk('local');
        $disk->makeDirectory('historical-pcr/test/pages');
        $disk->put('historical-pcr/test/source.pdf', Pdf::loadHTML('<p>Synthetic source</p>')->output());
        $im = imagecreatetruecolor(600, 400);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagejpeg($im, $disk->path('historical-pcr/test/pages/1.jpg'));
        imagejpeg($im, $disk->path('historical-pcr/test/pages/2.jpg'));
        imagedestroy($im);
        $this->pcr = HistoricalPcr::create(['evaluation_cycle_id' => $cycle->id, 'owner_id' => $this->owner->id, 'owner_name' => $this->owner->name, 'imported_by' => $this->admin->id, 'type' => 'ipcr', 'source_name' => 'synthetic.pdf', 'source_path' => 'historical-pcr/test/source.pdf', 'source_sha256' => hash_file('sha256', $disk->path('historical-pcr/test/source.pdf')), 'source_bytes' => 100, 'source_pages' => 2, 'form_pages' => 1, 'image_directory' => 'historical-pcr/test/pages']);
        $snapshot = ['office' => 'Synthetic office', 'owner_position' => 'Staff', 'form_date' => '', 'reported_overall' => '4.21', 'adjectival_rating' => 'VS', 'notes' => ['Unclear source weight retained.'], 'rows' => [['page' => 1, 'section' => 'Core (75%)', 'indicator' => '=1+1', 'budget' => '', 'accomplishment' => 'N/A', 'q' => '', 'e' => 'N/A', 't' => '4', 'a' => '', 'remarks' => '', 'review_note' => 'Blank average retained.']], 'signatories' => [], 'page_text' => []];
        $revision = $this->pcr->revisions()->create(['number' => 1, 'snapshot' => $snapshot, 'reason' => 'Synthetic initial draft', 'created_by' => $this->admin->id]);
        $this->pcr->update(['current_revision_id' => $revision->id]);
        $this->pcr->participants()->create(['user_id' => $this->reviewer->id, 'source_name' => $this->reviewer->name, 'source_role' => 'Reviewer']);
    }

    public function test_owner_reviewer_and_admin_can_read_but_other_users_cannot(): void
    {
        foreach ([$this->owner, $this->reviewer, $this->admin] as $user) {
            $this->actingAs($user)->get(route('historical-pcrs.show', $this->pcr))->assertOk()->assertSee('4.21');
            $this->get(route('historical-pcrs.original', $this->pcr))->assertOk();
            $this->get(route('historical-pcrs.page', [$this->pcr, 2]))->assertOk();
            $this->get('/api/historical-pcrs/'.$this->pcr->id)->assertOk()->assertJsonStructure(['html']);
        }
        $other = User::factory()->create();
        $other->assignRole('Employee');
        $this->actingAs($other);
        foreach (['', '/original', '/pages/1', '/edit', '/export/docx', '/export/xlsx', '/export/pdf'] as $suffix) {
            $this->get('/historical-pcrs/'.$this->pcr->id.$suffix)->assertForbidden();
        }
        $this->get('/api/historical-pcrs/'.$this->pcr->id)->assertForbidden();
        $this->get('/historical-pcrs')->assertDontSee($this->pcr->owner_name);
        $this->post(route('historical-pcrs.correction', $this->pcr), ['message' => 'No access', 'revision_id' => $this->pcr->current_revision_id])->assertForbidden();
    }

    public function test_verified_revisions_are_locked_and_corrections_create_a_new_draft(): void
    {
        $this->actingAs($this->owner)->post(route('historical-pcrs.verify', $this->pcr), ['revision_id' => $this->pcr->current_revision_id, 'acknowledge' => 1])->assertForbidden();
        $this->actingAs($this->admin)->post(route('historical-pcrs.verify', $this->pcr), ['revision_id' => $this->pcr->current_revision_id, 'acknowledge' => 1])->assertRedirect();
        $old = $this->pcr->fresh()->revision;
        $this->assertNotNull($old->verified_at);
        $this->actingAs($this->owner)->post(route('historical-pcrs.correction', $this->pcr), ['message' => 'Review weight on p.1', 'revision_id' => $old->id])->assertRedirect();
        $this->actingAs($this->admin)->put(route('historical-pcrs.update', $this->pcr), ['revision_id' => $old->id, 'reason' => 'Preserve source weight for review', 'snapshot' => $old->snapshot, 'corrections' => [$this->pcr->corrections()->first()->id]])->assertRedirect()->assertSessionHasNoErrors();
        $new = $this->pcr->fresh()->revision;
        $this->assertSame(2, $new->number);
        $this->assertNull($new->verified_at);
        $this->assertSame($old->snapshot, $old->fresh()->snapshot);
        $this->actingAs($this->admin)->put(route('historical-pcrs.update', $this->pcr), ['revision_id' => $old->id, 'reason' => 'Stale revision', 'snapshot' => $old->snapshot])->assertSessionHasErrors('revision');
        $old->snapshot = ['changed' => true];
        $this->expectException(\LogicException::class);
        $old->save();
    }

    public function test_exports_are_real_office_files_and_include_evidence(): void
    {
        $service = app(HistoricalPcrExport::class);
        foreach (['docx', 'xlsx', 'pdf'] as $format) {
            $path = $service->file($this->pcr, $format);
            $this->assertFileExists($path);
            $this->actingAs($this->owner)->get(route('historical-pcrs.export', [$this->pcr, $format]))->assertOk();
            if ($format === 'pdf') {
                $this->assertStringStartsWith('%PDF', file_get_contents($path));

                continue;
            }
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            $this->assertNotFalse($zip->locateName($format === 'docx' ? 'word/document.xml' : 'xl/workbook.xml'));
            $media = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (preg_match('~^(word|xl)/media/.*\.(jpg|jpeg|png)$~', $zip->getNameIndex($i))) {
                    $media[] = $zip->getNameIndex($i);
                }
            } $this->assertCount(1, $media);
            $zip->close();
            if ($format === 'xlsx') {
                $book = IOFactory::load($path);
                $this->assertSame(2, $book->getSheetCount());
                $found = false;
                foreach ($book->getSheet(0)->getRowIterator() as $r) {
                    foreach ($r->getCellIterator() as $c) {
                        if ($c->getValue() === '=1+1') {
                            $found = true;
                            $this->assertSame('s', $c->getDataType());
                        }
                    }
                } $this->assertTrue($found);
                $book->disconnectWorksheets();
            }
        }
    }

    public function test_identity_links_do_not_depend_on_email_and_history_blocks_deletion(): void
    {
        $identity = $this->owner->identities()->create(['provider' => 'google', 'issuer' => 'https://accounts.google.com', 'subject' => 'synthetic-subject']);
        $this->owner->update(['email' => 'new-school-identity@example.edu']);
        $this->assertSame($this->owner->id, $identity->fresh()->user_id);
        $this->assertSame($this->owner->id, $this->pcr->fresh()->owner_id);
        $this->actingAs($this->admin)->delete(route('employees.destroy', $this->owner))->assertRedirect();
        $this->assertModelExists($this->owner);
        $this->expectException(QueryException::class);
        UserIdentity::create(['user_id' => $this->reviewer->id, 'provider' => 'google', 'issuer' => 'https://accounts.google.com', 'subject' => 'synthetic-subject']);
    }

    public function test_source_bounds_and_missing_ratings_are_preserved(): void
    {
        $valid = app(HistoricalPcrArchive::class)->validateSnapshot($this->pcr->revision->snapshot, 1);
        $this->assertNull($valid['rows'][0]['numeric_ratings']['q']);
        $this->assertNull($valid['rows'][0]['numeric_ratings']['e']);
        $this->assertSame(4.0, $valid['rows'][0]['numeric_ratings']['t']);
        $this->actingAs($this->owner)->get(route('historical-pcrs.page', [$this->pcr, 3]))->assertNotFound();
        $this->get(route('historical-pcrs.edit', $this->pcr))->assertForbidden();
    }

    public function test_local_import_is_idempotent_without_password_resets_or_email_matching(): void
    {
        if (! is_file(config('historical-pcr.pdfinfo')) || ! is_file(config('historical-pcr.pdftoppm'))) {
            $this->markTestSkipped('Poppler is required for the real PDF import integration test.');
        }
        $manifest = ['version' => 1, 'period' => ['year' => 2025, 'semester' => EvaluationCycle::FIRST_SEMESTER], 'people' => [['key' => 'synthetic-owner', 'name' => 'Synthetic owner', 'email' => 'synthetic-owner@spms.test']], 'reports' => [['owner' => 'synthetic-owner', 'type' => 'ipcr', 'source_path' => Storage::disk('local')->path($this->pcr->source_path), 'form_pages' => 1, 'snapshot' => $this->pcr->revision->snapshot, 'participants' => []]]];
        $service = app(DemoPcrImport::class);
        $first = $service->run($manifest, 'SyntheticPassword123', $this->admin);
        $person = $first['people']['synthetic-owner'];
        $hash = $person->password;
        $id = $first['reports'][0]->id;
        $person->update(['email' => 'linked-school-address@example.edu']);
        $again = $service->run($manifest, 'DifferentPassword123', $this->admin);
        $this->assertSame($person->id, $again['people']['synthetic-owner']->id);
        $this->assertSame($hash, $again['people']['synthetic-owner']->password);
        $this->assertSame($id, $again['reports'][0]->id);
        $this->assertSame(['Employee'], $person->getRoleNames()->all());
        $this->assertSame(1,$again['reports'][0]->revisions()->count());
        $this->assertSame('closed',EvaluationCycle::first()->status);
    }
}

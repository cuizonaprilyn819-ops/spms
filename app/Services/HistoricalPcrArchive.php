<?php

namespace App\Services;

use App\Models\HistoricalPcr;
use App\Models\HistoricalPcrRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class HistoricalPcrArchive
{
    public const FIELDS = ['objective', 'activity', 'indicator', 'budget', 'accountable', 'accomplishment', 'q', 'e', 't', 'a', 'remarks', 'review_note'];

    public function validateSnapshot(array $snapshot, int $formPages): array
    {
        $rules = [
            'office' => 'nullable|string|max:500', 'owner_position' => 'nullable|string|max:500',
            'form_date' => 'nullable|string|max:100', 'reported_overall' => 'nullable|string|max:100',
            'adjectival_rating' => 'nullable|string|max:100', 'notes' => 'present|array|max:100',
            'notes.*' => 'required|string|max:2000', 'rows' => 'present|array|max:1000',
            'rows.*.page' => 'required|integer|min:1|max:'.$formPages,
            'rows.*.section' => 'required|string|max:500',
            'signatories' => 'present|array|max:30', 'signatories.*.name' => 'required|string|max:255',
            'signatories.*.role' => 'required|string|max:255',
            'signatories.*.page' => 'required|integer|min:1|max:'.$formPages,
            'signatories.*.date' => 'nullable|string|max:100',
            'page_text' => 'present|array|max:100',
            'page_text.*.page' => 'required|integer|min:1|max:'.$formPages,
            'page_text.*.text' => 'nullable|string|max:30000',
            'page_text.*.method' => 'required|string|max:100',
        ];
        foreach (self::FIELDS as $field) {
            $rules['rows.*.'.$field] = 'nullable|string|max:6000';
        }
        $valid = Validator::make($snapshot, $rules)->validate();
        $valid['schema_version'] = 1;
        // Numeric interpretations never replace the source notation, including N/A and blanks.
        foreach ($valid['rows'] as &$row) {
            $row['numeric_ratings'] = [];
            foreach (['q', 'e', 't', 'a'] as $field) {
                $raw = trim((string) ($row[$field] ?? ''));
                $row['numeric_ratings'][$field] = preg_match('/^\d+(?:\.\d+)?$/D', $raw) ? (float) $raw : null;
            }
        }

        return $valid;
    }

    /** A source is immutable. Identical imports return it; a changed source requires deliberate review. */
    public function import(string $source, string $sourceName, array $attributes, array $snapshot, array $participants, User $actor): HistoricalPcr
    {
        $size = is_file($source) ? filesize($source) : 0;
        if ($size <= 0 || $size > 25 * 1024 * 1024 || (new \finfo(FILEINFO_MIME_TYPE))->file($source) !== 'application/pdf') {
            throw ValidationException::withMessages(['source' => 'Choose a PDF no larger than 25 MiB.']);
        }
        $hash = hash_file('sha256', $source);
        $identity = array_intersect_key($attributes, array_flip(['owner_id', 'evaluation_cycle_id', 'type']));
        $existing = HistoricalPcr::where($identity)->first();
        if ($existing) {
            if (hash_equals($existing->source_sha256, $hash)) {
                return $existing;
            }
            throw ValidationException::withMessages(['source' => 'A different source is already archived for this owner, period and PCR type. Preserve it and review the replacement separately.']);
        }
        $snapshot = $this->validateSnapshot($snapshot, (int) $attributes['form_pages']);
        $directory = 'historical-pcr/'.Str::uuid();
        $disk = Storage::disk('local');
        try {
            $disk->makeDirectory($directory);
            $stream = fopen($source, 'rb');
            try {
                $disk->put($directory.'/source.pdf', $stream);
            } finally {
                fclose($stream);
            }
            $localSource = $disk->path($directory.'/source.pdf');
            $info = new Process([config('historical-pcr.pdfinfo'), '-enc', 'UTF-8', $localSource]);
            $info->setTimeout(30)->mustRun();
            if (! preg_match('/^Pages:\s+(\d+)/m', $info->getOutput(), $match)) {
                throw new \RuntimeException('The PDF page count could not be read.');
            }
            $pages = (int) $match[1];
            if ($pages < $attributes['form_pages'] || $pages > config('historical-pcr.max_pages')) {
                throw ValidationException::withMessages(['form_pages' => 'The form-page count must fit the source PDF, which may contain at most 100 pages.']);
            }
            $disk->makeDirectory($directory.'/pages');
            $render = new Process([config('historical-pcr.pdftoppm'), '-jpeg', '-jpegopt', 'quality=88', '-r', (string) config('historical-pcr.render_dpi'), $localSource, $disk->path($directory.'/pages/page')]);
            $render->setTimeout(config('historical-pcr.render_timeout'))->mustRun();
            $rendered = $disk->files($directory.'/pages');
            if (count($rendered) !== $pages) {
                throw new \RuntimeException('Not all source pages could be rendered.');
            }
            // Normalize zero-padded renderer names once. URLs accept integer pages only.
            foreach ($rendered as $path) {
                if (preg_match('/page-(\d+)\.jpg$/', $path, $number)) {
                    $target = $directory.'/pages/'.(int) $number[1].'.jpg';
                    $disk->move($path, $target);
                }
            }

            return DB::transaction(function () use ($identity, $attributes, $hash, $directory, $sourceName, $size, $pages, $snapshot, $participants, $actor) {
                // A stable owner row serializes imports across workers, including different source hashes.
                $owner = User::whereKey($identity['owner_id'])->lockForUpdate()->firstOrFail();
                $existing = HistoricalPcr::where($identity)->first();
                if ($existing) {
                    throw ValidationException::withMessages(['source' => 'This report was imported by another request. Reload the archive before trying again.']);
                }
                $pcr = HistoricalPcr::create([
                    ...$identity, 'owner_name' => $owner->name, 'imported_by' => $actor->id,
                    'form_pages' => $attributes['form_pages'], 'source_pages' => $pages,
                    'source_name' => basename(str_replace('\\', '/', $sourceName)),
                    'source_path' => $directory.'/source.pdf', 'source_sha256' => $hash,
                    'source_bytes' => $size, 'image_directory' => $directory.'/pages',
                ]);
                $revision = $pcr->revisions()->create(['number' => 1, 'snapshot' => $snapshot,
                    'reason' => 'Initial source transcription; administrator review pending.', 'created_by' => $actor->id]);
                $pcr->update(['current_revision_id' => $revision->id]);
                foreach ($participants as $participant) {
                    $pcr->participants()->create($participant);
                }

                return $pcr->load('revision', 'cycle', 'owner');
            });
        } catch (\Throwable $exception) {
            $disk->deleteDirectory($directory);
            throw $exception;
        }
    }

    public function revise(HistoricalPcr $pcr, array $snapshot, string $reason, int $expected, User $actor, array $corrections = []): HistoricalPcrRevision
    {
        $snapshot = $this->validateSnapshot($snapshot, $pcr->form_pages);

        return DB::transaction(function () use ($pcr, $snapshot, $reason, $expected, $actor, $corrections) {
            $locked = HistoricalPcr::whereKey($pcr->id)->lockForUpdate()->firstOrFail();
            $this->assertRevision($locked, $expected);
            $revision = $locked->revisions()->create(['number' => $locked->revision->number + 1,
                'snapshot' => $snapshot, 'reason' => $reason, 'created_by' => $actor->id]);
            $locked->update(['current_revision_id' => $revision->id]);
            $locked->corrections()->whereIn('id', $corrections)->whereNull('resolved_revision_id')->update(['resolved_revision_id' => $revision->id]);

            return $revision;
        });
    }

    public function verify(HistoricalPcr $pcr, int $expected, User $actor): void
    {
        DB::transaction(function () use ($pcr, $expected, $actor) {
            $locked = HistoricalPcr::whereKey($pcr->id)->lockForUpdate()->firstOrFail();
            $this->assertRevision($locked, $expected);
            if (! count($locked->revision->snapshot['rows'])) {
                throw ValidationException::withMessages(['verification' => 'Add the source transcription rows before verifying.']);
            }
            if (! $locked->revision->verified_at) {
                $locked->revision->update(['verified_by' => $actor->id, 'verified_at' => now()]);
            }
        });
    }

    private function assertRevision(HistoricalPcr $pcr, int $expected): void
    {
        if ((int) $pcr->current_revision_id !== $expected) {
            throw ValidationException::withMessages(['revision' => 'Another administrator saved a revision. Reload before continuing.']);
        }
    }

    public function privatePath(string $path): string
    {
        if (! str_starts_with($path, 'historical-pcr/') || str_contains($path, '..') || str_contains($path, '\\')) {
            abort(404);
        }
        $resolved = realpath(Storage::disk('local')->path($path));
        $root = realpath(Storage::disk('local')->path('historical-pcr'));
        abort_unless($resolved && $root && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) && is_file($resolved), 404);

        return $resolved;
    }
}

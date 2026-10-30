<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DemoPcrImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ImportHistoricalPcrDemo extends Command
{
    protected $signature = 'spms:import-historical-demo {manifest : Private JSON source manifest} {--password-file= : Private plaintext demo password file} {--administrator= : Existing administrator email}';

    protected $description = 'Import reviewed local-demo personnel and historical sources without replacing existing records.';

    public function handle(DemoPcrImport $importer): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is available only in local/test installations.');

            return self::FAILURE;
        }
        $manifestPath = realpath($this->argument('manifest'));
        $passwordPath = realpath((string) $this->option('password-file'));
        if (! $manifestPath || ! $passwordPath || ! is_file($manifestPath) || ! is_file($passwordPath)) {
            $this->error('Provide an existing private manifest and password file.');

            return self::FAILURE;
        }
        $actor = User::where('email', $this->option('administrator'))->first();
        if (! $actor?->hasRole('Administrator')) {
            $this->error('Select an existing administrator.');

            return self::FAILURE;
        }
        $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $password = trim(file_get_contents($passwordPath));
        $result = Cache::lock('historical-demo-import', 900)->block(5, fn () => $importer->run($manifest, $password, $actor));
        $this->table(['Personnel', 'Local login'], array_map(fn ($p) => [$p->name, $p->email], array_values($result['people'])));
        $this->table(['PCR', 'Owner', 'Source pages', 'Form entries'], array_map(fn ($p) => [strtoupper($p->type), $p->owner_name, $p->source_pages, count($p->revision->snapshot['rows'])], $result['reports']));
        $this->info('Import complete. Repeated imports retain existing passwords, identities and revisions.');

        return self::SUCCESS;
    }
}

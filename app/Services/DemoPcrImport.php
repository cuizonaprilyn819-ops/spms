<?php

namespace App\Services;

use App\Models\EvaluationCycle;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Local-only loader. Personal source manifests and passwords are never packaged in the repository. */
class DemoPcrImport
{
    public function run(array $manifest, string $password, User $actor): array
    {
        abort_unless(app()->environment(['local', 'testing']) && $actor->hasRole('Administrator'), 403);
        Validator::make($manifest, [
            'version' => 'required|in:1', 'period.year' => 'required|integer', 'period.semester' => 'required|string',
            'people' => 'required|array|min:1|max:100', 'people.*.key' => 'required|alpha_dash|max:100|distinct',
            'people.*.name' => 'required|string|max:255', 'people.*.email' => ['required', 'email', 'distinct', 'regex:/@spms\.test$/D'],
            'reports' => 'required|array|min:1|max:100', 'reports.*.owner' => 'required|string',
            'reports.*.type' => 'required|in:opcr,dpcr,upcr,ipcr', 'reports.*.source_path' => 'required|string',
            'reports.*.form_pages' => 'required|integer|min:1|max:100', 'reports.*.snapshot' => 'required|array',
            'reports.*.participants' => 'present|array', 'reports.*.participants.*.person' => 'required|string',
            'reports.*.participants.*.source_role' => 'required|string|max:255',
        ])->validate();
        Validator::make(['password' => $password], ['password' => 'required|string|min:8|max:255'])->validate();
        $cycle = EvaluationCycle::where('year', $manifest['period']['year'])->where('semester', $manifest['period']['semester'])->sole();
        $keys = array_column($manifest['people'], 'key');
        $archive = app(HistoricalPcrArchive::class);
        // Complete preflight before creating accounts or copying sources.
        foreach ($manifest['reports'] as $report) {
            if (! in_array($report['owner'], $keys, true) || ! is_file($report['source_path'])) {
                throw ValidationException::withMessages(['manifest' => 'A source file or owner is missing. No import started.']);
            }
            $archive->validateSnapshot($report['snapshot'], $report['form_pages']);
            $participants = [];
            foreach ($report['participants'] as $participant) {
                if (! in_array($participant['person'], $keys, true) || in_array($participant['person'], $participants, true)) {
                    throw ValidationException::withMessages(['manifest' => 'A participant is unknown or listed twice.']);
                }
                $participants[] = $participant['person'];
            }
        }
        $people = DB::transaction(function () use ($manifest, $password) {
            Role::findOrCreate('Employee', 'web');
            $people = [];
            foreach ($manifest['people'] as $person) {
                $identity = UserIdentity::where(['provider' => 'local-demo', 'issuer' => 'spms.local.demo', 'subject' => $person['key']])->first();
                if ($identity) {
                    $people[$person['key']] = $identity->user;

                    continue;
                }
                if (User::where('email', $person['email'])->exists()) {
                    throw ValidationException::withMessages(['manifest' => 'A requested demo email already belongs to another account. Existing accounts were not changed.']);
                }
                $user = new User;
                $user->forceFill(['name' => $person['name'], 'email' => $person['email'], 'password' => $password, 'is_demo' => true])->save();
                $user->assignRole('Employee');
                $user->identities()->create(['provider' => 'local-demo', 'issuer' => 'spms.local.demo', 'subject' => $person['key']]);
                $people[$person['key']] = $user;
            }

            return $people;
        });
        $reports = [];
        foreach ($manifest['reports'] as $report) {
            $participants = array_map(fn ($p) => ['user_id' => $people[$p['person']]->id,
                'source_name' => $people[$p['person']]->name, 'source_role' => $p['source_role']], $report['participants']);
            $reports[] = $archive->import($report['source_path'], basename($report['source_path']), [
                'owner_id' => $people[$report['owner']]->id, 'evaluation_cycle_id' => $cycle->id,
                'type' => $report['type'], 'form_pages' => $report['form_pages'],
            ], $report['snapshot'], $participants, $actor);
        }

        return ['people' => $people, 'reports' => $reports];
    }
}

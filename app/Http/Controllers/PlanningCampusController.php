<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\CommitmentPlan;
use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\OfficeTarget;
use App\Models\PlanningGrant;
use App\Models\User;
use App\Services\CommitmentPlanning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlanningCampusController extends Controller
{
    public function __construct(private CommitmentPlanning $planning) {}

    public function index(Request $r)
    {
        abort_unless($r->user()->hasRole('Administrator'), 403);

        return view('planning.campuses', ['campuses' => Campus::all(), 'offices' => Office::with('campusRecord')->orderBy('name')->get(),
            'people' => User::orderBy('name')->get(), 'grants' => PlanningGrant::with(['user', 'campus'])->get(), 'cycles' => EvaluationCycle::where('status', 'open')->get()]);
    }

    public function campus(Request $r)
    {
        abort_unless($r->user()->hasRole('Administrator'), 403);
        Campus::create($r->validate(['name' => ['required', 'string', 'max:255', 'unique:campuses'], 'code' => ['required', 'string', 'max:50', 'unique:campuses'], 'legacy_reference' => ['nullable', 'integer', 'min:0', 'unique:campuses']]));

        return back()->with('status', 'Campus added. Assign and verify its offices before activation.');
    }

    public function office(Request $r)
    {
        abort_unless($r->user()->hasRole('Administrator'), 403);
        $d = $r->validate(['name' => ['required', 'string', 'max:255', 'unique:offices'], 'code' => ['required', 'string', 'max:50', 'unique:offices'], 'campus_id' => ['nullable', Rule::exists('campuses', 'id')], 'organization_scope' => ['required', Rule::in(['university', 'campus'])]]);
        abort_unless(($d['organization_scope'] === 'university') === empty($d['campus_id']), 422, 'Choose a campus for campus offices; university offices have no campus.');
        Office::create([...$d, 'is_active' => true]);

        return back()->with('status', 'Office created. Record its personnel and verify its reporting structure in Commitment Planning.');
    }

    public function classify(Request $r, Office $office)
    {
        abort_unless($r->user()->hasRole('Administrator'), 403);
        $d = $r->validate(['campus_id' => ['nullable', Rule::exists('campuses', 'id')], 'organization_scope' => ['required', Rule::in(['university', 'campus'])]]);
        abort_unless(($d['organization_scope'] === 'university') === empty($d['campus_id']), 422, 'Choose the campus assignment matching this classification.');
        DB::transaction(function () use ($office, $d, $r) {
            Office::orderBy('id')->lockForUpdate()->get();
            $branch = Office::all()->filter(fn ($o) => in_array($office->id, $this->planning->organization->ancestors($o), true))->modelKeys();
            abort_if(CommitmentPlan::whereIn('office_id', $branch)->exists(), 422, 'This branch already has plans. Preserve its structure and configure a new branch.');
            $office->update([...$d, 'hierarchy_verified_at' => null]);
            $this->planning->event(null, $r->user(), 'Office campus classification recorded', ['office_id' => $office->id, ...$d]);
        });

        return back()->with('status', 'Classification saved. Verify the reporting structure again before use.');
    }

    public function grant(Request $r)
    {
        abort_unless($r->user()->hasRole('Administrator'), 403);
        $d = $r->validate(['user_id' => ['required', Rule::exists('users', 'id')], 'campus_id' => ['nullable', Rule::exists('campuses', 'id')], 'scope' => ['required', Rule::in(['central', 'campus'])], 'reason' => ['required', 'string', 'max:1000']]);
        abort_unless(User::findOrFail($d['user_id'])->hasRole('Planning Officer'), 422, 'Assign the existing Planning Officer role before granting its organizational scope.');
        abort_unless(($d['scope'] === 'central') === empty($d['campus_id']), 422, 'Campus grants need a campus; central grants have none.');
        DB::transaction(function () use ($d, $r) {
            User::whereKey($d['user_id'])->lockForUpdate()->firstOrFail();
            PlanningGrant::updateOrCreate(['user_id' => $d['user_id'], 'scope' => $d['scope'], 'campus_id' => $d['campus_id'] ?? null], ['reason' => $d['reason'], 'granted_by' => $r->user()->id]);
            $this->planning->event(null, $r->user(), 'Planning access granted', $d);
        });

        return back()->with('status', 'Planning scope saved. Existing login roles are unchanged.');
    }

    public function revoke(Request $r, PlanningGrant $grant)
    {
        abort_unless($r->user()->hasRole('Administrator'), 403);
        $this->planning->event(null, $r->user(), 'Planning access revoked', $grant->toArray());
        $grant->delete();

        return back()->with('status', 'Planning scope revoked.');
    }

    public function activate(Request $r, EvaluationCycle $cycle)
    {
        abort_unless($r->user()->hasRole('Administrator'), 403);
        $d = $r->validate(['planning_root_level' => ['sometimes', 'required', Rule::in(['campus', 'university'])], 'university_office_id' => ['nullable', Rule::exists('offices', 'id')], 'planning_readiness' => ['required', 'string', 'min:20', 'max:5000'], 'confirmed' => ['accepted']]);
        DB::transaction(function () use ($cycle, $d, $r) {
            $cycle = EvaluationCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            Office::orderBy('id')->lockForUpdate()->get();
            abort_unless($cycle->isOpen() && ! $cycle->planning_enabled, 422, 'Choose a new open period that has not been activated.');
            abort_if($cycle->opcrs()->exists() || CommitmentPlan::where('evaluation_cycle_id', $cycle->id)->exists() || OfficeTarget::where('evaluation_cycle_id', $cycle->id)->exists(), 422, 'Existing records retain their workflow. Use a new empty period.');
            // Older clients specifying a university retain their original activation behavior.
            $mode = $d['planning_root_level'] ?? (! empty($d['university_office_id']) ? 'university' : 'campus');
            $root = null;
            if ($mode === 'university') {
                abort_unless(! empty($d['university_office_id']), 422, 'Choose the university root office for a university-based period.');
                $root = Office::findOrFail($d['university_office_id']);
                $this->planning->organization->verify($root);
                abort_unless($root->organization_scope === 'university', 422, 'Choose a verified university root office.');
            }
            $branches = Office::where('organization_scope', 'campus')->where('is_active', true)->get()
                ->filter(fn ($o) => ! $root || in_array($root->id, $this->planning->organization->ancestors($o), true));
            abort_if($branches->isEmpty(), 422, 'Configure and verify campus branches first.');
            foreach ($branches as $office) {
                $this->planning->organization->verify($office);
            }
            if ($mode === 'university') {
                abort_unless(PlanningGrant::where('scope', 'central')->whereHas('user.roles', fn ($q) => $q->where('name', 'Planning Officer'))->exists(), 422, 'Record a central Planning Officer assignment first.');
            }
            foreach ($branches->pluck('campus_id')->unique() as $campusId) {
                abort_unless(PlanningGrant::where('scope', 'campus')->where('campus_id', $campusId)->whereHas('user.roles', fn ($q) => $q->where('name', 'Planning Officer'))->exists(), 422, 'Record a Planning Officer assignment for every participating campus.');
            }
            $cycle->update(['planning_enabled' => true, 'multi_campus_enabled' => true, 'planning_root_level' => $mode, 'university_office_id' => $root?->id, 'planning_readiness' => $d['planning_readiness']]);
            $this->planning->event(null, $r->user(), 'Multi-campus planning activated', ['cycle_id' => $cycle->id, ...$d, 'planning_root_level' => $mode, 'university_office_id' => $root?->id]);
        });

        return back()->with('status', 'Planning enabled for this period. Targets may cascade downward or be proposed upward through the configured reporting line.');
    }
}

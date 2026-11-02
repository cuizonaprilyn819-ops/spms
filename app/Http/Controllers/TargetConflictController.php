<?php

namespace App\Http\Controllers;

use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\TargetConflict;
use App\Models\TargetConflictScan;
use App\Services\AssistanceForms;
use App\Services\AssistanceSubject;
use App\Services\PlanningOrganization;
use App\Services\TargetConflictReview;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TargetConflictController extends Controller
{
    public function index(Request $r, TargetConflictReview $reviews)
    {
        $d = $r->validate(['period' => 'nullable|integer', 'office' => 'nullable|integer', 'source' => ['nullable', Rule::in(['rule', 'ai'])], 'status' => ['nullable', Rule::in(['open', 'addressed', 'dismissed', 'correction-required', 'pending', 'completed', 'failed', 'outdated', 'rules-only'])]]);
        $scans = TargetConflictScan::query()->when($d['period'] ?? null, fn ($q, $v) => $q->where('evaluation_cycle_id', $v))->when($d['office'] ?? null, fn ($q, $v) => $q->where('office_id', $v))->when($d['source'] ?? null, fn ($q, $v) => $q->whereHas('findings', fn ($q) => $q->where('source', $v)))->when($d['status'] ?? null, fn ($q, $v) => in_array($v, ['open', 'addressed', 'dismissed', 'correction-required']) ? $q->whereHas('findings', fn ($q) => $q->where('status', $v)) : $q->where('status', $v))->latest()->get()->filter(fn ($s) => $reviews->canView($r->user(), $s));

        return view('target-conflicts.index', ['scans' => $scans, 'periods' => EvaluationCycle::orderByDesc('year')->get(), 'offices' => Office::whereIn('id', app(PlanningOrganization::class)->accessibleOfficeIds($r->user()))->get()]);
    }

    public function scan(Request $r, TargetConflictReview $reviews, AssistanceForms $forms)
    {
        $d = $r->validate(['kind' => ['nullable', Rule::in(array_keys(AssistanceSubject::TYPES))], 'id' => 'nullable|integer|min:1', 'action' => 'nullable|string|max:1000', 'values' => 'sometimes|array|max:100']);
        if (! empty($d['action'])) {
            [$subject] = $forms->resolve($d['action'], $d['values'] ?? [], $r->user(), false);
        } else {
            $subject = $reviews->subjects->resolve($d['kind'] ?? '', $d['id'] ?? 0);
        }
        abort_unless($subject->exists && $reviews->subjects->canView($r->user(), $subject), 403);
        $canonical = $reviews->subjects->canonical($subject);
        abort_unless($reviews->subjects->canEdit($r->user(), $subject) || $reviews->subjects->canReview($r->user(), $canonical) || in_array($r->user()->id, $reviews->subjects->ownerIds($canonical)), 403);
        $publish = ! in_array($canonical->state ?? $canonical->status, ['draft', 'returned', null], true) && in_array($reviews->subjects->kind($canonical), ['plan', 'office-target', 'opcr', 'dpcr', 'upcr', 'ipcr'], true);
        $scan = $reviews->scan($subject, $r->user(), true, $publish);

        return redirect()->route('target-conflicts.show', $scan);
    }

    public function show(Request $r, TargetConflictScan $scan, TargetConflictReview $reviews)
    {
        abort_unless($reviews->canView($r->user(), $scan), 403);
        $subject = $reviews->subjects->resolve($scan->subject_kind, $scan->subject_id);
        // A finding can reference another branch. Only show details if every reference is visible.
        $findings = $scan->findings->filter(function ($f) use ($r, $reviews) {
            foreach ($f->references as $ref) {
                [$kind, $id] = array_pad(explode(':', $ref, 2), 2, null);
                try {
                    if (! $reviews->subjects->canView($r->user(), $reviews->subjects->resolve($kind, (int) $id))) {
                        return false;
                    }
                } catch (\Throwable) {
                    return false;
                }
            }

            return true;
        });

        return response()->view('target-conflicts.show', ['scan' => $scan, 'subject' => $subject, 'findings' => $findings, 'restrictedCount' => $scan->findings->count() - $findings->count(), 'current' => $reviews->current($scan), 'canReview' => $reviews->subjects->canReview($r->user(), $subject)])->header('Cache-Control', 'no-store, private');
    }

    public function disposition(Request $r, TargetConflictScan $scan, TargetConflictReview $reviews)
    {
        $d = $r->validate(['finding_id' => ['nullable', 'integer', Rule::exists('target_conflicts', 'id')->where('scan_id', $scan->id)], 'decision' => ['required', Rule::in(['correction-required', 'addressed', 'dismissed', 'manual-review'])], 'reason' => 'required|string|min:10|max:4000']);
        $reviews->disposition($scan, $r->user(), $d['decision'], $d['reason'], isset($d['finding_id']) ? TargetConflict::findOrFail($d['finding_id']) : null);

        return back()->with('status', 'Reviewer response recorded. Existing approvals and quantities are unchanged.');
    }
}

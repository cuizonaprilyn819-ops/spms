<?php

namespace App\Http\Controllers;

use App\Models\CommitmentPlan;
use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\OfficeTarget;
use App\Models\TargetDocument;
use App\Models\TargetDocumentDraft;
use App\Models\WorkflowAudit;
use App\Services\CommitmentPlanning;
use App\Services\PlanningOrganization;
use App\Services\TargetDocumentAi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TargetDocumentController extends Controller
{
    public function index(Request $request)
    {
        return view('target-documents.index', [
            'documents' => TargetDocument::where('created_by', $request->user()->id)->latest()->paginate(10),
            'cycles' => EvaluationCycle::where('status', EvaluationCycle::STATUS_OPEN)->orderByDesc('year')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'evaluation_cycle_id' => ['required', Rule::exists('evaluation_cycles', 'id')->where('status', EvaluationCycle::STATUS_OPEN)],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'extensions:pdf,doc,docx,xls,xlsx', 'max:20480'],
        ]);
        $file = $data['file'];
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->store('target-documents', 'local');
        abort_unless($path, 500, 'The document could not be stored.');
        $mime = match (strtolower($file->getClientOriginalExtension())) {
            'pdf' => 'application/pdf', 'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
        try {
            $document = DB::transaction(function () use ($request, $data, $file, $path, $mime, $hash) {
                $cycle = EvaluationCycle::whereKey($data['evaluation_cycle_id'])->lockForUpdate()->firstOrFail();
                abort_unless($cycle->isOpen(), 422, 'The evaluation period has closed.');
                // Serialize uploads for a period, including simultaneous retries.
                return TargetDocument::firstOrCreate([
                    'created_by' => $request->user()->id,
                    'evaluation_cycle_id' => $cycle->id,
                    'content_hash' => $hash,
                ], [
                    'original_name' => basename($file->getClientOriginalName()), 'path' => $path, 'mime_type' => $mime,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        if (! $document->wasRecentlyCreated) {
            Storage::disk('local')->delete($path);
        }

        return redirect()->route('target-documents.show', $document);
    }

    public function show(Request $request, TargetDocument $document)
    {
        $this->authorizeDocument($request, $document);

        return view('target-documents.show', ['document' => $document->load('drafts')]);
    }

    public function destroy(Request $request, TargetDocument $document)
    {
        $this->authorizeDocument($request, $document);
        DB::transaction(function () use ($document) {
            $locked = TargetDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'analyzing' && $locked->updated_at->gt(now()->subMinutes(10))) {
                throw ValidationException::withMessages(['document' => 'Wait for document analysis to finish before deleting this file.']);
            }
            if (Storage::disk('local')->exists($locked->path) && ! Storage::disk('local')->delete($locked->path)) {
                throw ValidationException::withMessages(['document' => 'The file could not be deleted. Please try again.']);
            }
            WorkflowAudit::record($locked, 'Source document deleted', ['original_name' => $locked->original_name], null);
            // Drafts cascade on deletion; previously assigned targets remain intact.
            $locked->delete();
        });

        return redirect()->route('target-documents.index')->with('status', 'Document deleted. Previously assigned targets have been kept.');
    }

    public function download(Request $request, TargetDocument $document)
    {
        $this->authorizeDocument($request, $document);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function analyze(Request $request, TargetDocument $document, TargetDocumentAi $ai)
    {
        $this->authorizeDocument($request, $document);
        $token = (string) Str::uuid();
        $claimed = TargetDocument::whereKey($document->id)->where(function ($query) {
            $query->whereIn('status', ['uploaded', 'failed'])->orWhere(function ($stale) {
                $stale->where('status', 'analyzing')->where('updated_at', '<', now()->subMinutes(10));
            });
        })->update(['status' => 'analyzing', 'analysis_token' => $token, 'error' => null, 'updated_at' => now()]);
        if (! $claimed) {
            return back()->withErrors(['analysis' => 'This document is already being analyzed or has results ready for review.']);
        }
        set_time_limit(420);
        try {
            $result = $ai->analyze($document);
            DB::transaction(function () use ($document, $result, $token) {
                $document = TargetDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
                if ($document->status !== 'analyzing' || $document->analysis_token !== $token) {
                    return;
                }
                foreach ($result['targets'] as $proposal) {
                    $document->drafts()->create(['proposal' => $proposal]);
                }
                WorkflowAudit::record($document, 'AI document analysis generated', null, $result);
                $document->update(['status' => 'review', 'analysis_token' => null, 'summary' => $result['summary']."\n\n".($result['warnings'] ?? ''), 'error' => null]);
            });
        } catch (\RuntimeException $exception) {
            TargetDocument::whereKey($document->id)->where('analysis_token', $token)
                ->update(['status' => 'failed', 'analysis_token' => null, 'error' => $exception->getMessage()]);
        }

        return redirect()->route('target-documents.show', $document);
    }

    public function repairHierarchy(Request $request, TargetDocument $document, TargetDocumentAi $ai)
    {
        $this->authorizeDocument($request, $document);
        $token = (string) Str::uuid();
        $claimed = TargetDocument::whereKey($document->id)->where('status', 'review')
            ->update(['status' => 'analyzing', 'analysis_token' => $token, 'error' => null, 'updated_at' => now()]);
        if (! $claimed) return back()->withErrors(['analysis' => 'Wait for analysis to finish before re-reading the hierarchy.']);
        set_time_limit(300);
        try {
            $drafts = $document->drafts()->orderBy('id')->get();
            $result = $ai->hierarchy($document, $drafts->pluck('proposal')->all());
            DB::transaction(function () use ($document, $drafts, $result, $token) {
                $locked = TargetDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
                if ($locked->analysis_token !== $token || $locked->status !== 'analyzing') return;
                $before = [];
                $after = [];
                foreach ($result['mappings'] as $mapping) {
                    $draft = TargetDocumentDraft::whereKey($drafts[$mapping['target_index']]->id)->lockForUpdate()->firstOrFail();
                    $before[$draft->id] = $draft->proposal;
                    $draft->update(['proposal' => [...$draft->proposal, 'program' => $mapping['program'], 'program_outcome' => $mapping['program_outcome']]]);
                    $after[$draft->id] = $draft->proposal;
                }
                WorkflowAudit::record($locked, 'Source program and outcome hierarchy re-read', $before, ['drafts' => $after, 'warnings' => $result['warnings']]);
                $locked->update(['status' => 'review', 'analysis_token' => null, 'error' => $result['warnings'] ?: null]);
            });
        } catch (\RuntimeException $exception) {
            TargetDocument::whereKey($document->id)->where('analysis_token', $token)
                ->update(['status' => 'review', 'analysis_token' => null, 'error' => $exception->getMessage()]);
        }
        return redirect()->route('target-documents.show', $document)->with('status', 'Program/outcome re-read finished. Check the grouping and any source-reading notes below.');
    }

    public function review(Request $request, TargetDocumentDraft $draft)
    {
        $this->authorizeDocument($request, $draft->document);
        if ($draft->document->evaluationCycle?->planning_enabled) {
            return view('planning.import', ['draft' => $draft, 'plans' => CommitmentPlan::where('evaluation_cycle_id', $draft->document->evaluation_cycle_id)->where('level', 'opcr')->get()->filter(fn ($p) => app(PlanningOrganization::class)->canPrepare($request->user(), $p))]);
        }

        return view('target-documents.review', [
            'draft' => $draft,
            'offices' => Office::where('is_active', true)->where('campus', config('hinunangan.campus_id'))->with(['targetHeads', 'employees.roles'])->orderBy('name')->get(),
        ]);
    }

    public function assign(Request $request, TargetDocumentDraft $draft, TargetDocumentAi $ai)
    {
        $this->authorizeDocument($request, $draft->document);
        CommitmentPlanning::legacyOnly($draft->document->evaluationCycle);
        abort_if($draft->proposal['cd_only'], 422, 'CD-only targets cannot be assigned to offices.');
        if ($draft->office_target_id) {
            return redirect()->route('office-targets.show', $draft->office_target_id);
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'success_indicator' => ['required', 'string', 'max:5000'],
            'target_value' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
            'unit_of_measure' => ['required', 'string', 'max:100'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'priority' => ['required', Rule::in(OfficeTarget::PRIORITIES)],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'office_ids' => ['required', 'array', 'min:1', 'max:20'],
            'office_ids.*' => ['integer', 'distinct', Rule::exists('offices', 'id')->where('is_active', true)->where('campus', config('hinunangan.campus_id'))],
            'allocations' => ['required', 'array', 'max:20'],
            'allocations.*' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
            'assignment_type' => ['sometimes', Rule::in(['office', 'individual'])],
            'employee_allocations' => ['sometimes', 'array', 'max:200'],
            'employee_allocations.*' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
            'confirmed' => ['accepted'],
        ]);
        $allocations = [];
        foreach ($data['office_ids'] as $officeId) {
            if (empty($data['allocations'][$officeId])) {
                throw ValidationException::withMessages(['allocations' => 'Enter a positive allocation for every selected office.']);
            }
            $allocations[(int) $officeId] = $data['allocations'][$officeId];
        }
        $allocated = array_sum(array_map(fn ($value) => (int) round($value * 100), $allocations));
        if ($allocated !== (int) round($data['target_value'] * 100)) {
            throw ValidationException::withMessages(['allocations' => 'Office allocations must equal the total target value.']);
        }
        $employeeAllocations = [];
        if (($data['assignment_type'] ?? 'office') === 'individual') {
            $requested = collect($data['employee_allocations'] ?? [])->filter(fn ($v) => $v !== null);
            $people = \App\Models\User::whereIn('id', $requested->keys())->whereIn('office_id', array_keys($allocations))->get();
            if ($requested->isEmpty() || $people->count() !== $requested->count()) {
                throw ValidationException::withMessages(['employee_allocations' => 'Select registered individuals belonging to the selected offices.']);
            }
            foreach ($allocations as $officeId => $quantity) {
                $total = $people->where('office_id', $officeId)->sum(fn ($person) => (int) round($requested[$person->id] * 100));
                if ($total !== (int) round($quantity * 100)) {
                    throw ValidationException::withMessages(['employee_allocations' => 'Individual quantities must equal the allocation for each selected office.']);
                }
            }
            foreach ($people as $person) $employeeAllocations[$person->id] = ['allocated_target_value' => $requested[$person->id], 'assigned_by' => $request->user()->id];
        }
        unset($data['office_ids'], $data['allocations'], $data['confirmed'], $data['assignment_type'], $data['employee_allocations']);
        if (! EvaluationCycle::whereKey($draft->document->evaluation_cycle_id)->where('status', EvaluationCycle::STATUS_OPEN)->exists()) {
            throw ValidationException::withMessages(['period' => 'The evaluation period is closed. Ask the administrator to reopen it before assignment.']);
        }
        $data['start_date'] = $draft->document->evaluationCycle->periodStart()->toDateString();
        $data['deadline'] = $draft->document->evaluationCycle->periodEnd()->toDateString();
        $draft->document->evaluationCycle->validateSchedule($data);
        set_time_limit(180);
        try {
            $briefings = $ai->briefings($data, $allocations);
        } catch (\RuntimeException $exception) {
            return back()->withInput()->withErrors(['analysis' => $exception->getMessage()]);
        }
        $targetId = DB::transaction(function () use ($request, $draft, $data, $allocations, $briefings, $employeeAllocations) {
            $locked = TargetDocumentDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($locked->office_target_id) {
                return $locked->office_target_id;
            }
            $cycle = EvaluationCycle::whereKey($draft->document->evaluation_cycle_id)->lockForUpdate()->firstOrFail();
            if ($cycle->status !== EvaluationCycle::STATUS_OPEN) {
                throw ValidationException::withMessages(['period' => 'The evaluation period has closed.']);
            }
            $data['start_date'] = $cycle->periodStart()->toDateString();
            $data['deadline'] = $cycle->periodEnd()->toDateString();
            $cycle->validateSchedule($data);
            $target = OfficeTarget::create([
                ...$data, 'created_by' => $request->user()->id, 'evaluation_cycle_id' => $cycle->id,
                'description' => $draft->proposal['source_excerpt'],
            ]);
            $pivots = [];
            foreach ($allocations as $officeId => $allocation) {
                $pivots[$officeId] = ['allocated_target_value' => $allocation, 'ai_briefing' => json_encode($briefings[$officeId], JSON_THROW_ON_ERROR)];
            }
            $target->offices()->sync($pivots);
            app(\App\Services\TargetHierarchy::class)->linkAvailableUpcrs($target);
            $target->assignedEmployees()->sync($employeeAllocations);
            foreach ($target->assignedEmployees as $person) $person->notify(new \App\Notifications\EmployeeTargetAssignedNotification($target, $request->user()->name));
            $locked->update(['office_target_id' => $target->id]);
            WorkflowAudit::record($target, 'Source proposal reviewed and target assignments confirmed', ['ai_proposal' => $locked->proposal], ['target' => $target->toArray(), 'allocations' => $allocations, 'allocation_percentages' => collect($allocations)->map(fn ($quantity) => round($quantity / $target->target_value * 100, 2))->all(), 'briefings' => $briefings, 'source_document_id' => $draft->document->id]);
            $target->notifyAssignedSupervisors(array_keys($allocations));

            return $target->id;
        });

        return redirect()->route('office-targets.show', $targetId)->with('status', 'Target assigned. Office heads have been notified with their AI briefing.');
    }

    private function authorizeDocument(Request $request, TargetDocument $document): void
    {
        abort_unless($document->created_by === $request->user()->id, 403);
    }
}

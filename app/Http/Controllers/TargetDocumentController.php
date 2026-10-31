<?php

namespace App\Http\Controllers;

use App\Models\CommitmentPlan;
use App\Models\EvaluationCycle;
use App\Models\Office;
use App\Models\OfficeTarget;
use App\Models\TargetDocument;
use App\Models\TargetDocumentDraft;
use App\Services\CommitmentPlanning;
use App\Services\PlanningOrganization;
use App\Services\TargetDocumentAi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TargetDocumentController extends Controller
{
    public function index(Request $request)
    {
        $documents = $request->user()->hasRole('Administrator')
            ? TargetDocument::latest()->paginate(10)
            : TargetDocument::where('created_by', $request->user()->id)->latest()->paginate(10);

        return view('target-documents.index', [
            'documents' => $documents,
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
        $path = $file->store('target-documents', 'local');
        abort_unless($path, 500, 'The document could not be stored.');
        $mime = match (strtolower($file->getClientOriginalExtension())) {
            'pdf' => 'application/pdf', 'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
        try {
            $document = TargetDocument::create([
                'created_by' => $request->user()->id, 'evaluation_cycle_id' => $data['evaluation_cycle_id'],
                'original_name' => basename($file->getClientOriginalName()), 'path' => $path, 'mime_type' => $mime,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()->route('target-documents.show', $document);
    }

    public function show(Request $request, TargetDocument $document)
    {
        $this->authorizeDocument($request, $document);

        return view('target-documents.show', ['document' => $document->load('drafts')]);
    }

    public function download(Request $request, TargetDocument $document)
    {
        $this->authorizeDocument($request, $document);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function analyze(Request $request, TargetDocument $document, TargetDocumentAi $ai)
    {
        $this->authorizeDocument($request, $document);
        $claimed = TargetDocument::whereKey($document->id)->where(function ($query) {
            $query->whereIn('status', ['uploaded', 'failed'])->orWhere(function ($stale) {
                $stale->where('status', 'analyzing')->where('updated_at', '<', now()->subMinutes(5));
            });
        })->update(['status' => 'analyzing', 'error' => null, 'updated_at' => now()]);
        if (! $claimed) {
            return back()->withErrors(['analysis' => 'This document is already being analyzed or has results ready for review.']);
        }
        set_time_limit(180);
        try {
            $result = $ai->analyze($document);
            DB::transaction(function () use ($document, $result) {
                foreach ($result['targets'] as $proposal) {
                    $document->drafts()->create(['proposal' => $proposal]);
                }
                $document->update(['status' => 'review', 'summary' => $result['summary']."\n\n".($result['warnings'] ?? ''), 'error' => null]);
            });
        } catch (\RuntimeException $exception) {
            $document->update(['status' => 'failed', 'error' => $exception->getMessage()]);
        }

        return redirect()->route('target-documents.show', $document);
    }

    public function review(Request $request, TargetDocumentDraft $draft)
    {
        $this->authorizeDocument($request, $draft->document);
        if ($draft->document->evaluationCycle?->planning_enabled) {
            return view('planning.import', ['draft' => $draft, 'plans' => CommitmentPlan::where('evaluation_cycle_id', $draft->document->evaluation_cycle_id)->where('level', 'opcr')->get()->filter(fn ($p) => app(PlanningOrganization::class)->canPrepare($request->user(), $p))]);
        }

        return view('target-documents.review', [
            'draft' => $draft,
            'offices' => Office::where('is_active', true)->where('campus', config('hinunangan.campus_id'))->with('targetHeads')->orderBy('name')->get(),
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
            'deadline' => ['required', 'date_format:Y-m-d'],
            'priority' => ['required', Rule::in(OfficeTarget::PRIORITIES)],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'office_ids' => ['required', 'array', 'min:1', 'max:20'],
            'office_ids.*' => ['integer', 'distinct', Rule::exists('offices', 'id')->where('is_active', true)->where('campus', config('hinunangan.campus_id'))],
            'allocations' => ['required', 'array', 'max:20'],
            'allocations.*' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
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
        unset($data['office_ids'], $data['allocations'], $data['confirmed']);
        if (! EvaluationCycle::whereKey($draft->document->evaluation_cycle_id)->where('status', EvaluationCycle::STATUS_OPEN)->exists()) {
            throw ValidationException::withMessages(['period' => 'The evaluation period is closed. Ask the administrator to reopen it before assignment.']);
        }
        set_time_limit(180);
        try {
            $briefings = $ai->briefings($data, $allocations);
        } catch (\RuntimeException $exception) {
            return back()->withInput()->withErrors(['analysis' => $exception->getMessage()]);
        }
        $targetId = DB::transaction(function () use ($request, $draft, $data, $allocations, $briefings) {
            $locked = TargetDocumentDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($locked->office_target_id) {
                return $locked->office_target_id;
            }
            $cycle = EvaluationCycle::whereKey($draft->document->evaluation_cycle_id)->lockForUpdate()->firstOrFail();
            if ($cycle->status !== EvaluationCycle::STATUS_OPEN) {
                throw ValidationException::withMessages(['period' => 'The evaluation period has closed.']);
            }
            $target = OfficeTarget::create([
                ...$data, 'created_by' => $request->user()->id, 'evaluation_cycle_id' => $cycle->id,
                'description' => $draft->proposal['source_excerpt'],
            ]);
            $pivots = [];
            foreach ($allocations as $officeId => $allocation) {
                $pivots[$officeId] = ['allocated_target_value' => $allocation, 'ai_briefing' => json_encode($briefings[$officeId], JSON_THROW_ON_ERROR)];
            }
            $target->offices()->sync($pivots);
            $locked->update(['office_target_id' => $target->id]);
            $target->notifyAssignedSupervisors(array_keys($allocations));

            return $target->id;
        });

        return redirect()->route('office-targets.show', $targetId)->with('status', 'Target assigned. Office heads have been notified with their AI briefing.');
    }

    private function authorizeDocument(Request $request, TargetDocument $document): void
    {
        abort_unless($request->user()->hasRole('Administrator') || $document->created_by === $request->user()->id, 403);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\EvidenceAnalysis;
use App\Models\Mov;
use App\Models\PlanningOutput;
use App\Models\TargetMov;
use App\Models\WorkflowAudit;
use App\Services\EvidenceAi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvidenceAnalysisController extends Controller
{
    private function evidence(Request $request, string $type, int $id): Mov|TargetMov|PlanningOutput
    {
        $model = match ($type) { 'mov' => Mov::class, 'target-mov' => TargetMov::class, 'planning-output' => PlanningOutput::class, default => abort(404) };
        $evidence = $model::findOrFail($id);
        abort_unless((int) ($evidence instanceof Mov ? $evidence->uploaded_by : ($evidence instanceof PlanningOutput ? $evidence->submitted_by : $evidence->user_id)) === (int) $request->user()->id, 403);
        return $evidence;
    }

    public function analyze(Request $request, string $type, int $id)
    {
        $evidence = $this->evidence($request, $type, $id);
        app(\App\Services\EvidenceProcessing::class)->queue($evidence);
        return back()->with('mov_status', 'MOV saved for background processing. Results will appear here when ready.');
    }

    public function status(Request $request, string $type, int $id)
    {
        $evidence = $this->evidence($request, $type, $id);
        $analysis = $evidence->analysis;
        $running = $analysis?->isProcessing();
        $terminal = ! $running && in_array($analysis?->status, ['completed', 'failed'], true);
        $feedback = $running ? $analysis->processingFeedback() : [];
        return response()->json([...$feedback, 'status' => $running ? $feedback['status'] : ($analysis?->status ?? 'not-analyzed'),
            'html' => $terminal ? view('components.evidence-analysis', compact('evidence'))->render() : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $type, int $id)
    {
        $evidence = $this->evidence($request, $type, $id);
        $data = $request->validate(['statement' => ['required', 'string', 'max:5000']]);
        DB::transaction(function () use ($evidence, $data) {
            $analysis = $evidence->analysis()->lockForUpdate()->firstOrFail();
            abort_unless($analysis->isReady() && ($analysis->findings['supported'] ?? false) && empty($analysis->findings['followup_questions']), 422, 'Resolve missing details and analyze supported evidence before editing its report statement.');
            $before = ['statement' => $analysis->statement];
            $analysis->update($data);
            WorkflowAudit::record($evidence, 'Report statement edited by uploader', $before, $data);
        });
        return back()->with('mov_status', 'Report statement saved.');
    }

    public function answers(Request $request, string $type, int $id)
    {
        $evidence = $this->evidence($request, $type, $id);
        abort_if($evidence instanceof PlanningOutput, 404);
        DB::transaction(function () use ($request, $evidence) {
            $files = app(\App\Services\EvidenceFiles::class)->group($evidence);
            $locked = $evidence->newQuery()->whereIn('id', $files->modelKeys())->orderBy('id')->lockForUpdate()->get();
            $analyses = EvidenceAnalysis::where('evidence_type', $evidence->getMorphClass())->whereIn('evidence_id', $locked->modelKeys())->orderBy('id')->lockForUpdate()->get();
            $analysis = $evidence->fresh()->analysis;
            abort_unless($analysis?->isReady() && ! $analyses->contains(fn ($a) => $a->isProcessing()), 422, 'Wait for MOV analysis to finish before answering.');
            $context = $evidence->fresh()->submission_context;
            $questions = $analysis->findings['followup_questions'] ?? [];
            abort_unless(($context['flow'] ?? null) === 'files-first' && $questions, 422, 'No pending questions for this MOV.');
            $snapshot = app(\App\Services\MovQuestions::class)->snapshot($request->user(), $context['target_snapshot']['target_ref']);
            abort_unless($snapshot === $context['target_snapshot'], 422, 'The target changed. Upload evidence for the current target.');
            $request->validate(['analysis_version' => ['required', 'string']]);
            abort_unless(hash_equals(hash('sha256', json_encode($questions)), $request->input('analysis_version')), 422, 'The questions changed. Refresh and answer the latest questions.');
            $rules = ['answers' => ['required', 'array:'.implode(',', array_column($questions, 'id'))]];
            foreach ($questions as $question) $rules['answers.'.$question['id']] = [$question['required'] ? 'required' : 'nullable', ...match ($question['type']) {
                'number' => ['numeric', 'min:0', 'max:9999999999.99'], 'date' => ['date_format:Y-m-d'], default => ['string', 'max:2000'],
            }];
            $answers = $request->validate($rules)['answers'];
            if ($context['questions']) $context['followup_history'][] = ['questions' => $context['questions'], 'answers' => $context['answers']];
            $context['questions'] = $questions;
            $context['answers'] = $answers;
            foreach ($locked as $file) {
                $file->update(['submission_context' => $context]);
                $file->analysis()->update(['status' => 'completed', 'statement' => null, 'findings' => ['satisfies_target' => false, 'followup_questions' => []]]);
            }
            WorkflowAudit::record($evidence, 'MOV missing details answered', null, ['questions' => $questions, 'answers' => $answers]);
        });
        app(\App\Services\EvidenceProcessing::class)->queue($evidence->fresh());
        return back()->with('mov_status', 'Answers saved. AI will recheck them against your original files and prepare the summary.');
    }
}

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
        return response()->json(['status' => $running ? ($analysis?->status === 'queued' ? 'queued' : 'analyzing') : ($analysis?->status ?? 'not-analyzed'),
            'html' => $terminal ? view('components.evidence-analysis', compact('evidence'))->render() : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $type, int $id)
    {
        $evidence = $this->evidence($request, $type, $id);
        $data = $request->validate(['statement' => ['required', 'string', 'max:5000']]);
        DB::transaction(function () use ($evidence, $data) {
            $analysis = $evidence->analysis()->lockForUpdate()->firstOrFail();
            abort_unless($analysis->isReady() && ($analysis->findings['supported'] ?? false), 422, 'Analyze supported evidence before editing its report statement.');
            $before = ['statement' => $analysis->statement];
            $analysis->update($data);
            WorkflowAudit::record($evidence, 'Report statement edited by uploader', $before, $data);
        });
        return back()->with('mov_status', 'Report statement saved.');
    }
}

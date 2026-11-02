<?php

namespace App\Http\Middleware;

use App\Models\OfficeTarget;
use App\Models\TargetAssistance;
use App\Models\WorkflowAudit;
use App\Services\AssistanceForms;
use App\Services\TargetConflictReview;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class TargetAssistanceWorkflow
{
    public function handle(Request $r, Closure $next)
    {
        if (! $r->user() || $r->isMethod('GET') || ! config('spms.target_assistance.enabled') || ! Schema::hasTable('target_assistances') || $r->routeIs('target-assistance.*', 'api.target-assistance.*', 'target-conflicts.*', 'api.target-conflicts.*')) {
            return $next($r);
        }
        $forms = app(AssistanceForms::class);
        if ($r->filled('ai_assistance') && ! $r->attributes->get('assistanceTransaction')) {
            $r->attributes->set('assistanceTransaction', true);

            return DB::transaction(fn () => $this->handle($r, $next));
        }
        $accepted = collect();
        if ($r->filled('ai_assistance')) {
            $r->validate(['ai_assistance' => 'array|max:25', 'ai_assistance.*' => 'integer|distinct']);
            [$subject, $fields] = $forms->resolve($r->path(), $r->input(), $r->user());
            foreach ($r->input('ai_assistance') as $id) {
                $a = TargetAssistance::where('user_id', $r->user()->id)->lockForUpdate()->findOrFail($id);
                abort_unless($a->status === 'completed' && ! $a->accepted_at && $a->subject_kind === $forms->subjects->kind($subject) && $a->subject_id === $subject->id && $forms->allowed($a->field, $fields), 422, 'This suggestion cannot be applied to this record.');
                abort_unless(hash_equals($a->fingerprint, $forms->subjects->fingerprint($subject)) && $a->protected_values === $forms->protectedValues($r->input(), $subject), 422, 'The record or protected details changed. Generate a fresh suggestion.');
                abort_unless(Arr::get($r->input(), $a->field) === $a->suggestion, 422, 'The suggested wording was changed. Keep the edited text without its AI acceptance marker, or request a new suggestion.');
                $accepted->push($a);
            }
        }
        $name = preg_replace('/^api\./', '', $r->route()?->getName() ?? '');
        if ($name === 'report-workflow.approve' || preg_match('/^(opcrs|dpcrs|upcrs|ipcrs)\.approve$/', $name)) {
            $type = $r->route('type') ?? rtrim(explode('.', $name)[0], 's');
            $id = $r->route('report') ?? $r->route($type);
            $model = $id instanceof Model ? $id : $forms->subjects->resolve($type, (int) $id);
            if ($model->status !== 'approved') {
                app(TargetConflictReview::class)->assertApproval($model, $r->user());
            }
        }
        $response = $next($r);
        if ($response->getStatusCode() >= 400 || $r->session()->get('errors')?->any()) {
            return $response;
        }
        foreach ($accepted as $a) {
            $a->update(['accepted_at' => now()]);
            WorkflowAudit::record($a, 'AI wording accepted and saved', ['text' => $a->original], ['text' => $a->suggestion, 'subject_kind' => $a->subject_kind, 'subject_id' => $a->subject_id, 'field' => $a->field]);
        }
        $subject = null;
        try {
            [$subject] = $forms->resolve($r->path(), $r->input(), $r->user(), false);
        } catch (HttpExceptionInterface $e) {
            if (! in_array($e->getStatusCode(), [403, 404], true)) {
                throw $e;
            }
        }
        if ((! $subject?->exists || $name === 'target-drafts.assign') && $response->headers->has('Location')) {
            $path = trim(parse_url($response->headers->get('Location'), PHP_URL_PATH) ?? '', '/');
            if (preg_match('#^(?:api/)?(office-targets|opcrs|dpcrs|upcrs|ipcrs)/(\d+)$#', $path, $match)) {
                $subject = $forms->subjects->resolve($match[1] === 'office-targets' ? 'office-target' : rtrim($match[1], 's'), (int) $match[2]);
            }
        }
        if ($subject?->exists && ! in_array($name, ['historical-pcrs.update', 'historical-pcrs.correction', 'evidence.statement'], true)) {
            $canonical = $forms->subjects->canonical($subject);
            $publish = $canonical instanceof OfficeTarget && in_array($name, ['office-targets.store', 'office-targets.update', 'office-targets.assign-employees', 'target-drafts.assign'], true);
            DB::afterCommit(fn () => app(TargetConflictReview::class)->scan($canonical->fresh(), $r->user(), $publish, $publish));
        }

        return $response;
    }
}

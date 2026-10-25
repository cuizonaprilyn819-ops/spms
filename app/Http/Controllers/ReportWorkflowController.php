<?php

namespace App\Http\Controllers;

use App\Models\{Dpcr, Ipcr, Opcr, Upcr, User};
use App\Notifications\ReportWorkflowNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportWorkflowController extends Controller
{
    private const MODELS = ['opcr' => Opcr::class, 'dpcr' => Dpcr::class, 'upcr' => Upcr::class, 'ipcr' => Ipcr::class];

    public function edit(Request $request, string $type, int $report): View
    {
        $form = $this->report($type, $report);
        abort_unless($form->isEditableBy($request->user()->id), 403);
        return view('reports.edit', compact('form', 'type'));
    }

    public function update(Request $request, string $type, int $report): RedirectResponse
    {
        $form = $this->report($type, $report);
        abort_unless($form->isEditableBy($request->user()->id), 403);
        $form->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'success_indicators' => ['nullable', 'string', 'max:5000'],
            'targets' => ['nullable', 'string', 'max:5000'],
        ]));
        return back()->with('status', strtoupper($type).' changes saved.');
    }

    public function submit(Request $request, string $type, int $report): RedirectResponse
    {
        $form = $this->report($type, $report);
        abort_unless($form->isEditableBy($request->user()->id), 403);
        $from = $form->status;
        $to = $from === $form::STATUS_RETURNED ? $form::STATUS_RESUBMITTED : $form::STATUS_SUBMITTED;
        $this->transition($form, $to, $request->user()->id, null, [
            'submitted_at' => now(),
            'resubmission_count' => $from === $form::STATUS_RETURNED ? $form->resubmission_count + 1 : $form->resubmission_count,
        ]);
        $this->reviewers($form)->each->notify(new ReportWorkflowNotification(strtoupper($type), $form, $to));
        return back()->with('status', strtoupper($type).' submitted for review.');
    }

    public function returnForCorrection(Request $request, string $type, int $report): RedirectResponse
    {
        $form = $this->report($type, $report);
        $this->authorizeReviewer($request, $form);
        abort_unless(in_array($form->status, [$form::STATUS_SUBMITTED, $form::STATUS_RESUBMITTED], true), 422);
        $data = $request->validate(['remarks' => ['required', 'string', 'max:5000']]);
        $this->transition($form, $form::STATUS_RETURNED, $request->user()->id, $data['remarks'], ['returned_at' => now()]);
        $form->preparedBy?->notify(new ReportWorkflowNotification(strtoupper($type), $form, 'returned for correction', $data['remarks']));
        return back()->with('status', strtoupper($type).' returned to the employee for correction.');
    }

    public function approve(Request $request, string $type, int $report): RedirectResponse
    {
        $form = $this->report($type, $report);
        $this->authorizeReviewer($request, $form);
        abort_unless(in_array($form->status, [$form::STATUS_SUBMITTED, $form::STATUS_RESUBMITTED], true), 422);
        $ratings = [];
        if ($form instanceof Ipcr && $form->office_target_id) {
            abort_if((int) $form->employee_id === (int) $request->user()->id, 403, 'You cannot rate your own IPCR.');
            $ratings = $request->validate([
                'quality_rating' => ['required', 'numeric', 'min:1', 'max:5'],
                'efficiency_rating' => ['required', 'numeric', 'min:1', 'max:5'],
                'timeliness_rating' => ['required', 'numeric', 'min:1', 'max:5'],
                'rating_remarks' => ['nullable', 'string', 'max:5000'],
            ]);
            $ratings['average_rating'] = round(((float) $ratings['quality_rating'] + (float) $ratings['efficiency_rating'] + (float) $ratings['timeliness_rating']) / 3, 2);
        }
        $this->transition($form, $form::STATUS_APPROVED, $request->user()->id, null, ['approved_by' => $request->user()->id, 'approved_at' => now(), ...$ratings]);
        $form->preparedBy?->notify(new ReportWorkflowNotification(strtoupper($type), $form, 'approved'));
        if ($form instanceof Ipcr && (int) $form->employee_id !== (int) $form->prepared_by) {
            $form->employee?->notify(new ReportWorkflowNotification('IPCR', $form, 'approved'));
        }
        return back()->with('status', strtoupper($type).' approved successfully.');
    }

    private function report(string $type, int $id): Model
    {
        abort_unless(isset(self::MODELS[$type]), 404);
        return self::MODELS[$type]::with(['preparedBy', 'statusHistories.actor'])->findOrFail($id);
    }

    private function transition(Model $form, string $to, int $actor, ?string $remarks, array $extra = []): void
    {
        DB::transaction(function () use ($form, $to, $actor, $remarks, $extra): void {
            $from = $form->status;
            $form->update(['status' => $to, ...$extra]);
            $form->statusHistories()->create(['from_status' => $from, 'to_status' => $to, 'remarks' => $remarks, 'acted_by' => $actor]);
        });
    }

    private function authorizeReviewer(Request $request, Model $form): void
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['Administrator', 'HR Head', 'Campus Director', 'Supervisor']), 403);
        if ($user->hasRole('Supervisor')) abort_unless($user->office_id && (int) $user->office_id === (int) $this->officeId($form), 403);
        if ($user->hasRole('Campus Director') && $user->office?->campus) {
            abort_unless($form->preparedBy?->office?->campus === $user->office->campus, 403);
        }
    }

    private function reviewers(Model $form)
    {
        $officeId = $this->officeId($form);
        $campus = $form->preparedBy?->office?->campus;
        return User::role(['Supervisor', 'Campus Director', 'HR Head'])->where(function ($query) use ($officeId, $campus): void {
            $query->where(fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'Supervisor'))->where('office_id', $officeId));
            if ($campus) $query->orWhere(fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', 'Campus Director'))->whereHas('office', fn ($o) => $o->where('campus', $campus)));
            $query->orWhereHas('roles', fn ($r) => $r->where('name', 'HR Head'));
        })->get();
    }

    private function officeId(Model $form): ?int
    {
        return match (true) {
            $form instanceof Opcr => $form->office_id,
            $form instanceof Dpcr => $form->employee?->office_id,
            $form instanceof Upcr => $form->employee?->office_id,
            $form instanceof Ipcr => $form->employee?->office_id,
        };
    }
}

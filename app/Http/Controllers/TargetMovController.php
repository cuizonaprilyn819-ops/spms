<?php

namespace App\Http\Controllers;

use App\Models\OfficeTarget;
use App\Models\TargetMov;
use App\Models\User;
use App\Notifications\TargetCompletedNotification;
use App\Notifications\TargetMovSubmittedNotification;
use App\Services\CommitmentPlanning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TargetMovController extends Controller
{
    public function complete(Request $request, OfficeTarget $officeTarget)
    {
        CommitmentPlanning::legacyOnly($officeTarget->evaluationCycle);
        $user = $request->user();
        abort_unless($user->office_id && $officeTarget->offices()->whereKey($user->office_id)->exists(), 403);
        DB::transaction(function () use ($user, $officeTarget) {
            $assignment = DB::table('office_target_employee')->where('office_target_id', $officeTarget->id)->where('user_id', $user->id)->lockForUpdate()->first();
            abort_unless($assignment && $assignment->accepted_at, 403);
            if ($assignment->completed_at) {
                return;
            }
            abort_unless(TargetMov::where('office_target_id', $officeTarget->id)->where('user_id', $user->id)->exists(), 422, 'Submit MOV before marking this target complete.');
            DB::table('office_target_employee')->where('id', $assignment->id)->update(['completed_at' => now(), 'completed_value' => $assignment->completed_value ?? $assignment->allocated_target_value, 'updated_at' => now()]);
            $recipients = User::where('office_id', $user->office_id)->whereHas('roles', fn ($q) => $q->whereIn('name', ['Supervisor', 'Division Head', 'Unit Head', 'HR Head']))->get();
            if ($officeTarget->createdBy?->hasAnyRole(['Campus Director', 'Planning Officer'])) {
                $recipients->push($officeTarget->createdBy);
            }
            $recipients->unique('id')->each(fn ($recipient) => $recipient->notify(new TargetCompletedNotification($officeTarget, $user->name)));
        });

        return back()->with('mov_status', 'Your target is marked complete. Completion notifications have been sent.');
    }

    public function store(Request $request, OfficeTarget $officeTarget)
    {
        CommitmentPlanning::legacyOnly($officeTarget->evaluationCycle);
        $user = $request->user();
        abort_unless($user->office_id
            && $officeTarget->assignedEmployees()->whereKey($user->id)->wherePivotNotNull('accepted_at')->exists()
            && $officeTarget->offices()->whereKey($user->office_id)->exists(), 403);
        $data = $request->validate([
            'accomplishment' => ['required', 'string', 'max:5000'],
            'completed_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'files' => ['required', 'array', 'min:1', 'max:'.\App\Support\MovUpload::MAX_FILES],
            'files.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);
        \App\Support\MovUpload::checkTotal($request->file('files'));
        $context = app(\App\Services\MovQuestions::class)->submission($request, 'office:'.$officeTarget->id);
        if ($context && ($context['flow'] ?? null) !== 'files-first' && ! $officeTarget->assignedEmployees()->whereKey($user->id)->first()->pivot->completed_at) {
            $request->validate(['completed_value' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2']]);
        }
        if ($context && isset($data['completed_value'])) $context['reported_completed_value'] = $data['completed_value'];
        $paths = [];
        $uploaded = [];
        try {
            DB::transaction(function () use ($officeTarget, $user, $data, $request, $context, &$paths, &$uploaded) {
                OfficeTarget::whereKey($officeTarget->id)->lockForUpdate()->firstOrFail();
                $assignment = DB::table('office_target_employee')->where('office_target_id', $officeTarget->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
                if (isset($data['completed_value'])) {
                    if ($assignment->allocated_target_value === null) {
                        throw ValidationException::withMessages(['completed_value' => 'Ask your head to set an allocation before reporting completion.']);
                    }
                    if ($assignment->completed_at) {
                        throw ValidationException::withMessages(['completed_value' => 'This assignment is already marked complete.']);
                    }
                    DB::table('office_target_employee')->where('id', $assignment->id)->update(['completed_value' => $data['completed_value'], 'updated_at' => now()]);
                }
                foreach ($request->file('files') as $file) {
                    $path = $file->store('target-movs', 'local');
                    abort_unless($path, 500, 'Unable to store the file. Please try again.');
                    $paths[] = $path;
                    $mov = TargetMov::create(['office_target_id' => $officeTarget->id, 'user_id' => $user->id,
                        'office_id' => $user->office_id, 'accomplishment' => $data['accomplishment'],
                        'path' => $path, 'original_name' => $file->getClientOriginalName(), 'submission_context' => $context]);
                }
                $uploaded = TargetMov::whereIn('path', $paths)->get()->all();
                $recipients = app(\App\Services\TargetHierarchy::class)->reportingHeads([$user->office_id]);
                $recipients->push($user);
                if ($officeTarget->createdBy?->hasAnyRole(['Campus Director', 'Planning Officer'])) {
                    $recipients->push($officeTarget->createdBy);
                }
                $movIds = array_map(fn ($file) => $file->id, $uploaded);
                $recipients->unique('id')->each(fn ($recipient) => $recipient->notify(new TargetMovSubmittedNotification($mov, $movIds)));
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        foreach ($context ? [$uploaded[0]] : $uploaded as $evidence) {
            app(\App\Services\EvidenceProcessing::class)->queue($evidence, (bool) config('spms.ai.enabled'));
        }

        return back()->with('mov_status', 'MOV submitted successfully. Your office heads and assigning Campus Director have been notified.');
    }

    public function download(Request $request, TargetMov $targetMov)
    {
        $this->authorizeFile($request, $targetMov);

        return Storage::disk('local')->download($targetMov->path, $targetMov->original_name);
    }

    public function preview(Request $request, TargetMov $targetMov)
    {
        $this->authorizeFile($request, $targetMov);
        $preview = app(\App\Services\EvidenceFiles::class)->previewPath($targetMov);
        if ($preview) return Storage::disk('local')->response($preview, 'preview.webp', ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        $mime = Storage::disk('local')->mimeType($targetMov->path);
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true), 415, 'Preview is available for PDF and images only.');

        return Storage::disk('local')->response($targetMov->path, $targetMov->original_name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    private function authorizeFile(Request $request, TargetMov $targetMov): void
    {
        $user = $request->user();
        $managed = app(\App\Services\TargetHierarchy::class)->managedOfficeIds($user);
        $canReview = in_array((int) $targetMov->office_id, $managed)
            && $targetMov->target->offices()->whereKey($targetMov->office_id)->exists();
        $isAssigningDirector = $user->hasAnyRole(['Campus Director', 'Planning Officer']) && (int) $targetMov->target->created_by === (int) $user->id;
        abort_unless((int) $targetMov->user_id === (int) $user->id || $canReview || $isAssigningDirector, 403);
        abort_unless(Storage::disk('local')->exists($targetMov->path), 404);
    }
}

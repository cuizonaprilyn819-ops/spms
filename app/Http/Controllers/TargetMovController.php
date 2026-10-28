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
        abort_unless($user->hasAnyRole(['Employee', 'Supervisor', 'HR Head']) && $user->office_id && $officeTarget->offices()->whereKey($user->office_id)->exists(), 403);
        DB::transaction(function () use ($user, $officeTarget) {
            $assignment = DB::table('office_target_employee')->where('office_target_id', $officeTarget->id)->where('user_id', $user->id)->lockForUpdate()->first();
            abort_unless($assignment && $assignment->accepted_at, 403);
            if ($assignment->completed_at) {
                return;
            }
            abort_unless(TargetMov::where('office_target_id', $officeTarget->id)->where('user_id', $user->id)->exists(), 422, 'Submit MOV before marking this target complete.');
            DB::table('office_target_employee')->where('id', $assignment->id)->update(['completed_at' => now(), 'completed_value' => $assignment->allocated_target_value, 'updated_at' => now()]);
            $recipients = User::where('office_id', $user->office_id)->whereHas('roles', fn ($q) => $q->whereIn('name', ['Supervisor', 'HR Head']))->get();
            if ($officeTarget->createdBy?->hasRole('Campus Director')) {
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
        abort_unless($user->hasAnyRole(['Employee', 'Supervisor', 'HR Head']) && $user->office_id
            && $officeTarget->assignedEmployees()->whereKey($user->id)->wherePivotNotNull('accepted_at')->exists()
            && $officeTarget->offices()->whereKey($user->office_id)->exists(), 403);
        $data = $request->validate([
            'accomplishment' => ['required', 'string', 'max:5000'],
            'completed_value' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
        $paths = [];
        try {
            DB::transaction(function () use ($officeTarget, $user, $data, $request, &$paths) {
                OfficeTarget::whereKey($officeTarget->id)->lockForUpdate()->firstOrFail();
                $assignment = DB::table('office_target_employee')->where('office_target_id', $officeTarget->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
                if (isset($data['completed_value'])) {
                    if ($assignment->allocated_target_value === null || (float) $data['completed_value'] > (float) $assignment->allocated_target_value) {
                        throw ValidationException::withMessages(['completed_value' => 'Completed quantity cannot exceed your assigned allocation. Ask your head to set an allocation if it is missing.']);
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
                        'path' => $path, 'original_name' => $file->getClientOriginalName()]);
                }
                $recipients = User::where('office_id', $user->office_id)
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Supervisor', 'HR Head']))
                    ->get();
                if ($officeTarget->createdBy?->hasRole('Campus Director')) {
                    $recipients->push($officeTarget->createdBy);
                }
                $recipients->unique('id')->each(fn ($recipient) => $recipient->notify(new TargetMovSubmittedNotification($mov)));
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
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
        $mime = Storage::disk('local')->mimeType($targetMov->path);
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true), 415, 'Preview is available for PDF and images only.');

        return Storage::disk('local')->response($targetMov->path, $targetMov->original_name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    private function authorizeFile(Request $request, TargetMov $targetMov): void
    {
        $user = $request->user();
        $canReview = $user->hasAnyRole(['Supervisor', 'HR Head']) && $user->office_id
            && (int) $user->office_id === (int) $targetMov->office_id
            && $targetMov->target->offices()->whereKey($user->office_id)->exists();
        $isAssigningDirector = $user->hasRole('Campus Director') && (int) $targetMov->target->created_by === (int) $user->id;
        abort_unless((int) $targetMov->user_id === (int) $user->id || $canReview || $isAssigningDirector, 403);
        abort_unless(Storage::disk('local')->exists($targetMov->path), 404);
    }
}

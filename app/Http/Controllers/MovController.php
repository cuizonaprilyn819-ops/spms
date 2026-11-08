<?php

namespace App\Http\Controllers;

use App\Models\Mov;
use App\Models\PerformanceTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovController extends Controller
{
    public function index(): View
    {
        $user = request()->user();
        $movs = \App\Models\TargetMov::with(['employee', 'target'])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id);
                if ($user->hasAnyRole(['Supervisor', 'Division Head', 'Unit Head', 'HR Head']) && $user->office_id) {
                    $query->orWhere(fn ($q) => $q->where('office_id', $user->office_id)
                        ->whereHas('target.offices', fn ($offices) => $offices->whereKey($user->office_id)));
                }
                if ($user->hasRole('Campus Director')) {
                    $query->orWhereHas('target', fn ($target) => $target->where('created_by', $user->id));
                }
            })
            ->latest()
            ->paginate(10);

        $reportMovs = Mov::with(['performanceTarget', 'uploadedBy'])
            ->where('uploaded_by', $user->id)
            ->when(request()->filled('performance_target_id'), fn ($query) => $query->where('performance_target_id', request()->integer('performance_target_id')))
            ->latest()->paginate(10, ['*'], 'report_page')->withQueryString();

        return view('movs.index', compact('movs', 'reportMovs'));
    }

    public function create(): View|RedirectResponse
    {
        $user = request()->user();
        try {
            $token = request()->validate(['questionnaire_token' => ['nullable', 'uuid']])['questionnaire_token'] ?? null;
            $questionnaire = $token ? app(\App\Services\MovQuestions::class)->questionnaire(request(), $token) : null;
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()->route('movs.create')->withErrors(['questions' => 'Prepare fresh AI questions for your target before uploading MOV.']);
        }
        $progress = null;
        if ($questionnaire && str_starts_with($questionnaire['target_ref'], 'office:')) {
            $assignment = \App\Models\OfficeTarget::find(substr($questionnaire['target_ref'], 7))?->assignedEmployees()->whereKey($user->id)->first()?->pivot;
            if ($assignment && ! $assignment->completed_at) $progress = $assignment;
        }
        return view('movs.create', [
            'progress' => $progress,
            'questionnaire' => $questionnaire,
            'questionnaireToken' => $token,
            'targets' => $this->uploadableReportTargets()->with('targetable')->latest()->get(),
            'officeTargets' => \App\Models\OfficeTarget::whereNull('performance_target_id')->whereHas('assignedEmployees', fn ($q) => $q->whereKey($user->id)->whereNotNull('office_target_employee.accepted_at'))
                ->whereHas('offices', fn ($q) => $q->whereKey($user->office_id ?? 0))->with('evaluationCycle')->latest()->get(),
        ]);
    }

    public function questions(Request $request, \App\Services\MovQuestions $questions): RedirectResponse
    {
        $data = $request->validate(['target_ref' => ['required', 'string', 'regex:/^(office|report):[1-9][0-9]*$/']]);
        try {
            $token = $questions->prepare($request, $data['target_ref']);
        } catch (\Throwable $exception) {
            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) throw $exception;
            throw \Illuminate\Validation\ValidationException::withMessages([
                'questions' => $exception instanceof \RuntimeException ? $exception->getMessage() : 'AI could not prepare questions. Please retry.',
            ]);
        }
        return redirect()->route('movs.create', ['questionnaire_token' => $token]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['mov_flow' => ['nullable', 'in:files-first']]);
        if ($request->input('mov_flow') === 'files-first') {
            $reference = $request->validate(['target_ref' => ['required', 'string', 'regex:/^(office|report):[1-9][0-9]*$/']])['target_ref'];
            $snapshot = app(\App\Services\MovQuestions::class)->snapshot($request->user(), $reference);
            $request->merge(['title' => \Illuminate\Support\Str::limit('Evidence for: '.$snapshot['title'], 255, ''), 'description' => 'Uploaded evidence awaiting analysis.']);
        }
        $data = $request->validate([
            'target_ref' => ['nullable', 'string', 'regex:/^(office|report):[1-9][0-9]*$/'],
            'performance_target_id' => ['required_without:target_ref', 'nullable', 'integer', 'exists:performance_targets,id'],
            'title' => ['required_without:questionnaire_token', 'nullable', 'string', 'max:255'],
            'questionnaire_token' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['required_without:files', 'nullable', 'prohibits:files', 'file', 'mimes:pdf,docx,xlsx,jpg,jpeg,png,webp', 'max:10240'],
            'files' => ['required_without:file', 'nullable', 'prohibits:file', 'array', 'min:1', 'max:'.\App\Support\MovUpload::MAX_FILES],
            'files.*' => ['required', 'file', 'mimes:pdf,docx,xlsx,jpg,jpeg,png,webp', 'max:10240'],
        ]);
        $files = $request->file('files') ?: [$request->file('file')];
        \App\Support\MovUpload::checkTotal($files);
        if ($request->filled('questionnaire_token')) {
            $questionnaire = app(\App\Services\MovQuestions::class)->questionnaire($request, $request->input('questionnaire_token'));
            $data['title'] = $data['title'] ?? $questionnaire['mov_title'] ?? \Illuminate\Support\Str::limit('Evidence for: '.$questionnaire['target_snapshot']['title'], 255, '');
            $data['description'] = $data['description'] ?? $questionnaire['mov_description'] ?? \Illuminate\Support\Str::limit('Supporting files for: '.$questionnaire['target_snapshot']['success_indicator'], 2000, '');
        }

        if ($request->filled('target_ref')) {
            [$kind, $id] = explode(':', $data['target_ref']);
            if ($kind === 'office') {
                $target = \App\Models\OfficeTarget::whereNull('performance_target_id')->findOrFail($id);
                // Reuse assignment acceptance, office scoping, file storage and AI analysis.
                $request->merge(['accomplishment' => trim($data['title']."\n".($data['description'] ?? ''))]);
                $request->files->set('files', $files);
                app(TargetMovController::class)->store(Request::createFrom($request), $target);
                if ($request->filled('questionnaire_token') || $request->input('mov_flow') === 'files-first') return redirect()->to(route('office-targets.show', $target).'#target-movs')->with('mov_status', 'Files saved. AI will analyze them and ask only for missing details.');
                return redirect()->route('movs.index')->with('status', 'MOV uploaded for '.$target->title.'. Review the AI target assessment from the target.');
            }
            $data['performance_target_id'] = (int) $id;
        }
        abort_unless($this->uploadableReportTargets()->whereKey($data['performance_target_id'])->exists(), 403, 'Choose a target from your own reports.');
        $context = app(\App\Services\MovQuestions::class)->submission($request, 'report:'.$data['performance_target_id']);

        $paths = [];
        try {
            $uploaded = \Illuminate\Support\Facades\DB::transaction(function () use ($files, $data, $request, $context, &$paths) {
                $uploaded = [];
                foreach ($files as $file) {
                    $path = $file->store('movs', 'public');
                    abort_unless($path, 500, 'Unable to save the MOV file. Please retry.');
                    $paths[] = $path;
                    $uploaded[] = Mov::create([
                        'performance_target_id' => $data['performance_target_id'],
                        'title' => $data['title'],
                        'description' => $data['description'] ?? null,
                        'file_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'extension' => strtolower($file->getClientOriginalExtension()),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => $request->user()->id,
                        'submission_context' => $context,
                    ]);
                }
                return $uploaded;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($paths);
            throw $exception;
        }

        foreach ($context ? [$uploaded[0]] : $uploaded as $mov) {
            app(\App\Services\EvidenceProcessing::class)->queue($mov, (bool) config('spms.ai.enabled'));
        }

        return redirect()
            ->route($context || count($uploaded) === 1 ? 'movs.show' : 'movs.index', $context || count($uploaded) === 1 ? $uploaded[0] : [])
            ->with('status', count($uploaded).' MOV file(s) uploaded for the selected target.');
    }

    public function show(Mov $mov): View
    {
        $this->authorizeEvidence($mov);
        return view('movs.show', [
            'mov' => $mov->load(['uploadedBy', 'performanceTarget']),
        ]);
    }

    public function download(Mov $mov): StreamedResponse
    {
        $this->authorizeEvidence($mov);
        return Storage::disk('public')->download($mov->file_path, $mov->original_name);
    }

    public function preview(Mov $mov)
    {
        $this->authorizeEvidence($mov);
        $path = app(\App\Services\EvidenceFiles::class)->previewPath($mov);
        if ($path) return Storage::disk('local')->response($path, 'preview.webp', ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        abort_unless(in_array(strtolower(pathinfo($mov->original_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true), 415);
        return Storage::disk('public')->response($mov->file_path, $mov->original_name, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function uploadableReportTargets()
    {
        return app(\App\Services\MovQuestions::class)->reportTargets(request()->user());
    }

    private function authorizeEvidence(Mov $mov): void
    {
        $user = request()->user();
        if ((int) $mov->uploaded_by === (int) $user->id || $user->hasAnyRole(['Administrator', 'Planning Officer'])) return;
        $managed = app(\App\Services\TargetHierarchy::class)->managedOfficeIds($user);
        abort_unless($mov->uploadedBy?->office_id && in_array((int) $mov->uploadedBy->office_id, $managed), 403);
    }
}

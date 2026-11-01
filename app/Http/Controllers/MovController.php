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
                if ($user->hasAnyRole(['Supervisor', 'HR Head']) && $user->office_id) {
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

    public function create(): View
    {
        return view('movs.create', ['targets' => PerformanceTarget::with('targetable')->latest()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'performance_target_id' => ['nullable', 'integer', 'exists:performance_targets,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'mimes:pdf,docx,xlsx,jpg,jpeg', 'max:10240'],
        ]);

        $file = $data['file'];
        $path = $file->store('movs', 'public');

        $mov = Mov::create([
            'performance_target_id' => $data['performance_target_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'extension' => strtolower($file->getClientOriginalExtension()),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        if (config('spms.ai.enabled')) {
            set_time_limit(150);
            app(\App\Services\EvidenceAi::class)->analyze($mov);
        }

        return redirect()
            ->route('movs.show', $mov)
            ->with('status', 'MOV uploaded successfully.');
    }

    public function show(Mov $mov): View
    {
        return view('movs.show', [
            'mov' => $mov->load(['uploadedBy', 'performanceTarget']),
        ]);
    }

    public function download(Mov $mov): StreamedResponse
    {
        return Storage::disk('public')->download($mov->file_path, $mov->original_name);
    }
}

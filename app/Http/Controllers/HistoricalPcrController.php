<?php

namespace App\Http\Controllers;

use App\Models\EvaluationCycle;
use App\Models\HistoricalPcr;
use App\Models\User;
use App\Services\HistoricalPcrArchive;
use App\Services\HistoricalPcrExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\ExceptionInterface;

class HistoricalPcrController extends Controller
{
    public function index(Request $request)
    {
        $records = HistoricalPcr::visibleTo($request->user())->with(['cycle', 'revision', 'owner'])->latest()->get();

        return view('historical-pcrs.index', compact('records'));
    }

    public function create()
    {
        Gate::authorize('create', HistoricalPcr::class);

        return view('historical-pcrs.create', ['people' => User::orderBy('name')->get(), 'cycles' => EvaluationCycle::orderBy('year')->orderBy('semester')->get()]);
    }

    public function store(Request $request, HistoricalPcrArchive $archive)
    {
        Gate::authorize('create', HistoricalPcr::class);
        $data = $request->validate([
            'source' => 'required|file|mimes:pdf|max:25600',
            'owner_id' => 'required|integer|exists:users,id', 'evaluation_cycle_id' => 'required|integer|exists:evaluation_cycles,id',
            'type' => ['required', Rule::in(array_keys(HistoricalPcrExport::TITLES))],
            'form_pages' => 'required|integer|min:1|max:100', 'office' => 'nullable|string|max:500', 'owner_position' => 'nullable|string|max:500',
            'participants' => 'nullable|array|max:30', 'participants.*.user_id' => 'nullable|integer|distinct|exists:users,id',
            'participants.*.source_role' => 'nullable|string|max:255',
        ]);
        $participants = [];
        foreach ($data['participants'] ?? [] as $participant) {
            if (empty($participant['user_id'])) {
                continue;
            }
            if (empty($participant['source_role'])) {
                throw ValidationException::withMessages(['participants' => 'Give every named reviewer their role on the source form.']);
            }
            $person = User::findOrFail($participant['user_id']);
            $participants[] = ['user_id' => $person->id, 'source_name' => $person->name, 'source_role' => $participant['source_role']];
        }
        $snapshot = ['office' => $data['office'] ?? '', 'owner_position' => $data['owner_position'] ?? '',
            'form_date' => '', 'reported_overall' => '', 'adjectival_rating' => '',
            'notes' => ['Source transcription has not been completed.'], 'rows' => [], 'page_text' => [],
            'signatories' => array_map(fn ($p) => ['name' => $p['source_name'], 'role' => $p['source_role'], 'page' => 1, 'date' => ''], $participants)];
        try {
            $pcr = $archive->import($request->file('source')->getRealPath(), $request->file('source')->getClientOriginalName(), $data, $snapshot, $participants, $request->user());
        } catch (ExceptionInterface $exception) {
            report($exception);
            throw ValidationException::withMessages(['source' => 'The PDF renderer could not finish. Check the configured renderer and try again. No report was created.']);
        }

        return redirect()->route('historical-pcrs.edit', $pcr)->with('status', 'Original PDF archived. Complete and review its transcription.');
    }

    public function show(HistoricalPcr $historicalPcr, Request $request, HistoricalPcrExport $export)
    {
        Gate::authorize('view', $historicalPcr);
        $revision = $request->filled('revision') ? $historicalPcr->revisions()->findOrFail($request->integer('revision')) : $historicalPcr->revision;

        return view('historical-pcrs.show', $export->data($historicalPcr, $revision));
    }

    public function edit(HistoricalPcr $historicalPcr)
    {
        Gate::authorize('update', $historicalPcr);

        return view('historical-pcrs.edit', ['pcr' => $historicalPcr->load('revision'), 'snapshot' => $historicalPcr->revision->snapshot]);
    }

    public function update(HistoricalPcr $historicalPcr, Request $request, HistoricalPcrArchive $archive)
    {
        Gate::authorize('update', $historicalPcr);
        $data = $request->validate(['revision_id' => 'required|integer', 'reason' => 'required|string|max:2000', 'snapshot' => 'required|array',
            'corrections' => 'nullable|array', 'corrections.*' => ['integer', Rule::exists('historical_pcr_corrections', 'id')->where('historical_pcr_id', $historicalPcr->id)]]);
        // Preserve source passage provenance; ordinary transcription edits never rewrite extracted source text.
        $snapshot = $data['snapshot'];
        $snapshot['page_text'] = $historicalPcr->revision->snapshot['page_text'];
        $snapshot['notes'] = array_values(array_filter($snapshot['notes'] ?? [], fn ($note) => trim((string) $note) !== ''));
        $snapshot['rows'] = array_values($snapshot['rows'] ?? []);
        $snapshot['signatories'] = array_values(array_filter($snapshot['signatories'] ?? [], fn ($person) => trim((string) ($person['name'] ?? '')) !== '' || trim((string) ($person['role'] ?? '')) !== ''));
        $archive->revise($historicalPcr, $snapshot, $data['reason'], $data['revision_id'], $request->user(), $data['corrections'] ?? []);

        return redirect()->route('historical-pcrs.show', $historicalPcr)->with('status', 'New draft revision saved. Earlier versions remain available.');
    }

    public function verify(HistoricalPcr $historicalPcr, Request $request, HistoricalPcrArchive $archive)
    {
        Gate::authorize('update', $historicalPcr);
        $data = $request->validate(['revision_id' => 'required|integer', 'acknowledge' => 'accepted']);
        $archive->verify($historicalPcr, $data['revision_id'], $request->user());

        return back()->with('status', 'Transcription verified and locked. Institutional approval and ratings are unchanged.');
    }

    public function correction(HistoricalPcr $historicalPcr, Request $request)
    {
        Gate::authorize('view', $historicalPcr);
        $data = $request->validate(['message' => 'required|string|max:4000', 'revision_id' => ['required', 'integer', Rule::exists('historical_pcr_revisions', 'id')->where('historical_pcr_id', $historicalPcr->id)]]);
        $historicalPcr->corrections()->firstOrCreate(['requested_by' => $request->user()->id, 'revision_id' => $data['revision_id'], 'message' => $data['message']]);

        return back()->with('status', 'Correction request recorded for administrator review.');
    }

    public function original(HistoricalPcr $historicalPcr, HistoricalPcrArchive $archive)
    {
        Gate::authorize('view', $historicalPcr);

        return response()->download($archive->privatePath($historicalPcr->source_path), $historicalPcr->source_name, ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function page(HistoricalPcr $historicalPcr, int $page, HistoricalPcrArchive $archive)
    {
        Gate::authorize('view', $historicalPcr);
        abort_unless($page >= 1 && $page <= $historicalPcr->source_pages, 404);

        return response()->file($archive->privatePath($historicalPcr->image_directory.'/'.$page.'.jpg'), ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function export(HistoricalPcr $historicalPcr, string $format, Request $request, HistoricalPcrExport $export)
    {
        Gate::authorize('view', $historicalPcr);
        $revision = $request->filled('revision') ? $historicalPcr->revisions()->findOrFail($request->integer('revision')) : $historicalPcr->revision;
        $path = $export->file($historicalPcr, $format, $revision);
        $mime = ['docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'][$format];

        return response()->download($path, strtoupper($historicalPcr->type).'-'.$historicalPcr->id.'-revision-'.$revision->number.'.'.$format,
            ['Content-Type' => $mime, 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\{Opcr, Dpcr, Upcr, Ipcr, ReportAttachment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportAttachmentController extends Controller
{
    private function authorizeReport(Request $request, string $type, int $id): void
    {
        $class = ['opcr' => Opcr::class, 'dpcr' => Dpcr::class, 'upcr' => Upcr::class, 'ipcr' => Ipcr::class][$type] ?? null;
        abort_unless($class, 404);
        $report = $class::findOrFail($id);
        $officeId = $type === 'opcr' ? $report->office_id : $report->employee?->office_id;
        abort_unless($request->user()->hasRole('Supervisor') && $request->user()->office_id
            && (int) $request->user()->office_id === (int) $officeId, 403);
    }

    public function store(Request $request, string $type, int $report)
    {
        $this->authorizeReport($request, $type, $report);
        $request->validate(['file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240']]);
        $file = $request->file('file');
        $path = $file->store('report-attachments', 'local');
        abort_unless($path, 500, 'The file could not be stored. Please try again.');

        try {
            ReportAttachment::create([
                'report_type' => $type, 'report_id' => $report,
                'uploaded_by' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(), 'path' => $path,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()->route($type.'s.show', $report)->with('status', 'File uploaded successfully.');
    }

    public function download(Request $request, ReportAttachment $attachment)
    {
        $this->authorizeReport($request, $attachment->report_type, $attachment->report_id);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}

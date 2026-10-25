@props(['report', 'type'])
@php
    $officeId = $type === 'opcr' ? $report->office_id : $report->employee?->office_id;
    $canUpload = auth()->user()->hasRole('Supervisor') && auth()->user()->office_id
        && (int) auth()->user()->office_id === (int) $officeId;
@endphp
@if ($canUpload)
    <section class="mt-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-bold text-spms-ink">{{ strtoupper($type) }} Files</h2>
        <form method="POST" action="{{ route('report-attachments.store', [$type, $report->id]) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
            @csrf
            <label for="report-file" class="block text-sm font-semibold">Upload a file</label>
            <input id="report-file" name="file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required class="block w-full text-sm">
            <p class="text-xs text-gray-500">PDF, Word, Excel, JPG, or PNG. Maximum 10 MB per file.</p>
            <x-input-error :messages="$errors->get('file')" />
            <x-primary-button>Upload File</x-primary-button>
        </form>
        <ul class="mt-4 space-y-2">
            @forelse (\App\Models\ReportAttachment::where('report_type', $type)->where('report_id', $report->id)->latest()->get() as $attachment)
                <li><a class="text-sm text-spms-navy underline" href="{{ route('report-attachments.download', $attachment) }}">{{ $attachment->original_name }}</a></li>
            @empty
                <li class="text-sm text-gray-500">No files uploaded yet.</li>
            @endforelse
        </ul>
    </section>
@endif

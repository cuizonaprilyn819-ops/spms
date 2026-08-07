<?php

namespace App\Http\Controllers;

use App\Models\Mov;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovController extends Controller
{
    public function index(): View
    {
        $movs = Mov::with('uploadedBy')
            ->latest()
            ->paginate(10);

        return view('movs.index', compact('movs'));
    }

    public function create(): View
    {
        return view('movs.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'mimes:pdf,docx,xlsx,jpg,jpeg', 'max:10240'],
        ]);

        $file = $data['file'];
        $path = $file->store('movs', 'public');

        $mov = Mov::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'extension' => strtolower($file->getClientOriginalExtension()),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('movs.show', $mov)
            ->with('status', 'MOV uploaded successfully.');
    }

    public function show(Mov $mov): View
    {
        return view('movs.show', [
            'mov' => $mov->load('uploadedBy'),
        ]);
    }

    public function download(Mov $mov): StreamedResponse
    {
        return Storage::disk('public')->download($mov->file_path, $mov->original_name);
    }
}

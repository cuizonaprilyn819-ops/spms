<?php

namespace App\Services;

use App\Models\{Mov, PlanningOutput, TargetMov};
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\{ExecutableFinder, Process};

class EvidenceOcr
{
    public function needed(array $findings): bool
    {
        return ! ($findings['satisfies_target'] ?? false)
            && (bool) preg_match('/unreadable|illegible|blurred|blurry|small text|cannot read|not readable|text.{0,30}unclear/i', ($findings['limitations'] ?? '').' '.($findings['target_assessment'] ?? ''));
    }

    public function extract(Mov|TargetMov|PlanningOutput $file): ?string
    {
        [$disk, $path, $name] = app(EvidenceFiles::class)->original($file);
        if (! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true)) return null;
        $key = app(EvidenceFiles::class)->cacheKey($file).'.ocr.txt';
        if (Storage::disk('local')->exists($key)) return Storage::disk('local')->get($key);
        $configured = config('spms.mov.ocr_binary');
        $binary = $configured ?: (new ExecutableFinder)->find('tesseract');
        if (! $binary || ! is_file($binary)) return null;
        $size = @getimagesize($disk->path($path));
        if (! $size || $size[0] * $size[1] > 16000000) return null;
        // Fixed arguments, original file, bounded runtime; no shell interpolation.
        $process = new Process([$binary, $disk->path($path), 'stdout', '-l', 'eng', '--psm', '3']);
        $process->setTimeout(8);
        try { $process->run(); } catch (\Throwable) { return null; }
        if (! $process->isSuccessful()) return null;
        $text = mb_substr(mb_scrub(trim($process->getOutput())), 0, 12000);
        if ($text === '') return null;
        Storage::disk('local')->put($key, $text);
        return $text;
    }
}

<?php

namespace App\Services;

use App\Models\{Mov, PlanningOutput, TargetMov};
use Illuminate\Support\Facades\Storage;

class EvidenceFiles
{
    public function type(Mov|TargetMov|PlanningOutput $file): string
    {
        return $file instanceof Mov ? 'mov' : ($file instanceof TargetMov ? 'target-mov' : 'planning-output');
    }

    public function original(Mov|TargetMov|PlanningOutput $file): array
    {
        return [Storage::disk($file instanceof Mov ? 'public' : 'local'),
            $file instanceof Mov ? $file->file_path : ($file instanceof TargetMov ? $file->path : $file->evidence['path']),
            $file instanceof PlanningOutput ? $file->evidence['name'] : $file->original_name];
    }

    public function group(Mov|TargetMov|PlanningOutput $file)
    {
        $batch = $file->submission_context['batch_id'] ?? null;
        if (! $batch || $file instanceof PlanningOutput) return collect([$file]);
        $report = $file instanceof Mov;
        return $file->newQuery()->where('submission_context->batch_id', $batch)
            ->where($report ? 'uploaded_by' : 'user_id', $report ? $file->uploaded_by : $file->user_id)
            ->where($report ? 'performance_target_id' : 'office_target_id', $report ? $file->performance_target_id : $file->office_target_id)
            ->orderBy('id')->get();
    }

    public function cacheKey(Mov|TargetMov|PlanningOutput $file): string
    {
        [$disk, $path] = $this->original($file);
        return 'mov-processing/'.$this->type($file).'/'.$file->id.'/'.hash('sha256', $path.'|'.$disk->size($path).'|'.$disk->lastModified($path));
    }

    public function previewPath(Mov|TargetMov|PlanningOutput $file): ?string
    {
        try {
            $path = $this->cacheKey($file).'.webp';
            return Storage::disk('local')->exists($path) ? $path : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function preview(Mov|TargetMov|PlanningOutput $file): void
    {
        if (! function_exists('imagewebp') || $this->previewPath($file)) return;
        [$disk, $path, $name] = $this->original($file);
        if (! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true)) return;
        $bytes = $disk->get($path);
        $size = @getimagesizefromstring($bytes);
        if (! $size || $size[0] * $size[1] > 16000000) return;
        $image = @imagecreatefromstring($bytes);
        if (! $image) return;
        $preview = null;
        try {
            if ($size[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $orientation = @exif_read_data($disk->path($path))['Orientation'] ?? 1;
                if (in_array($orientation, [2, 4, 5, 7], true)) imageflip($image, IMG_FLIP_HORIZONTAL);
                $angle = match ($orientation) { 3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0 };
                if ($angle) {
                    $rotated = imagerotate($image, $angle, 0);
                    imagedestroy($image);
                    $image = $rotated;
                }
            }
            $scale = min(1, 1600 / max(imagesx($image), imagesy($image)));
            $preview = imagecreatetruecolor(max(1, (int) round(imagesx($image) * $scale)), max(1, (int) round(imagesy($image) * $scale)));
            imagealphablending($preview, false);
            imagesavealpha($preview, true);
            imagecopyresampled($preview, $image, 0, 0, 0, 0, imagesx($preview), imagesy($preview), imagesx($image), imagesy($image));
            ob_start();
            try { $encoded = imagewebp($preview, null, 82); $webp = ob_get_contents(); }
            finally { ob_end_clean(); }
            if ($encoded && strlen($webp) < strlen($bytes) && str_starts_with($webp, 'RIFF') && substr($webp, 8, 4) === 'WEBP') {
                $destination = $this->cacheKey($file).'.webp';
                $temporary = $destination.'.'.bin2hex(random_bytes(8)).'.tmp';
                try {
                    Storage::disk('local')->put($temporary, $webp);
                    Storage::disk('local')->move($temporary, $destination);
                } finally {
                    Storage::disk('local')->delete($temporary);
                }
            }
        } finally {
            if ($preview) imagedestroy($preview);
            imagedestroy($image);
        }
    }
}

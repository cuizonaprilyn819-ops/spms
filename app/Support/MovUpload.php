<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class MovUpload
{
    public const MAX_FILES = 20;
    public const MAX_TOTAL_BYTES = 35 * 1024 * 1024;

    public static function checkTotal(array $files): void
    {
        if (array_sum(array_map(fn ($file) => $file->getSize(), $files)) > self::MAX_TOTAL_BYTES) {
            throw ValidationException::withMessages(['files' => 'Select up to 35 MB of MOV files per upload. Upload the remaining files in another batch for the same target.']);
        }
    }
}

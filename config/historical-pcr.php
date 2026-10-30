<?php

return [
    // Absolute executable paths may be configured locally, without committing workstation paths.
    'pdfinfo' => env('PCR_PDFINFO', 'pdfinfo'),
    'pdftoppm' => env('PCR_PDFTOPPM', 'pdftoppm'),
    'max_pages' => 100,
    'render_timeout' => 180,
    'render_dpi' => 130,
];

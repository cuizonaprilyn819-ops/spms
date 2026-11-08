param(
    [ValidateRange(1024, 65535)]
    [int]$Port = 8891,
    [string]$PhpPath,
    [switch]$NoMovWorker
)

& (Join-Path $PSScriptRoot 'scripts/serve-local.ps1') -Port $Port -PhpPath $PhpPath -NoMovWorker:$NoMovWorker

param(
    [ValidateRange(1024, 65535)]
    [int]$Port = 8891,
    [string]$PhpPath
)

& (Join-Path $PSScriptRoot 'scripts/serve-local.ps1') -Port $Port -PhpPath $PhpPath

param(
    [ValidateRange(1024, 65535)]
    [int]$Port = 8891
)

$ErrorActionPreference = 'Stop'
$repoPath = Split-Path $PSScriptRoot -Parent
$environmentPath = Join-Path $repoPath '.env'
if (-not (Test-Path -LiteralPath $environmentPath)) {
    throw 'Configure the local database in .env before starting SPMS.'
}

# Read only enough configuration to recognize this workstation's local runtime.
# Never execute .env content or print its credentials.
$connectionSettings = @{}
foreach ($line in Get-Content -LiteralPath $environmentPath) {
    if ($line -match '^\s*(DB_CONNECTION|DB_HOST|DB_PORT)\s*=\s*(.*?)\s*$') {
        $connectionSettings[$Matches[1]] = $Matches[2].Trim('"').Trim("'")
    }
}

$phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
if (-not $phpCommand) {
    throw 'PHP 8.2 or newer must be on PATH. Select a PHP version in Laragon first.'
}
$phpExecutable = $phpCommand.Source
$phpOptions = @('-d', 'upload_max_filesize=25M', '-d', 'post_max_size=32M', '-d', 'max_input_vars=30000', '-d', 'memory_limit=512M', '-d', 'max_execution_time=300')
$phpModules = & $phpExecutable -m
if ($connectionSettings['DB_CONNECTION'] -eq 'pgsql' -and $phpModules -notcontains 'pdo_pgsql') {
    $phpOptions += @('-d', 'extension=pdo_pgsql')
}

$runtimePath = Join-Path $env:LOCALAPPDATA 'SPMS'
$pgData = Join-Path $runtimePath 'data-postgresql17'
$pgCtl = Join-Path $runtimePath 'postgresql17/pgsql/bin/pg_ctl.exe'
if ($connectionSettings['DB_CONNECTION'] -eq 'pgsql' -and
    $connectionSettings['DB_HOST'] -eq '127.0.0.1' -and
    $connectionSettings['DB_PORT'] -eq '55437' -and
    (Test-Path -LiteralPath (Join-Path $pgData 'PG_VERSION')) -and
    (Test-Path -LiteralPath $pgCtl)) {
    & $pgCtl -D $pgData status *> $null
    if ($LASTEXITCODE -ne 0) {
        & $pgCtl -D $pgData -l (Join-Path $runtimePath 'postgresql.log') start -w -t 20
        if ($LASTEXITCODE -ne 0) {
            throw 'Local PostgreSQL could not start. See the SPMS PostgreSQL log.'
        }
    }
}

$env:APP_ENV = 'local'
$env:APP_URL = "http://127.0.0.1:$Port"
$publicPath = Join-Path $repoPath 'public'
$routerPath = Join-Path $repoPath 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
Push-Location $publicPath
try {
    # Keep the pgsql extension option in the process that actually serves the app.
    & $phpExecutable @phpOptions -S "127.0.0.1:$Port" -t $publicPath $routerPath
} finally {
    Pop-Location
}

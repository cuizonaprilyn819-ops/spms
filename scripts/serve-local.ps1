param(
    [ValidateRange(1024, 65535)]
    [int]$Port = 8891,
    [string]$PhpPath,
    [switch]$NoMovWorker
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

$phpCandidates = if ($PhpPath) { @($PhpPath) } else {
    @((Get-Command php.exe -All -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source)) + @('C:\xampp\php\php.exe')
}
$phpExecutable = $null
$driverOptions = @()
$needsPgsql = $connectionSettings['DB_CONNECTION'] -eq 'pgsql'
function Test-SpmsPhpRuntime {
    param([string]$Executable, [bool]$NeedsPgsql, [switch]$LoadPgsql)
    # Windows PowerShell treats native stderr as an error record. A broken
    # candidate must be rejected without preventing the next runtime probe.
    $ErrorActionPreference = 'Continue'
    $probeOptions = @()
    if ($LoadPgsql) { $probeOptions = @('-d', 'extension=pdo_pgsql') }
    $probe = 'exit(PHP_VERSION_ID >= 80200 && (! $argv[1] || extension_loaded(''pdo_pgsql'')) ? 0 : 1);'
    $null = & $Executable @probeOptions -r $probe ([int]$NeedsPgsql) 2>&1
    return $LASTEXITCODE -eq 0
}
foreach ($candidate in ($phpCandidates | Select-Object -Unique)) {
    if (-not (Test-Path -LiteralPath $candidate -PathType Leaf)) { continue }
    # Probe the actual PHP process. A failed extension load is only a warning,
    # so checking the process exit code alone is not enough.
    if (Test-SpmsPhpRuntime -Executable $candidate -NeedsPgsql $needsPgsql) {
        $phpExecutable = $candidate
        break
    }
    if ($needsPgsql) {
        if (Test-SpmsPhpRuntime -Executable $candidate -NeedsPgsql $true -LoadPgsql) {
            $phpExecutable = $candidate
            $driverOptions = @('-d', 'extension=pdo_pgsql')
            break
        }
    }
}
if (-not $phpExecutable) {
    throw 'No compatible PHP runtime found. Use PHP 8.2+ with pdo_pgsql installed, for example: .\serve.ps1 -PhpPath C:\xampp\php\php.exe'
}
$phpOptions = @('-d', 'upload_max_filesize=10M', '-d', 'post_max_size=40M', '-d', 'max_input_vars=30000', '-d', 'memory_limit=512M', '-d', 'max_execution_time=300')
$phpOptions += $driverOptions
function Test-SpmsWebp {
    param([switch]$LoadGd)
    $ErrorActionPreference = 'Continue'
    $options = @($driverOptions)
    if ($LoadGd) { $options += @('-d', 'extension=gd') }
    $null = & $phpExecutable @options -r 'exit(function_exists(''imagewebp'') ? 0 : 1);' 2>&1
    return $LASTEXITCODE -eq 0
}
if (-not (Test-SpmsWebp)) {
    if (Test-SpmsWebp -LoadGd) { $phpOptions += @('-d', 'extension=gd') }
    else { Write-Warning 'PHP GD/WebP is unavailable. MOV originals remain available; enable GD for smaller previews.' }
}
Write-Host "PHP runtime: $phpExecutable"

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
$routerPath = Join-Path $repoPath 'scripts/local-router.php'
$movWorker = $null
Push-Location $publicPath
try {
    if (-not $NoMovWorker) {
        $logDirectory = Join-Path $repoPath 'storage/logs'
        $artisanPath = '"' + (Join-Path $repoPath 'artisan') + '"'
        $workerArgs = @($phpOptions) + @('-d', 'max_execution_time=0', $artisanPath, 'queue:work', 'mov-processing', '--queue=mov-processing', '--sleep=2', '--tries=1', '--timeout=600', '--memory=384')
        $movWorker = Start-Process -FilePath $phpExecutable -ArgumentList $workerArgs -WorkingDirectory $repoPath -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDirectory "mov-worker-$Port.log") -RedirectStandardError (Join-Path $logDirectory "mov-worker-$Port-error.log") -PassThru
        Write-Host 'MOV background worker started.'
    }
    # Keep the pgsql extension option in the process that actually serves the app.
    & $phpExecutable @phpOptions -S "127.0.0.1:$Port" -t $publicPath $routerPath
} finally {
    if ($movWorker -and -not $movWorker.HasExited) { Stop-Process -Id $movWorker.Id -ErrorAction SilentlyContinue }
    Pop-Location
}

$ErrorActionPreference = 'Stop'
$phpExecutable = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $phpExecutable)) {
    throw 'XAMPP PHP was not found at C:\xampp\php\php.exe.'
}
$phpModules = & $phpExecutable -m
if ($phpModules -notcontains 'pdo_pgsql') {
    throw 'Enable pdo_pgsql in the XAMPP php.ini before starting the app.'
}
Push-Location $PSScriptRoot
try {
    & $phpExecutable artisan serve --host=127.0.0.1 --port=8000
} finally {
    Pop-Location
}

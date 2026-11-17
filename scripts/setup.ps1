[CmdletBinding()]
param(
    [string]$PhpPath,
    [switch]$Migrate,
    [switch]$SkipInstall,
    [switch]$SkipBuild
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
$projectDirectory = Split-Path -Parent $PSScriptRoot
$phpExecutable = Resolve-FinwatchPhp -RequestedPath $PhpPath
$originalProcessPath = $env:PATH
Push-Location -LiteralPath $projectDirectory

try {
    $env:PATH = "$(Split-Path -Parent $phpExecutable);$originalProcessPath"
    if (-not (Test-Path -LiteralPath '.env')) {
        Copy-Item -LiteralPath '.env.example' -Destination '.env'
        Write-Host '.env dibuat; periksa koneksi database dan Google OAuth.'
    }

    if (-not $SkipInstall) {
        $composerExecutable = (Get-Command composer -ErrorAction Stop).Source
        Invoke-FinwatchCommand -Executable $composerExecutable -Arguments @('install', '--prefer-dist', '--no-interaction')
        $npmExecutable = (Get-Command npm.cmd -ErrorAction Stop).Source
        if (Test-Path -LiteralPath 'package-lock.json') {
            Invoke-FinwatchCommand -Executable $npmExecutable -Arguments @('ci')
        }
        else {
            Invoke-FinwatchCommand -Executable $npmExecutable -Arguments @('install')
        }
    }

    $keySetting = Select-String -LiteralPath '.env' -Pattern '^APP_KEY=.+$'
    if (-not $keySetting) {
        Invoke-FinwatchCommand -Executable $phpExecutable -Arguments @('artisan', 'key:generate')
    }
    Invoke-FinwatchCommand -Executable $phpExecutable -Arguments @('artisan', 'config:clear')

    if ($Migrate) {
        Write-Host 'Migrasi dan seed menggunakan database pada .env.'
        Invoke-FinwatchCommand -Executable $phpExecutable -Arguments @('artisan', 'migrate', '--seed', '--no-interaction')
    }
    else {
        Write-Host 'Migrasi belum dijalankan. Setelah DB/.env siap, jalankan setup.ps1 -Migrate -SkipInstall.'
    }

    if (-not $SkipBuild) {
        $npmExecutable = (Get-Command npm.cmd -ErrorAction Stop).Source
        Invoke-FinwatchCommand -Executable $npmExecutable -Arguments @('run', 'build')
    }
    Write-Host 'Setup selesai. Jalankan scripts/start.ps1 untuk server lokal.'
}
finally {
    Pop-Location
    $env:PATH = $originalProcessPath
}

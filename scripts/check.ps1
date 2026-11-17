[CmdletBinding()]
param([string]$PhpPath)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
$projectDirectory = Split-Path -Parent $PSScriptRoot
$phpExecutable = Resolve-FinwatchPhp -RequestedPath $PhpPath
$originalProcessPath = $env:PATH
Push-Location -LiteralPath $projectDirectory

try {
    $env:PATH = "$(Split-Path -Parent $phpExecutable);$originalProcessPath"
    $npmExecutable = (Get-Command npm.cmd -ErrorAction Stop).Source
    Invoke-FinwatchCommand -Executable $phpExecutable -Arguments @('artisan', 'config:clear')
    Invoke-FinwatchCommand -Executable $phpExecutable -Arguments @('artisan', 'test')
    Invoke-FinwatchCommand -Executable $phpExecutable -Arguments @('vendor/laravel/pint/builds/pint', '--test')
    Invoke-FinwatchCommand -Executable $npmExecutable -Arguments @('run', 'lint')
    Invoke-FinwatchCommand -Executable $npmExecutable -Arguments @('run', 'typecheck')
    Invoke-FinwatchCommand -Executable $npmExecutable -Arguments @('run', 'test')
    Invoke-FinwatchCommand -Executable $npmExecutable -Arguments @('run', 'build')
    Write-Host 'Seluruh pemeriksaan selesai tanpa kegagalan.'
}
finally {
    Pop-Location
    $env:PATH = $originalProcessPath
}

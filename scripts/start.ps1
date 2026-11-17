[CmdletBinding()]
param(
    [string]$PhpPath,
    [ValidateRange(1024, 65535)][int]$Port = 8000
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'common.ps1')
$projectDirectory = Split-Path -Parent $PSScriptRoot
$phpExecutable = Resolve-FinwatchPhp -RequestedPath $PhpPath
$originalProcessPath = $env:PATH
$ownedProcesses = @()
$helpers = @()

function Start-LocalHelper {
    param([string]$Name, [string[]]$HelperArguments)
    $helper = Start-Process -FilePath $phpExecutable -ArgumentList $HelperArguments -WorkingDirectory $projectDirectory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $projectDirectory "storage/logs/local-$Name.log") -RedirectStandardError (Join-Path $projectDirectory "storage/logs/local-$Name-error.log")
    return $helper
}

function Stop-OwnedProcessTree {
    param([System.Diagnostics.Process]$OwnedProcess)
    if ($OwnedProcess.HasExited) { return }
    $descendants = @(Get-CimInstance Win32_Process -Filter "ParentProcessId = $($OwnedProcess.Id)" -ErrorAction SilentlyContinue)
    foreach ($child in $descendants) {
        $childProcess = Get-Process -Id $child.ProcessId -ErrorAction SilentlyContinue
        if ($childProcess -and $childProcess.StartTime -ge $OwnedProcess.StartTime) {
            Stop-OwnedProcessTree -OwnedProcess $childProcess
        }
    }
    if (-not $OwnedProcess.HasExited) { Stop-Process -InputObject $OwnedProcess -ErrorAction SilentlyContinue }
}

Push-Location -LiteralPath $projectDirectory
try {
    $env:PATH = "$(Split-Path -Parent $phpExecutable);$originalProcessPath"
    if (-not (Test-Path -LiteralPath '.env')) { throw '.env belum ada. Jalankan scripts/setup.ps1 dan periksa database dahulu.' }
    if (-not (Test-Path -LiteralPath 'vendor/autoload.php')) { throw 'Dependency PHP belum ada. Jalankan scripts/setup.ps1.' }
    if (-not (Test-Path -LiteralPath 'public/build/manifest.json') -and -not (Test-Path -LiteralPath 'public/hot')) { throw 'Aset belum ada. Jalankan npm run build atau npm run dev.' }
    if (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) { throw "Port $Port sudah digunakan. Buka http://localhost:$Port atau hentikan server lama dahulu." }
    foreach ($definition in @(
        @{ Name = 'worker'; Args = @('artisan', 'queue:work', '--sleep=2', '--tries=3', '--timeout=90', '--memory=256') },
        @{ Name = 'scheduler'; Args = @('artisan', 'schedule:work') }
    )) {
        $helperProcess = Start-LocalHelper -Name $definition.Name -HelperArguments $definition.Args
        $ownedProcesses += $helperProcess
        $helpers += @{ Name = $definition.Name; Args = $definition.Args; Process = $helperProcess; Restarts = 0; Window = [datetime]::UtcNow }
    }
    $serverProcess = Start-LocalHelper -Name 'server' -HelperArguments @('artisan', 'serve', '--host=localhost', "--port=$Port")
    $ownedProcesses += $serverProcess
    Start-Sleep -Seconds 2
    foreach ($processToCheck in $ownedProcesses) {
        if ($processToCheck.HasExited) { throw "Proses FINWATCH gagal dimulai. Periksa storage/logs/local-*-error.log (exit $($processToCheck.ExitCode))." }
    }
    Write-Host "FINWATCH lokal: http://localhost:$Port (Ctrl+C untuk berhenti). Server, pekerja antrean, dan penjadwal berjalan bersama."
    while (-not $serverProcess.HasExited) {
        foreach ($helper in $helpers) {
            if ($helper.Process.HasExited) {
                if (([datetime]::UtcNow - $helper.Window).TotalMinutes -ge 1) { $helper.Restarts = 0; $helper.Window = [datetime]::UtcNow }
                if ($helper.Restarts -ge 3) { throw "Proses $($helper.Name) berhenti berulang kali. Periksa storage/logs/local-$($helper.Name)-error.log dan laravel.log." }
                $helper.Restarts++
                Write-Warning "Menjalankan ulang $($helper.Name) yang berhenti (exit $($helper.Process.ExitCode))."
                $helper.Process = Start-LocalHelper -Name $helper.Name -HelperArguments $helper.Args
                $ownedProcesses += $helper.Process
            }
        }
        Start-Sleep -Seconds 2
    }
    if ($serverProcess.ExitCode -ne 0) { throw "Server berhenti dengan exit $($serverProcess.ExitCode). Periksa storage/logs/local-server-error.log." }
}
finally {
    foreach ($ownedProcess in $ownedProcesses) { Stop-OwnedProcessTree -OwnedProcess $ownedProcess }
    Pop-Location
    $env:PATH = $originalProcessPath
}

Set-StrictMode -Version Latest

function Resolve-FinwatchPhp {
    param([string]$RequestedPath)

    if ($RequestedPath) {
        if (-not (Test-Path -LiteralPath $RequestedPath -PathType Leaf)) {
            throw "PHP tidak ditemukan: $RequestedPath"
        }
        $phpExecutable = (Resolve-Path -LiteralPath $RequestedPath).Path
    }
    else {
        $projectDirectory = Split-Path -Parent $PSScriptRoot
        $laragonCandidate = Join-Path $projectDirectory '..\..\bin\php\php-8.3.7-Win32-vs16-x64\php.exe'
        $knownCandidate = 'D:\laragon\bin\php\php-8.3.7-Win32-vs16-x64\php.exe'
        if (Test-Path -LiteralPath $laragonCandidate -PathType Leaf) {
            $phpExecutable = (Resolve-Path -LiteralPath $laragonCandidate).Path
        }
        elseif (Test-Path -LiteralPath $knownCandidate -PathType Leaf) {
            $phpExecutable = $knownCandidate
        }
        else {
            $phpExecutable = (Get-Command php -ErrorAction Stop).Source
        }
    }

    $versionId = & $phpExecutable -r 'echo PHP_VERSION_ID;'
    if ($LASTEXITCODE -ne 0 -or [int]$versionId -lt 80300) {
        throw 'FINWATCH memerlukan PHP minimal 8.3. Pilih binary yang sesuai melalui -PhpPath.'
    }
    return $phpExecutable
}

function Invoke-FinwatchCommand {
    param(
        [Parameter(Mandatory = $true)][string]$Executable,
        [string[]]$Arguments = @()
    )

    & $Executable @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Command gagal (exit $LASTEXITCODE): $Executable $($Arguments -join ' ')"
    }
}

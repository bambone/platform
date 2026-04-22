#Requires -Version 5.1
<#
.SYNOPSIS
  Guard checks (APP_ENV, DB_HOST), then php artisan tenant-media:backfill-from-r2.

.DESCRIPTION
  Runs before any artisan/R2 work: refuses production APP_ENV; DB_HOST must be local.
  Target directory must be absolute and outside the repo (enforced by Laravel command).

.PARAMETER Target
  Absolute path for local mirror (e.g. MEDIA_LOCAL_ROOT). Default: value of MEDIA_LOCAL_ROOT from .env if set.

.PARAMETER Tenant
  Passed as --tenant=

.PARAMETER Prefix
  Passed as --prefix=

.PARAMETER DryRun
  Passed as --dry-run

.PARAMETER OnlyMissing
  Passed as --only-missing

.PARAMETER VerifyAfterDownload
  Passed as --verify-after-download

.PARAMETER Limit
  Passed as --limit=

.EXAMPLE
  .\scripts\sync-r2-public-media-to-local.ps1 -Target 'D:\rentbase-media'
#>
param(
    [string] $Target = '',
    [string] $Tenant = '',
    [string] $Prefix = '',
    [switch] $DryRun,
    [switch] $OnlyMissing,
    [switch] $VerifyAfterDownload,
    [int] $Limit = 0
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot

function Read-DotEnv {
    param([string] $LiteralPath)
    if (-not (Test-Path -LiteralPath $LiteralPath)) {
        Write-Error ".env not found: $LiteralPath"
    }
    $map = @{}
    $lines = Get-Content -LiteralPath $LiteralPath
    foreach ($rawLine in $lines) {
        $line = $rawLine.Trim()
        if ($line -eq '' -or $line.StartsWith('#')) { continue }

        $eq = $line.IndexOf('=')
        if ($eq -lt 1) { continue }
        $key = $line.Substring(0, $eq).Trim()
        $val = $line.Substring($eq + 1).Trim()

        if (($val.StartsWith('"') -and $val.EndsWith('"') -and $val.Length -ge 2) -or
            ($val.StartsWith("'") -and $val.EndsWith("'") -and $val.Length -ge 2)) {
            $q = $val[0]
            $val = $val.Substring(1, $val.Length - 2)
            if ($q -eq '"') {
                $val = $val -replace '\\n', "`n" -replace '\\r', "`r" -replace '\\"', '"'
            }
        }
        else {
            $hash = $val.IndexOf(' #')
            if ($hash -ge 0) { $val = $val.Substring(0, $hash).TrimEnd() }
        }
        $map[$key] = $val
    }
    return $map
}

function Assert-LocalDbHost {
    param([string] $HostName)
    $h = $HostName.Trim().ToLowerInvariant()
    if ($h -eq 'localhost' -or $h -eq '::1') { return }
    if ($h.StartsWith('127.')) { return }
    Write-Error "Refusing R2 sync: DB_HOST must be localhost, ::1, or an address in 127.0.0.0/8 (got: $HostName)."
}

$envPath = Join-Path $root '.env'
$envMap = Read-DotEnv -LiteralPath $envPath

$appEnvRaw = if ($envMap.ContainsKey('APP_ENV')) { $envMap['APP_ENV'] } else { '' }
if ($appEnvRaw.Trim().ToLowerInvariant() -eq 'production') {
    Write-Error 'Refusing R2 sync: APP_ENV is production.'
}

$dbHostRaw = if ($envMap.ContainsKey('DB_HOST')) { $envMap['DB_HOST'] } else { '127.0.0.1' }
Assert-LocalDbHost -HostName $dbHostRaw

if ([string]::IsNullOrWhiteSpace($Target)) {
    if ($envMap.ContainsKey('MEDIA_LOCAL_ROOT') -and $envMap['MEDIA_LOCAL_ROOT'].Trim() -ne '') {
        $Target = $envMap['MEDIA_LOCAL_ROOT'].Trim()
    }
}

if ([string]::IsNullOrWhiteSpace($Target)) {
    Write-Error 'Pass -Target or set MEDIA_LOCAL_ROOT in .env'
}

$targetPath = $Target
if (-not [IO.Path]::IsPathRooted($targetPath)) {
    Write-Error '-Target must be an absolute path'
}

Set-Location $root

$args = @('artisan', 'tenant-media:backfill-from-r2', '--target', $targetPath, '--no-interaction')
if ($Tenant -ne '') {
    $args += @('--tenant', $Tenant)
}
if ($Prefix -ne '') {
    $args += @('--prefix', $Prefix)
}
if ($DryRun) { $args += '--dry-run' }
if ($OnlyMissing) { $args += '--only-missing' }
if ($VerifyAfterDownload) { $args += '--verify-after-download' }
if ($Limit -gt 0) {
    $args += @('--limit', "$Limit")
}

Write-Host "php $($args -join ' ')"
& php @args
exit $LASTEXITCODE

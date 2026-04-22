#Requires -Version 5.1
<#
.SYNOPSIS
  Run DB whitelist restore, then optional R2 public media backfill.

.DESCRIPTION
  Requires CONFIRM_STAGE_RESTORE=yes for the restore step (enforced by restore-stage-from-backup.ps1).
  Use -SkipDb and/or -SkipMedia to run only one phase.

.EXAMPLE
  $env:CONFIRM_STAGE_RESTORE='yes'
  .\scripts\bootstrap-stage-from-prod.ps1 -MediaTarget 'D:\rentbase-media'
#>
param(
    [switch] $SkipDb,
    [switch] $SkipMedia,
    [string] $MediaTarget = '',
    [switch] $SkipSnapshot,
    [switch] $AllowAbsoluteRedirects,
    [string] $SqlPath = '',
    [string] $SrcDatabase = 'platform_restore_src',
    [switch] $KeepDownloadedDump,
    [switch] $DryRun,
    [switch] $OnlyMissing,
    [switch] $VerifyAfterDownload,
    [string] $Tenant = '',
    [string] $Prefix = '',
    [int] $MediaLimit = 0
)

$ErrorActionPreference = 'Stop'
$here = $PSScriptRoot
$root = Split-Path -Parent $here

function Read-DotEnvMediaRoot {
    param([string] $LiteralPath)
    if (-not (Test-Path -LiteralPath $LiteralPath)) { return '' }
    foreach ($rawLine in Get-Content -LiteralPath $LiteralPath) {
        $line = $rawLine.Trim()
        if ($line -eq '' -or $line.StartsWith('#')) { continue }
        if (-not $line.StartsWith('MEDIA_LOCAL_ROOT=')) { continue }
        $val = $line.Substring('MEDIA_LOCAL_ROOT='.Length).Trim()
        if (($val.StartsWith('"') -and $val.EndsWith('"')) -or ($val.StartsWith("'") -and $val.EndsWith("'"))) {
            return $val.Substring(1, $val.Length - 2)
        }
        $hash = $val.IndexOf(' #')
        if ($hash -ge 0) { $val = $val.Substring(0, $hash).TrimEnd() }
        return $val
    }
    return ''
}

if (-not $SkipDb) {
    $dbArgs = @()
    if ($SkipSnapshot) { $dbArgs += '-SkipSnapshot' }
    if ($AllowAbsoluteRedirects) { $dbArgs += '-AllowAbsoluteRedirects' }
    if ($SqlPath -ne '') { $dbArgs += @('-SqlPath', $SqlPath) }
    if ($SrcDatabase -ne 'platform_restore_src') { $dbArgs += @('-SrcDatabase', $SrcDatabase) }
    if ($KeepDownloadedDump) { $dbArgs += '-KeepDownloadedDump' }
    & "$here\restore-stage-from-backup.ps1" @dbArgs
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

if (-not $SkipMedia) {
    $mt = $MediaTarget
    if ([string]::IsNullOrWhiteSpace($mt)) {
        $mt = Read-DotEnvMediaRoot -LiteralPath (Join-Path $root '.env')
    }
    if ([string]::IsNullOrWhiteSpace($mt)) {
        Write-Error 'Pass -MediaTarget, set MEDIA_LOCAL_ROOT in .env, or use -SkipMedia.'
    }
    $mArgs = @('-Target', $mt)
    if ($Tenant -ne '') { $mArgs += @('-Tenant', $Tenant) }
    if ($Prefix -ne '') { $mArgs += @('-Prefix', $Prefix) }
    if ($DryRun) { $mArgs += '-DryRun' }
    if ($OnlyMissing) { $mArgs += '-OnlyMissing' }
    if ($VerifyAfterDownload) { $mArgs += '-VerifyAfterDownload' }
    if ($MediaLimit -gt 0) { $mArgs += @('-Limit', $MediaLimit) }
    & "$here\sync-r2-public-media-to-local.ps1" @mArgs
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

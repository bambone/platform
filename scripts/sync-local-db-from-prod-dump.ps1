#Requires -Version 5.1
<#
.SYNOPSIS
  Replace the local MySQL database (from .env) with a Navicat-style SQL dump from production.

.DESCRIPTION
  - Does not modify .env or application URLs in code.
  - After import, rewrites *.rentbase.su -> *.rentbase.local in tenant_domains and tenant_settings
    so ResolveTenantFromDomain works with TENANCY_ROOT_DOMAIN=rentbase.local.
  - Then runs `php artisan migrate --force`.

.PARAMETER DumpPath
  Absolute path to the .sql file (e.g. copy from Desktop).

.PARAMETER SkipRewire
  Keep production hostnames (use only if you map prod domains via hosts file).

.EXAMPLE
  .\scripts\sync-local-db-from-prod-dump.ps1 -DumpPath 'C:\Users\you\Desktop\platform.sql'
#>
param(
    [Parameter(Mandatory = $true)]
    [string] $DumpPath,

    [switch] $SkipRewire
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

if (-not (Test-Path -LiteralPath $DumpPath)) {
    Write-Error "Dump not found: $DumpPath"
}

$args = @('artisan', 'rentbase:import-mysql-dump', $DumpPath, '--no-interaction')
if ($SkipRewire) {
    $args += '--skip-rewire'
}

& php @args
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

& php artisan migrate --force --no-interaction
exit $LASTEXITCODE

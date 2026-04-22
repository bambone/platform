#Requires -Version 5.1
<#
.SYNOPSIS
  Stage/local DB restore: download latest .sql.zst (rclone), import into temporary MySQL DB, copy whitelist tables into DB from .env.

.DESCRIPTION
  Guards (APP_ENV, DB_HOST, CONFIRM_STAGE_RESTORE) run before any download or MySQL restore work.
  Whitelist: scripts/restore/restore-include.txt (single source of truth).
  Does not overwrite tenant_domains (not in whitelist).
  After copy: fails if redirects contain absolute http(s) URLs unless -AllowAbsoluteRedirects.
  Then: php artisan optimize:clear && php artisan migrate --force

  Dependencies: mysql, mysqldump, zstd (for .zst), rclone (unless -SqlPath), php

  Env:
    CONFIRM_STAGE_RESTORE=yes   — required
    RENTBASE_RESTORE_RCLONE_REMOTE — e.g. mailru-webdav:Backups/rentbase/mysql
    RCLONE_CONFIG — optional path to rclone config
    RENTBASE_PATH_PREPEND — optional; semicolon-separated dirs prepended to PATH (e.g. OSPanel MySQL bin, rclone, zstd)

.PARAMETER SkipSnapshot
  Skip mysqldump of target DB before restore (default: snapshot enabled).

.PARAMETER AllowAbsoluteRedirects
  Do not exit with error when redirects.from_url / to_url contain https?://

.PARAMETER SqlPath
  Use this dump instead of rclone (.sql or .sql.zst).

.PARAMETER SrcDatabase
  Temporary database name (default: platform_restore_src).

.PARAMETER KeepDownloadedDump
  Keep file under download cache when using rclone.

.EXAMPLE
  $env:CONFIRM_STAGE_RESTORE='yes'; $env:RENTBASE_RESTORE_RCLONE_REMOTE='remote:Backups/rentbase/mysql'
  .\scripts\restore-stage-from-backup.ps1
#>
param(
    [switch] $SkipSnapshot,
    [switch] $AllowAbsoluteRedirects,
    [string] $SqlPath = '',
    [string] $SrcDatabase = 'platform_restore_src',
    [string] $RcloneRemote = $env:RENTBASE_RESTORE_RCLONE_REMOTE,
    [string] $RcloneConfig = $env:RCLONE_CONFIG,
    [string] $DownloadDir = '',
    [switch] $KeepDownloadedDump
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$restoreDir = Join-Path $root 'scripts\restore'
$includeFile = Join-Path $restoreDir 'restore-include.txt'
$snapDir = Join-Path $restoreDir 'snapshots'

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
            $hash2 = $val.IndexOf("`t#")
            if ($hash2 -ge 0) { $val = $val.Substring(0, $hash2).TrimEnd() }
        }
        $map[$key] = $val
    }
    return $map
}

function New-MySqlClientCnf {
    param(
        [string] $User,
        [string] $Password,
        [string] $MySqlHost,
        [int] $Port
    )
    $p = [IO.Path]::ChangeExtension([IO.Path]::GetTempFileName(), 'cnf')
    $escPwd = $Password -replace '\\', '\\\\' -replace '"', '\"'
    # UTF-8 must be without BOM — mysqldump treats BOM as junk before [client]
    $lines = @(
        '[client]',
        "user=$User",
        "password=`"$escPwd`"",
        "host=$MySqlHost",
        "port=$Port"
    )
    [IO.File]::WriteAllLines($p, $lines, [Text.UTF8Encoding]::new($false))
    return $p
}

function Assert-LocalDbHost {
    param([string] $HostName)
    $h = $HostName.Trim().ToLowerInvariant()
    if ($h -eq 'localhost' -or $h -eq '::1') { return }
    # 127.0.0.0/8 — loopback (в т.ч. 127.127.126.x у OSPanel)
    if ($h.StartsWith('127.')) { return }
    Write-Error "Refusing restore: DB_HOST must be localhost, ::1, or an address in 127.0.0.0/8 (got: $HostName)."
}

function Get-RestoreTableNames {
    param([string] $Path)
    $names = [System.Collections.Generic.List[string]]::new()
    Get-Content -LiteralPath $Path | ForEach-Object {
        $t = $_.Trim()
        if ($t -ne '' -and -not $t.StartsWith('#')) { $names.Add($t) }
    }
    return $names
}

function Test-MySqlTableExists {
    param(
        [string] $CnfPath,
        [string] $Schema,
        [string] $Table
    )
    $sch = $Schema.Replace("'", "''")
    $tbl = $Table.Replace("'", "''")
    $q = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$sch' AND table_name='$tbl'"
    $out = & mysql --defaults-extra-file=$CnfPath -N -B -e $q 2>&1
    if ($LASTEXITCODE -ne 0) { Write-Error "mysql: $out" }
    $line = ($out | Select-Object -First 1).ToString().Trim()
    return ([int]$line) -ge 1
}

# --- Load env & guards (before any network / restore I/O) ---
$envPath = Join-Path $root '.env'
$envMap = Read-DotEnv -LiteralPath $envPath

# Tools often missing from PATH for IDE/PHP (OSPanel MySQL bin, rclone, zstd). Process env first, then .env.
$prependList = [System.Collections.Generic.List[string]]::new()
$prependSeen = @{}
foreach ($rawBlock in @(
        $(if ($null -ne $env:RENTBASE_PATH_PREPEND) { $env:RENTBASE_PATH_PREPEND } else { '' }),
        $(if ($envMap.ContainsKey('RENTBASE_PATH_PREPEND')) { $envMap['RENTBASE_PATH_PREPEND'] } else { '' })
    )) {
    foreach ($seg in $rawBlock.Trim().TrimEnd(';').Split(';')) {
        $t = $seg.Trim()
        if ($t -eq '') { continue }
        $k = $t.ToLowerInvariant()
        if ($prependSeen.ContainsKey($k)) { continue }
        $prependSeen[$k] = $true
        $prependList.Add($t)
    }
}
if ($prependList.Count -gt 0) {
    $env:Path = ($prependList -join ';') + ';' + $env:Path
}

$appEnvRaw = if ($envMap.ContainsKey('APP_ENV')) { $envMap['APP_ENV'] } else { '' }
$appEnv = $appEnvRaw.Trim().ToLowerInvariant()
if ($appEnv -eq 'production') {
    Write-Error 'Refusing restore: APP_ENV is production.'
}

$dbHostRaw = if ($envMap.ContainsKey('DB_HOST')) { $envMap['DB_HOST'] } else { '127.0.0.1' }
$dbHost = $dbHostRaw.Trim()
Assert-LocalDbHost -HostName $dbHost

$confirmRaw = if ($null -ne $env:CONFIRM_STAGE_RESTORE) { $env:CONFIRM_STAGE_RESTORE } elseif ($envMap.ContainsKey('CONFIRM_STAGE_RESTORE')) { $envMap['CONFIRM_STAGE_RESTORE'] } else { '' }
if ($confirmRaw.Trim().ToLowerInvariant() -ne 'yes') {
    Write-Error 'Refusing restore: set CONFIRM_STAGE_RESTORE=yes in .env or in environment'
}

$dbPort = 3306
if ($envMap.ContainsKey('DB_PORT') -and $envMap['DB_PORT'] -ne '') {
    $parsedPort = 3306
    if ([int]::TryParse($envMap['DB_PORT'].Trim(), [ref]$parsedPort)) {
        $dbPort = $parsedPort
    }
}
$dbName = if ($envMap.ContainsKey('DB_DATABASE')) { $envMap['DB_DATABASE'].Trim() } else { '' }
$dbUser = if ($envMap.ContainsKey('DB_USERNAME')) { $envMap['DB_USERNAME'].Trim() } else { 'root' }
$dbPass = if ($envMap.ContainsKey('DB_PASSWORD')) { $envMap['DB_PASSWORD'] } else { '' }

if ($dbName -eq '') {
    Write-Error 'DB_DATABASE is empty in .env'
}

# rclone / confirm extras: same keys as Laravel .env (process env still wins via param defaults)
if ([string]::IsNullOrWhiteSpace($RcloneRemote) -and $envMap.ContainsKey('RENTBASE_RESTORE_RCLONE_REMOTE')) {
    $RcloneRemote = $envMap['RENTBASE_RESTORE_RCLONE_REMOTE'].Trim()
}
if ([string]::IsNullOrWhiteSpace($RcloneConfig) -and $envMap.ContainsKey('RCLONE_CONFIG')) {
    $RcloneConfig = $envMap['RCLONE_CONFIG'].Trim()
}

if (-not (Test-Path -LiteralPath $includeFile)) {
    Write-Error "Missing whitelist: $includeFile"
}
$tableNames = Get-RestoreTableNames -Path $includeFile
if ($tableNames.Count -eq 0) {
    Write-Error "No tables listed in $includeFile"
}

$cnf = New-MySqlClientCnf -User $dbUser -Password $dbPass -MySqlHost $dbHost -Port $dbPort
try {
    # --- Snapshot (default on) ---
    if (-not $SkipSnapshot) {
        if (-not (Test-Path -LiteralPath $snapDir)) {
            New-Item -ItemType Directory -Path $snapDir | Out-Null
        }
        $stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
        $snapFile = Join-Path $snapDir "${dbName}_pre_restore_${stamp}.sql"
        Write-Host "Snapshot -> $snapFile"
        & mysqldump --defaults-extra-file=$cnf --single-transaction --no-tablespaces $dbName | Set-Content -LiteralPath $snapFile -Encoding utf8
        if ($LASTEXITCODE -ne 0) { Write-Error 'mysqldump failed' }
    }

    # --- Acquire dump ---
    $dumpLocal = ''
    $useZstd = $false
    if ($SqlPath -ne '') {
        if (-not (Test-Path -LiteralPath $SqlPath)) {
            Write-Error "SqlPath not found: $SqlPath"
        }
        $dumpLocal = (Resolve-Path -LiteralPath $SqlPath).Path
        $useZstd = $dumpLocal.ToLowerInvariant().EndsWith('.zst')
    }
    else {
        if ([string]::IsNullOrWhiteSpace($RcloneRemote)) {
            Write-Error 'Set RENTBASE_RESTORE_RCLONE_REMOTE or pass -SqlPath'
        }
        $cacheDir = $DownloadDir
        if ([string]::IsNullOrWhiteSpace($cacheDir)) {
            $cacheDir = Join-Path $env:TEMP 'rentbase-restore'
        }
        if (-not (Test-Path -LiteralPath $cacheDir)) {
            New-Item -ItemType Directory -Path $cacheDir | Out-Null
        }

        $rcloneBase = @()
        if ($RcloneConfig -ne '') {
            $rcloneBase += @('--config', $RcloneConfig)
        }

        Write-Host "rclone lsf $RcloneRemote ..."
        $remoteTrim = $RcloneRemote.TrimEnd('/')
        $lsArgs = $rcloneBase + @('lsf', '--files-only', $remoteTrim)
        $candidates = & rclone @lsArgs 2>&1
        if ($LASTEXITCODE -ne 0) { Write-Error "rclone lsf failed: $candidates" }
        $zstFiles = @($candidates | Where-Object { $_ -match '\.(sql\.zst|zst)$' })
        if ($zstFiles.Count -eq 0) {
            Write-Error "No .zst files found under $RcloneRemote"
        }
        $latest = ($zstFiles | Sort-Object | Select-Object -Last 1).ToString().Trim()
        $remoteFile = "$remoteTrim/$latest"
        $dumpLocal = Join-Path $cacheDir $latest
        Write-Host "rclone copyto $remoteFile -> $dumpLocal"
        $copyArgs = $rcloneBase + @('copyto', $remoteFile, $dumpLocal)
        & rclone @copyArgs
        if ($LASTEXITCODE -ne 0) { Write-Error 'rclone copyto failed' }
        $useZstd = $true
    }

    # --- Temp DB + import ---
    Write-Host "Recreate temporary database [$SrcDatabase]..."
    $dropCreate = @(
        "DROP DATABASE IF EXISTS ``$SrcDatabase``;",
        "CREATE DATABASE ``$SrcDatabase`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    ) -join "`n"
    & mysql --defaults-extra-file=$cnf -e $dropCreate
    if ($LASTEXITCODE -ne 0) { Write-Error 'mysql create src db failed' }

    Write-Host 'Importing dump into temporary DB (may take a while)...'
    $cnfEsc = $cnf -replace '\\', '/' 
    $dumpEsc = $dumpLocal -replace '\\', '/'
    if ($useZstd) {
        $cmd = "zstd -dc `"$dumpEsc`" | mysql --defaults-extra-file=`"$cnfEsc`" `"$SrcDatabase`""
        cmd /c $cmd
        if ($LASTEXITCODE -ne 0) { Write-Error 'zstd|mysql import failed' }
    }
    else {
        $cmd = "mysql --defaults-extra-file=`"$cnfEsc`" `"$SrcDatabase`" < `"$dumpEsc`""
        cmd /c $cmd
        if ($LASTEXITCODE -ne 0) { Write-Error 'mysql import failed' }
    }

    foreach ($t in $tableNames) {
        if (-not (Test-MySqlTableExists -CnfPath $cnf -Schema $SrcDatabase -Table $t)) {
            Write-Error "Fail-fast: table ``$t`` missing in source DB ``$SrcDatabase``"
        }
        if (-not (Test-MySqlTableExists -CnfPath $cnf -Schema $dbName -Table $t)) {
            Write-Error "Fail-fast: table ``$t`` missing in target DB ``$dbName`` (run migrations first?)"
        }
    }

    $copySqlPath = Join-Path $env:TEMP ("rentbase_restore_copy_{0}.sql" -f [Guid]::NewGuid().ToString('n'))
    $sb = [System.Text.StringBuilder]::new()
    [void]$sb.AppendLine('SET FOREIGN_KEY_CHECKS=0;')
    foreach ($t in $tableNames) {
        [void]$sb.AppendLine("DELETE FROM ``$dbName``.``$t``;")
    }
    foreach ($t in $tableNames) {
        [void]$sb.AppendLine("INSERT INTO ``$dbName``.``$t`` SELECT * FROM ``$SrcDatabase``.``$t``;")
    }
    [void]$sb.AppendLine('SET FOREIGN_KEY_CHECKS=1;')
    [IO.File]::WriteAllText($copySqlPath, $sb.ToString(), [Text.UTF8Encoding]::new($false))

    Write-Host "Copying $($tableNames.Count) tables into [$dbName]..."
    $cmdCopy = "mysql --defaults-extra-file=`"$cnf`" < `"$copySqlPath`""
    cmd /c $cmdCopy
    if ($LASTEXITCODE -ne 0) {
        Write-Error 'Table copy batch failed (see mysql error above)'
    }
    Remove-Item -LiteralPath $copySqlPath -Force -ErrorAction SilentlyContinue

    # --- redirects check ---
    if ($tableNames -contains 'redirects') {
        $rq = @"
SELECT COUNT(*) FROM ``$dbName``.``redirects``
WHERE ``from_url`` REGEXP '^https?://' OR ``to_url`` REGEXP '^https?://'
LIMIT 1;
"@
        $cnt = & mysql --defaults-extra-file=$cnf -N -B -e $rq 2>&1
        if ($LASTEXITCODE -ne 0) { Write-Error "redirects check failed: $cnt" }
        $n = 0
        [void][int]::TryParse((($cnt | Select-Object -First 1).ToString().Trim()), [ref]$n)
        if ($n -gt 0 -and -not $AllowAbsoluteRedirects) {
            Write-Error 'redirects contains absolute http(s) URLs. Fix data or pass -AllowAbsoluteRedirects.'
        }
        if ($n -gt 0) {
            Write-Warning 'redirects contains absolute http(s) URLs (-AllowAbsoluteRedirects set).'
        }
    }

    Write-Host "DROP temporary database [$SrcDatabase]..."
    & mysql --defaults-extra-file=$cnf -e "DROP DATABASE IF EXISTS ``$SrcDatabase``;"
    if ($LASTEXITCODE -ne 0) { Write-Error 'drop src db failed' }

    if ($SqlPath -eq '' -and -not $KeepDownloadedDump -and $dumpLocal -ne '' -and (Test-Path -LiteralPath $dumpLocal)) {
        Remove-Item -LiteralPath $dumpLocal -Force -ErrorAction SilentlyContinue
    }
}
finally {
    Remove-Item -LiteralPath $cnf -Force -ErrorAction SilentlyContinue
}

Set-Location $root
Write-Host 'php artisan optimize:clear'
& php artisan optimize:clear --no-interaction
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host 'php artisan migrate --force'
& php artisan migrate --force --no-interaction
exit $LASTEXITCODE

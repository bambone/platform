#Requires -Version 5.1
#  .\scripts\export-changed-files-since-push.ps1
<#
.SYNOPSIS
  Writes one text file: full path + full file contents for each file changed since the remote (last push / upstream).

.DESCRIPTION
  By default compares the working tree (including the index) to the upstream branch (git @{u}):
  - unpushed commits;
  - uncommitted changes to tracked files.

  Untracked files are omitted unless -IncludeUntracked.

.PARAMETER OutputPath
  Output file path (default: scripts/export-changed-files-dumps/export-changed-files-dump.txt).

.PARAMETER BaseRef
  Explicit base ref (e.g. origin/main) if no upstream or you need another ref.

.PARAMETER IncludeUntracked
  Include untracked files (git ls-files --others --exclude-standard).

.PARAMETER Fetch
  Run git fetch before comparing.

.EXAMPLE
  .\scripts\export-changed-files-since-push.ps1

.EXAMPLE
  .\scripts\export-changed-files-since-push.ps1 -OutputPath C:\Temp\dump.txt -IncludeUntracked -Fetch
#>
param(
    [string] $OutputPath = '',
    [string] $BaseRef = '',
    [switch] $IncludeUntracked,
    [switch] $Fetch
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$root = Split-Path -Parent $scriptDir
Set-Location -LiteralPath $root

if ($Fetch) {
    git fetch --quiet 2>$null
}

function Resolve-BaseRef {
    param([string] $Explicit)

    if ($Explicit) {
        $hash = git rev-parse -q --verify $Explicit 2>$null
        if ($LASTEXITCODE -ne 0 -or -not $hash) {
            Write-Error "Invalid or missing BaseRef: $Explicit"
        }
        return $Explicit
    }

    $u = '@{u}'
    $upHash = git rev-parse -q --verify $u 2>$null
    if ($LASTEXITCODE -eq 0 -and $upHash) {
        return $u
    }

    $branch = git rev-parse --abbrev-ref HEAD 2>$null
    if (-not $branch -or $branch -eq 'HEAD') {
        Write-Error 'Cannot determine current branch. Pass -BaseRef (e.g. origin/main).'
    }

    $originBranch = "origin/$branch"
    $obHash = git rev-parse -q --verify $originBranch 2>$null
    if ($LASTEXITCODE -eq 0 -and $obHash) {
        Write-Warning "No upstream (@{u}). Using $originBranch. Set upstream: git push -u origin $branch"
        return $originBranch
    }

    Write-Error "No upstream (@{u}) and no $originBranch. Pass -BaseRef or configure remote/upstream."
}

$base = Resolve-BaseRef -Explicit $BaseRef

$defaultDumpDir = Join-Path $scriptDir 'export-changed-files-dumps'
if (-not $OutputPath) {
    if (-not (Test-Path -LiteralPath $defaultDumpDir)) {
        New-Item -ItemType Directory -Force -Path $defaultDumpDir | Out-Null
    }
    $OutputPath = Join-Path $defaultDumpDir 'export-changed-files-dump.txt'
} elseif (-not [System.IO.Path]::IsPathRooted($OutputPath)) {
    $OutputPath = Join-Path $root $OutputPath
}

$outDir = Split-Path -Parent $OutputPath
if ($outDir -and -not (Test-Path -LiteralPath $outDir)) {
    New-Item -ItemType Directory -Force -Path $outDir | Out-Null
}

# Tracked: working tree + index vs base (everything after remote state).
$tracked = @(git diff --name-only $base 2>$null | Where-Object { $_ -and $_.Trim() -ne '' })
if ($null -eq $tracked) { $tracked = @() }

$untracked = @()
if ($IncludeUntracked) {
    $untracked = @(git ls-files -o --exclude-standard 2>$null | Where-Object { $_ -and $_.Trim() -ne '' })
}

$allFiles = @($tracked + $untracked | Sort-Object -Unique)

$sb = New-Object System.Text.StringBuilder
$null = $sb.AppendLine('# export-changed-files-since-push')
$null = $sb.AppendLine("# repo: $root")
$null = $sb.AppendLine("# base: $base ($(git rev-parse -q --verify $base 2>$null))")
$null = $sb.AppendLine("# generated: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')")
$null = $sb.AppendLine('')

if ($allFiles.Count -eq 0) {
    $null = $sb.AppendLine('(no files to export)')
    [System.IO.File]::WriteAllText($OutputPath, $sb.ToString(), [System.Text.UTF8Encoding]::new($false))
    Write-Host "No differences vs $base. Wrote empty report: $OutputPath"
    exit 0
}

foreach ($rel in $allFiles) {
    $rel = $rel -replace '/', [System.IO.Path]::DirectorySeparatorChar
    $full = Join-Path $root $rel
    $null = $sb.AppendLine('======== FILE ========')
    $null = $sb.AppendLine($full)
    $null = $sb.AppendLine('======== BEGIN =======')

    if (Test-Path -LiteralPath $full -PathType Leaf) {
        try {
            $raw = [System.IO.File]::ReadAllText($full)
            $null = $sb.AppendLine($raw)
        } catch {
            $null = $sb.AppendLine("[read error: $($_.Exception.Message)]")
        }
    } elseif (Test-Path -LiteralPath $full -PathType Container) {
        $null = $sb.AppendLine('[directory: contents not inlined]')
    } else {
        # Deleted or only in git: try last committed blob at HEAD.
        $gitPath = $rel -replace '\\', '/'
        $fromHead = git show "HEAD:$gitPath" 2>$null
        if ($LASTEXITCODE -eq 0 -and $null -ne $fromHead) {
            $null = $sb.AppendLine('[file missing on disk; last committed version from HEAD below]')
            $null = $sb.AppendLine($fromHead)
        } else {
            $null = $sb.AppendLine('[file missing on disk; could not read from HEAD]')
        }
    }

    $null = $sb.AppendLine('======== END =======')
    $null = $sb.AppendLine('')
}

$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($OutputPath, $sb.ToString(), $utf8NoBom)

Write-Host "Done: $($allFiles.Count) file(s) -> $OutputPath"

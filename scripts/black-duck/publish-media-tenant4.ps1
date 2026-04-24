# Sync WebP: proof, services, work grid (wg-01..25). Tenant 4.
# Also: media-catalog.json + PNG/MP4 proof assets referenced by the catalog (prod needs these on R2, not only WebP).
# After this script: upload the mirror (or storage/app/public/tenants/4/...) to R2, then on prod:
#   php artisan tenant:black-duck:refresh-content blackduck --force
# Hero: site/brand/hero-1916.webp is NOT filled from proof by default — put your branded splash there by hand
# (or pass -HeroFromProof8 to copy XXXL (8) as a one-off test only).
param(
    [switch]$HeroFromProof8
)

$ErrorActionPreference = "Stop"
$srcProof = "C:\OSPanel\home\rentbase-media\tenants\4\public\site\brand\proof"
$mediaBrand = "C:\OSPanel\home\rentbase-media\tenants\4\public\site\brand"
$localBrand = "C:\OSPanel\home\rentbase.local\storage\app\public\tenants\4\public\site\brand"
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path
$catalogSrc = Join-Path $repoRoot "database\data\black_duck_tenant4_media_catalog.json"

function Ensure-Dir($p) { New-Item -ItemType Directory -Force -Path $p | Out-Null }

Ensure-Dir "$localBrand\proof"
Ensure-Dir "$localBrand\services"
Ensure-Dir "$mediaBrand\services"

Copy-Item -Path "$srcProof\*.webp" -Destination "$localBrand\proof\" -Force

if ($HeroFromProof8) {
    Copy-Item -Path "$srcProof\XXXL (8).webp" -Destination "$localBrand\hero-1916.webp" -Force
    Copy-Item -Path "$srcProof\XXXL (8).webp" -Destination "$mediaBrand\hero-1916.webp" -Force
}

$serviceMap = [ordered]@{
    "polirovka-kuzova" = "XXXL (21).webp"
    "keramika"         = "XXXL (25).webp"
    "ppf"              = "XXXL (11).webp"
    "tonirovka"        = "XXXL (32).webp"
    "himchistka-salona"= "XXXL (24).webp"
    "shumka"           = "XXXL (19).webp"
    "detejling-mojka"  = "XXXL (12).webp"
    "podkapotnaya-himchistka" = "XXXL (2).webp"
    "kozha-keramika"   = "XXXL (45).webp"
    "pdr"              = "XXXL (22).webp"
    "himchistka-kuzova"= "XXXL (7).webp"
    "himchistka-diskov"= "XXXL (15).webp"
    "antidozhd"        = "XXXL (16).webp"
    "bronirovanie-salona" = "XXXL (38).webp"
    "remont-skolov"    = "XXXL (14).webp"
    "restavratsiya-kozhi" = "XXXL (10).webp"
    "setki-radiatora"  = "XXXL (1).webp"
    "predprodazhnaya"  = "XXXL (43).webp"
    "vinil"            = "XXXL (26).webp"
}

foreach ($e in $serviceMap.GetEnumerator()) {
    $slug = $e.Key
    $fn = $e.Value
    $src = Join-Path $srcProof $fn
    if (-not (Test-Path $src)) { Write-Warning "Missing: $src"; continue }
    Copy-Item -Path $src -Destination "$localBrand\services\$slug.webp" -Force
    Copy-Item -Path $src -Destination "$mediaBrand\services\$slug.webp" -Force
}

$workGrid = @(
    @{n=1;  src="XXXL (1).webp";  },
    @{n=2;  src="XXXL (4).webp";  },
    @{n=3;  src="XXXL (8).webp";  },
    @{n=4;  src="XXXL (12).webp"; },
    @{n=5;  src="XXXL (42).webp"; },
    @{n=6;  src="XXXL (43).webp"; },
    @{n=7;  src="XXXL.webp";      },
    @{n=8;  src="XXXL (11).webp"; },
    @{n=9;  src="XXXL (21).webp"; },
    @{n=10; src="XXXL (22).webp"; },
    @{n=11; src="XXXL (24).webp"; },
    @{n=12; src="XXXL (26).webp"; },
    @{n=13; src="XXXL (32).webp"; },
    @{n=14; src="XXXL (37).webp"; },
    @{n=15; src="XXXL (38).webp"; },
    @{n=16; src="XXXL (44).webp"; },
    @{n=17; src="XXXL (45).webp"; },
    @{n=18; src="XXXL (13).webp"; },
    @{n=19; src="XXXL (14).webp"; },
    @{n=20; src="XXXL (25).webp"; },
    @{n=21; src="XXXL (28).webp"; },
    @{n=22; src="XXXL (31).webp"; },
    @{n=23; src="XXXL (23).webp"; },
    @{n=24; src="XXXL (35).webp"; },
    @{n=25; src="XXXL (46).webp"; }
)

foreach ($row in $workGrid) {
    $src = Join-Path $srcProof $row.src
    if (-not (Test-Path $src)) { Write-Warning "Skip wg $($row.n): $($row.src)"; continue }
    $base = "wg-{0:D2}.webp" -f $row.n
    Copy-Item -Path $src -Destination "$localBrand\proof\$base" -Force
    Copy-Item -Path $src -Destination "$mediaBrand\proof\$base" -Force
}

# Curated manifest keys (avoid t-*.jpg/mp4 stubs on CDN — they break /raboty hero and portfolio).
$proofBinariesForCatalog = @(
    "00-home-before.png",
    "01-home-after.png",
    "02-card-ppf.png",
    "23-service-gallery-ppf.png",
    "08-works-hero.mp4",
    "1008-works-hero-poster.png"
)
foreach ($fn in $proofBinariesForCatalog) {
    $src = Join-Path $srcProof $fn
    if (-not (Test-Path $src)) {
        Write-Warning "Missing catalog asset (copy manually to proof): $fn"
        continue
    }
    # Mirror path is already $srcProof — only sync into Laravel public tenant tree.
    Copy-Item -Path $src -Destination "$localBrand\proof\$fn" -Force
}

if (-not (Test-Path $catalogSrc)) {
    throw "Canonical catalog not found: $catalogSrc"
}
Copy-Item -Path $catalogSrc -Destination "$localBrand\media-catalog.json" -Force
Copy-Item -Path $catalogSrc -Destination "$mediaBrand\media-catalog.json" -Force

# hero-1916.webp = JPEG hero (w1916 slot); do not leave stale webp (e.g. from old proof import)
$regenHero = Join-Path $PSScriptRoot "regen-hero-1916-webp-tenant4.php"
if ((Test-Path "$mediaBrand\hero-1916.jpg") -and (Test-Path $regenHero)) {
  & php $regenHero
  if ($LASTEXITCODE -ne 0) { throw "regen-hero-1916-webp-tenant4.php failed (exit $LASTEXITCODE)" }
}

Write-Host "OK (mirror + Laravel public tenant 4). Sync to R2, then on prod: php artisan tenant:black-duck:refresh-content blackduck --force"

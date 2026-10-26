# Builds spokora-free zip for WordPress upload (Linux/cPanel-safe POSIX paths).
# Do NOT use Compress-Archive — Windows backslashes break unzip on the host.
#
# Layout: spokora-seo-toolkit/spokora-seo-toolkit.php
#
# Usage (from repo root):
#   powershell -ExecutionPolicy Bypass -File .\scripts\build-spokora-free-zip.ps1

$ErrorActionPreference = 'Stop'

$repoRoot   = Split-Path $PSScriptRoot -Parent
$sourceDir  = Join-Path $repoRoot 'spokora-seo-toolkit'
$versionFile = Join-Path $sourceDir 'includes\version.php'
$version = '0.0.0'
if (Test-Path -LiteralPath $versionFile) {
    $vf = Get-Content -LiteralPath $versionFile -Raw
    if ($vf -match "define\s*\(\s*'SPOKORA_VERSION'\s*,\s*'([^']+)'\s*\)") {
        $version = $Matches[1]
    }
}

$distDir = Join-Path $repoRoot 'dist'
if (-not (Test-Path -LiteralPath $distDir)) {
    New-Item -ItemType Directory -Path $distDir -Force | Out-Null
}

$zipPath = Join-Path $distDir ("spokora-seo-toolkit-$version.zip")

if (-not (Test-Path (Join-Path $sourceDir 'spokora-seo-toolkit.php'))) {
    throw "Source not found: $sourceDir\spokora-seo-toolkit.php"
}

function Write-SpokoraFreeZipPosix {
    param(
        [string] $Destination,
        [string] $Folder
    )

    if (Test-Path -LiteralPath $Destination) {
        Remove-Item -LiteralPath $Destination -Force
    }

    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $folderRoot = (Resolve-Path -LiteralPath $Folder).Path.TrimEnd('\')

    $archive = [System.IO.Compression.ZipFile]::Open(
        $Destination,
        [System.IO.Compression.ZipArchiveMode]::Create
    )

    try {
        Get-ChildItem -LiteralPath $Folder -Recurse -File -Force | ForEach-Object {
            $relative = $_.FullName.Substring($folderRoot.Length + 1).Replace('\', '/')
            $name = [System.IO.Path]::GetFileName($relative)
            if ($name.StartsWith('.') -or $name -like '*.md' -or $name -eq 'Thumbs.db' -or $name -eq '.DS_Store') {
                return
            }
            $entryName = 'spokora-seo-toolkit/' + $relative
            [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $archive,
                $_.FullName,
                $entryName,
                [System.IO.Compression.CompressionLevel]::Optimal
            )
        }
    } finally {
        $archive.Dispose()
    }

    $zip = [System.IO.Compression.ZipFile]::OpenRead($Destination)
    try {
        $script:names = @($zip.Entries | ForEach-Object { $_.FullName } | Sort-Object)
        if ($script:names.Count -lt 1) {
            throw 'Zip is empty.'
        }
        $backslash = $script:names | Where-Object { $_ -match '\\' }
        if ($backslash) {
            throw 'Zip contains backslash paths: ' + ($backslash | Select-Object -First 3 -Join ', ')
        }
        $main = $script:names | Where-Object { $_ -eq 'spokora-seo-toolkit/spokora-seo-toolkit.php' }
        if (-not $main) {
            throw 'Missing entry spokora-seo-toolkit/spokora-seo-toolkit.php'
        }
    } finally {
        $zip.Dispose()
    }
}

Write-SpokoraFreeZipPosix -Destination $zipPath -Folder $sourceDir
Write-Host "OK: $zipPath ($($names.Count) entries, v$version, POSIX paths spokora-seo-toolkit/)"

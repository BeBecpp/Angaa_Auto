param([Parameter(Mandatory=$true)][string]$ProjectRoot)
$ErrorActionPreference = 'Stop'
$bundleRoot = Split-Path -Parent $PSScriptRoot
$project = (Resolve-Path -LiteralPath $ProjectRoot).Path
if (!(Test-Path -LiteralPath (Join-Path $project 'artisan') -PathType Leaf) -or !(Test-Path -LiteralPath (Join-Path $project 'composer.json') -PathType Leaf)) {
    throw 'Expected an existing Laravel project with artisan and composer.json.'
}
$destination = Join-Path $project 'public/brand'
$relativeSources = @(
    'assets/brand/angaa-auto-logo-primary.png',
    'assets/brand/angaa-auto-logo-reversed-navy.png',
    'assets/brand/angaa-auto-mark.png',
    'assets/mascot/angaa-auto-mascot-welcome.png',
    'assets/mascot/angaa-auto-mascot-success.png'
)
$copies = @()
foreach ($relative in $relativeSources) {
    $source = Join-Path $bundleRoot $relative
    if (!(Test-Path -LiteralPath $source -PathType Leaf)) { throw "Missing source: $source" }
    $target = Join-Path $destination (Split-Path -Leaf $source)
    if (Test-Path -LiteralPath $target) {
        $item = Get-Item -LiteralPath $target -Force
        if ($item.Attributes -band [System.IO.FileAttributes]::ReparsePoint) { throw "Refusing linked destination: $target" }
        if (!(Test-Path -LiteralPath $target -PathType Leaf)) { throw "Not a regular file: $target" }
        if ((Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash -ne (Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash) {
            throw "Refusing to overwrite a different file: $target"
        }
    }
    $copies += [PSCustomObject]@{Source=$source;Target=$target}
}
New-Item -ItemType Directory -Path $destination -Force | Out-Null
foreach ($copy in $copies) {
    if (!(Test-Path -LiteralPath $copy.Target)) { Copy-Item -LiteralPath $copy.Source -Destination $copy.Target }
}
Write-Output "Installed/verified 5 selected ANGAA AUTO PNG assets in $destination"
Write-Output 'The brand board stays in the handoff. Application dependencies and integrations are unchanged.'

[CmdletBinding()]
param(
    [string] $OutputDirectory = (Join-Path (Resolve-Path (Join-Path $PSScriptRoot '..')).Path 'dist')
)

$ErrorActionPreference = 'Stop'
$sourceRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$pluginFile = Join-Path $sourceRoot 'wp-admin-agent.php'
$versionMatch = Select-String -LiteralPath $pluginFile -Pattern '^ \* Version:\s+(.+)$'
if (-not $versionMatch) {
    throw 'Unable to read plugin version.'
}
$version = $versionMatch.Matches[0].Groups[1].Value.Trim()

$outputRoot = [System.IO.Path]::GetFullPath($OutputDirectory)
New-Item -ItemType Directory -Path $outputRoot -Force | Out-Null
$stagingRoot = Join-Path $outputRoot '.staging-william-research-admin-agent'
$packageRoot = Join-Path $stagingRoot 'william-research-admin-agent'

if (Test-Path -LiteralPath $stagingRoot) {
    $resolved = (Resolve-Path -LiteralPath $stagingRoot).Path
    if (-not $resolved.StartsWith($outputRoot + [System.IO.Path]::DirectorySeparatorChar, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'Refusing to clean staging directory outside the output directory.'
    }
    Remove-Item -LiteralPath $resolved -Recurse -Force
}
New-Item -ItemType Directory -Path $packageRoot -Force | Out-Null

foreach ($directory in @('admin', 'assets', 'includes', 'tools')) {
    Copy-Item -LiteralPath (Join-Path $sourceRoot $directory) -Destination $packageRoot -Recurse
}
New-Item -ItemType Directory -Path (Join-Path $packageRoot 'knowledge-base') | Out-Null
Copy-Item -LiteralPath (Join-Path $sourceRoot 'knowledge-base\00-free-user-guide.md') -Destination (Join-Path $packageRoot 'knowledge-base\00-free-user-guide.md')

foreach ($file in @('LICENSE', 'readme.txt', 'uninstall.php', 'wp-admin-agent.php')) {
    Copy-Item -LiteralPath (Join-Path $sourceRoot $file) -Destination (Join-Path $packageRoot $file)
}

$zipPath = Join-Path $outputRoot "william-research-admin-agent-$version.zip"
if (Test-Path -LiteralPath $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
}
Compress-Archive -LiteralPath $packageRoot -DestinationPath $zipPath -CompressionLevel Optimal
Remove-Item -LiteralPath $stagingRoot -Recurse -Force

Write-Output $zipPath

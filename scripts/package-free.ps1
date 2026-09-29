[CmdletBinding()]
param(
    [string] $OutputDirectory,
    [string] $FreemiusSdkArchive
)

$ErrorActionPreference = 'Stop'
$sourceRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$freemiusSdkVersion = '2.13.4'
$freemiusSdkSha256 = '24CBBCA0D370AC1635F98FC977C1667C260854A9A6EB6C3103F0D73B6DE3531E'
$freemiusSdkUrl = "https://github.com/Freemius/wordpress-sdk/archive/refs/tags/$freemiusSdkVersion.zip"
if ([string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $OutputDirectory = Join-Path $sourceRoot 'dist'
}
$pluginFile = Join-Path $sourceRoot 'wp-admin-agent.php'
$pluginSource = Get-Content -LiteralPath $pluginFile -Raw
if ($pluginSource -notmatch "'has_addons'\s*=>\s*false") {
    throw 'Free package must explicitly disable Freemius add-on discovery.'
}
if ($pluginSource -match "'premium_slug'\s*=>") {
    throw 'Free package must not declare a premium slug.'
}
if ($pluginSource -notmatch "'addons'\s*=>\s*false" -or $pluginSource -notmatch "'pricing'\s*=>\s*false") {
    throw 'Free package must explicitly disable Freemius add-on and pricing menus.'
}
$versionMatch = Select-String -LiteralPath $pluginFile -Pattern '^ \* Version:\s+(.+)$'
if (-not $versionMatch) {
    throw 'Unable to read plugin version.'
}
$version = $versionMatch.Matches[0].Groups[1].Value.Trim()

$readmePath = Join-Path $sourceRoot 'readme.txt'
$readmeSource = Get-Content -LiteralPath $readmePath -Raw
$stableTagMatch = Select-String -LiteralPath $readmePath -Pattern '^Stable tag:\s+(.+)$'
if (-not $stableTagMatch -or $stableTagMatch.Matches[0].Groups[1].Value.Trim() -ne $version) {
    throw 'Plugin header version and readme stable tag must match.'
}
if ($readmeSource -notmatch '(?m)^Contributors:\s*williamduongrn\s*$') {
    throw 'The WordPress.org contributor username must be williamduongrn.'
}
foreach ($requiredDisclosure in @(
    'https://www.voxmedia.com/legal/terms-of-use',
    'https://www.voxmedia.com/legal/privacy-notice',
    'https://www.sciencedaily.com/terms.htm',
    'https://www.sciencedaily.com/privacy.htm'
)) {
    if (-not $readmeSource.Contains($requiredDisclosure)) {
        throw "Missing required external-service disclosure: $requiredDisclosure"
    }
}
if ($readmeSource -match '(?ms)= Does the plugin include AI usage\? =\s+No\.') {
    throw 'The AI usage FAQ must not contradict the plugin description.'
}

$settingsPage = Join-Path $sourceRoot 'admin\settings-page.php'
$settingsPageSource = Get-Content -LiteralPath $settingsPage -Raw
if ($settingsPageSource -match '<(?:script|style)(?:\s|>)') {
    throw 'Static Settings screen scripts and styles must be enqueued, not printed inline.'
}
foreach ($asset in @('admin\settings-page.js', 'admin\settings-page.css')) {
    if (-not (Test-Path -LiteralPath (Join-Path $sourceRoot $asset))) {
        throw "Missing enqueued Settings screen asset: $asset"
    }
}
foreach ($asset in @('assets\js\admin-agent.js', 'assets\css\admin-agent.css')) {
    if (-not (Test-Path -LiteralPath (Join-Path $sourceRoot $asset))) {
        throw "Missing compiled admin interface asset: $asset. Run npm run build first."
    }
}

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

$sdkArchivePath = $FreemiusSdkArchive
if ([string]::IsNullOrWhiteSpace($sdkArchivePath)) {
    $sdkArchivePath = Join-Path $stagingRoot "freemius-wordpress-sdk-$freemiusSdkVersion.zip"
    Invoke-WebRequest -Uri $freemiusSdkUrl -OutFile $sdkArchivePath
} else {
    $sdkArchivePath = (Resolve-Path -LiteralPath $sdkArchivePath).Path
}

$actualSdkHash = (Get-FileHash -LiteralPath $sdkArchivePath -Algorithm SHA256).Hash
if ($actualSdkHash -ne $freemiusSdkSha256) {
    throw "Freemius SDK checksum mismatch. Expected $freemiusSdkSha256, received $actualSdkHash."
}

$sdkExtractRoot = Join-Path $stagingRoot 'freemius-sdk'
Expand-Archive -LiteralPath $sdkArchivePath -DestinationPath $sdkExtractRoot
$sdkSource = Join-Path $sdkExtractRoot "wordpress-sdk-$freemiusSdkVersion"
$vendorRoot = Join-Path $packageRoot 'vendor'
New-Item -ItemType Directory -Path $vendorRoot -Force | Out-Null
Copy-Item -LiteralPath $sdkSource -Destination (Join-Path $vendorRoot 'freemius') -Recurse

$zipPath = Join-Path $outputRoot "william-research-admin-agent-$version.zip"
if (Test-Path -LiteralPath $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
}
Compress-Archive -LiteralPath $packageRoot -DestinationPath $zipPath -CompressionLevel Optimal
Remove-Item -LiteralPath $stagingRoot -Recurse -Force

Write-Output $zipPath

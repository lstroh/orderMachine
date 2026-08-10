# Build an installable WordPress plugin zip for Order Machine.
#
# Usage (from plugin root):
#   powershell -File bin/build-plugin-zip.ps1
#   powershell -File bin/build-plugin-zip.ps1 -Version 0.22.0
#
# Output: dist/orderMachine-<version>.zip
# Zip root folder: orderMachine/

[CmdletBinding()]
param(
	[string]$Version = '',
	[string]$PluginSlug = 'orderMachine',
	[string]$OutDir = ''
)

$ErrorActionPreference = 'Stop'

$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Set-Location $Root

# Only ship runtime plugin files (deny-lists are easy to get wrong on Windows).
$IncludeNames = @(
	'orderMachine.php',
	'uninstall.php',
	'admin',
	'includes'
)

$PluginFile = Join-Path $Root 'orderMachine.php'
if (-not (Test-Path $PluginFile)) {
	throw "Main plugin file not found: $PluginFile"
}

$header = Get-Content -Path $PluginFile -Raw
$headerVersion = $null
$constVersion = $null
if ($header -match '(?m)^\s*\*\s*Version:\s*(\S+)') {
	$headerVersion = $Matches[1]
}
if ($header -match "(?m)define\(\s*'SOM_VERSION'\s*,\s*'([^']+)'\s*\)") {
	$constVersion = $Matches[1]
}
if (-not $Version) {
	$Version = $headerVersion
}
if (-not $Version) {
	throw 'Could not read Version from orderMachine.php'
}
if ($headerVersion -and $constVersion -and ($headerVersion -ne $constVersion)) {
	throw "Version mismatch: header=$headerVersion SOM_VERSION=$constVersion"
}
if ($headerVersion -and ($headerVersion -ne $Version)) {
	throw "Requested version $Version does not match plugin header $headerVersion"
}
if ($constVersion -and ($constVersion -ne $Version)) {
	throw "Requested version $Version does not match SOM_VERSION $constVersion"
}

if (-not $OutDir) {
	$OutDir = Join-Path $Root 'dist'
}
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null

$StagingRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("om-zip-" + [guid]::NewGuid().ToString('N'))
$StagingPlugin = Join-Path $StagingRoot $PluginSlug
New-Item -ItemType Directory -Force -Path $StagingPlugin | Out-Null

try {
	foreach ($name in $IncludeNames) {
		$src = Join-Path $Root $name
		if (-not (Test-Path $src)) {
			throw "Required path missing: $name"
		}
		$dest = Join-Path $StagingPlugin $name
		if (Test-Path $src -PathType Container) {
			Copy-Item -Path $src -Destination $dest -Recurse -Force
		} else {
			Copy-Item -Path $src -Destination $dest -Force
		}
	}

	$ZipPath = Join-Path $OutDir "$PluginSlug-$Version.zip"
	if (Test-Path $ZipPath) {
		Remove-Item -LiteralPath $ZipPath -Force
	}

	Compress-Archive -Path $StagingPlugin -DestinationPath $ZipPath -CompressionLevel Optimal
	Write-Host "Created $ZipPath"
	Write-Host 'Install via Plugins -> Add New -> Upload Plugin'
}
finally {
	if (Test-Path $StagingRoot) {
		Remove-Item -LiteralPath $StagingRoot -Recurse -Force -ErrorAction SilentlyContinue
	}
}

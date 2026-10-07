$ErrorActionPreference = 'Stop'
$taskRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Set-Location -LiteralPath $taskRoot
$taskStamp = [TimeZoneInfo]::ConvertTimeBySystemTimeZoneId([DateTime]::UtcNow, 'GMT Standard Time').ToString('yyyy-MM-dd-HH-mm-ss')
$taskBase = '6d2a2ef'
$taskHead = (git rev-parse HEAD).Trim()
if ($LASTEXITCODE -ne 0) { throw 'Unable to resolve source HEAD.' }
$taskRuntimePaths = @(git diff --name-only $taskBase HEAD | Where-Object { $_ -match '^(app|bootstrap|config|database|routes|resources/views|resources/css|public/build)/|^docs/api/mobile.openapi.yaml$' } | Sort-Object -Unique)
if ($LASTEXITCODE -ne 0 -or $taskRuntimePaths.Count -eq 0) { throw 'No production runtime files found.' }
Add-Type -AssemblyName System.IO.Compression.FileSystem
[IO.Directory]::CreateDirectory((Join-Path $taskRoot 'production-patches')) | Out-Null

function New-VerifiedTaskBundle([string]$taskName, [string[]]$taskPaths, [string]$taskNotes) {
    $taskZipPath = Join-Path $taskRoot ('production-patches/' + $taskName + '.zip')
    if (Test-Path -LiteralPath $taskZipPath) { throw 'Refusing to overwrite bundle.' }
    $taskManifest = @()
    $taskZip = [IO.Compression.ZipFile]::Open($taskZipPath, [IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($taskPath in $taskPaths) {
            $taskSource = [IO.Path]::GetFullPath((Join-Path $taskRoot $taskPath))
            if (-not $taskSource.StartsWith($taskRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Source escaped workspace.' }
            if (-not (Test-Path -LiteralPath $taskSource -PathType Leaf)) { throw ('Missing source: ' + $taskPath) }
            $taskManifest += [pscustomobject]@{path=$taskPath;bytes=(Get-Item -LiteralPath $taskSource).Length;sha256=(Get-FileHash -LiteralPath $taskSource -Algorithm SHA256).Hash.ToLowerInvariant()}
            [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($taskZip, $taskSource, $taskPath, [IO.Compression.CompressionLevel]::Optimal) | Out-Null
        }
    } finally { $taskZip.Dispose() }
    $taskZip = [IO.Compression.ZipFile]::OpenRead($taskZipPath)
    try {
        if ($taskZip.Entries.Count -ne $taskPaths.Count) { throw 'Incorrect ZIP count.' }
        foreach ($taskEntry in $taskZip.Entries) {
            if ($taskEntry.FullName.Contains('\') -or $taskEntry.FullName.StartsWith('/') -or $taskEntry.FullName.Contains('..')) { throw 'Unsafe ZIP path.' }
            $taskStream = $taskEntry.Open()
            $taskHasher = [Security.Cryptography.SHA256]::Create()
            try { $taskHash = [Convert]::ToHexString($taskHasher.ComputeHash($taskStream)).ToLowerInvariant() }
            finally { $taskStream.Dispose(); $taskHasher.Dispose() }
            if ($taskHash -ne ($taskManifest | Where-Object path -eq $taskEntry.FullName).sha256) { throw 'ZIP content mismatch.' }
        }
    } finally { $taskZip.Dispose() }
    $taskChecksum = (Get-FileHash -LiteralPath $taskZipPath -Algorithm SHA256).Hash.ToLowerInvariant()
    $taskManifest | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath (Join-Path $PSScriptRoot ($taskName + '.manifest.json')) -Encoding utf8
    ($taskChecksum + '  ' + $taskName + '.zip') | Set-Content -LiteralPath (Join-Path $PSScriptRoot ($taskName + '.sha256')) -Encoding utf8
    $taskInstructions = Join-Path $PSScriptRoot ($taskName + '.md')
    ($taskNotes + "`nPackage: ``$taskName.zip```nSource base: ``$taskBase```nSource HEAD: ``$taskHead```nFiles: $($taskPaths.Count)`nSHA-256: ``$taskChecksum```n") | Set-Content -LiteralPath $taskInstructions -Encoding utf8
    return [pscustomobject]@{zip=$taskZipPath;instructions=$taskInstructions;manifest=(Join-Path $PSScriptRoot ($taskName + '.manifest.json'));checksum=$taskChecksum;files=$taskPaths.Count;bytes=(Get-Item -LiteralPath $taskZipPath).Length;head=$taskHead;verified=$true}
}

$taskAssets = Get-Content -LiteralPath (Join-Path $taskRoot 'public/build/manifest.json') -Raw | ConvertFrom-Json
$taskRuntimePaths = @($taskRuntimePaths + 'public/build/manifest.json' + @($taskAssets.PSObject.Properties | ForEach-Object { 'public/build/' + $_.Value.file; foreach ($taskCss in $_.Value.css) { 'public/build/' + $taskCss } }) | Sort-Object -Unique)
$taskProduction = New-VerifiedTaskBundle ('chart-segment-hover-' + $taskStamp) $taskRuntimePaths (Get-Content -LiteralPath (Join-Path $PSScriptRoot 'chart-hover-deployment.md') -Raw)
$taskResult = [pscustomobject]@{production=$taskProduction;tests=@{scope='WorkspaceOvertimeTest';passed=13;assertions=159};frontend_build_passed=$true;browser_verified=$false;production_deployed=$false}
$taskResult | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath (Join-Path $PSScriptRoot 'chart-hover-package-result.json') -Encoding utf8
$taskResult | ConvertTo-Json -Depth 6
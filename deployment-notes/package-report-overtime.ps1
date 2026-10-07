$ErrorActionPreference = 'Stop'
$taskRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Set-Location -LiteralPath $taskRoot
$taskStamp = [TimeZoneInfo]::ConvertTimeBySystemTimeZoneId([DateTime]::UtcNow, 'GMT Standard Time').ToString('yyyy-MM-dd-HH-mm-ss')
$taskBase = '76cbba3'
$taskHead = (git rev-parse HEAD).Trim()
if ($LASTEXITCODE -ne 0) { throw 'Unable to resolve source HEAD.' }
$taskRuntimePaths = @(git diff --name-only $taskBase HEAD | Where-Object { $_ -match '^(app|bootstrap|config|database|routes|resources/views)/|^docs/api/mobile.openapi.yaml$' } | Sort-Object -Unique)
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

$taskProduction = New-VerifiedTaskBundle ('report-overtime-clarification-' + $taskStamp) $taskRuntimePaths (Get-Content -LiteralPath (Join-Path $PSScriptRoot 'report-overtime-deployment.md') -Raw)
$taskFlutter = New-VerifiedTaskBundle ('flutter-report-overtime-handoff-' + $taskStamp) @('docs/api/mobile.openapi.yaml','docs/api/mobile-integration.md','docs/api/flutter-daily-overtime-prompt.md','docs/api/flutter-report-overtime-prompt.md') 'Flutter handoff only: extract into the Flutter project and paste docs/api/flutter-report-overtime-prompt.md. Read OpenAPI 2.2.0 and integration guide. Deploy the Laravel patch before enabling settings. Preserve existing auth and push flows. Do not upload this handoff as Laravel runtime files.'
$taskResult = [pscustomobject]@{production=$taskProduction;flutter=$taskFlutter;sqlite=@{passed=364;skipped=9;assertions=2993};mysql=@{passed=12;assertions=131;workbook_fixture_verified=$true};real_device_verified=$false;production_deployed=$false}
$taskResult | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath (Join-Path $PSScriptRoot 'report-overtime-package-result.json') -Encoding utf8
$taskResult | ConvertTo-Json -Depth 6

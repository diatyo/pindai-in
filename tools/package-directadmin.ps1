param(
    [Parameter(Mandatory = $true)][string]$Php,
    [Parameter(Mandatory = $true)][string]$ComposerPhar
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$projectRoot = Split-Path -Parent $PSScriptRoot
$buildRoot = Join-Path $projectRoot ('build\directadmin-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
if (Test-Path -LiteralPath $buildRoot) { throw 'Folder build sudah ada. Jalankan lagi dengan folder baru.' }
$payloadRoot = Join-Path $buildRoot 'payload'
$applicationRoot = Join-Path $payloadRoot 'pindai-app'
$publicRoot = Join-Path $payloadRoot 'public_html'
$utf8 = [System.Text.UTF8Encoding]::new($false)

function Write-Utf8([string]$Path, [string]$Content) {
    [System.IO.Directory]::CreateDirectory((Split-Path -Parent $Path)) | Out-Null
    [System.IO.File]::WriteAllText($Path, $Content, $utf8)
}

# Allowlist tracked source files; never copy local .env, uploads, SQL, or sessions.
$sourceFiles = & git -C $projectRoot ls-files -- app public
if ($LASTEXITCODE -ne 0) { throw 'Tidak dapat membaca daftar source Git.' }
foreach ($relativePath in $sourceFiles) {
    if ($relativePath -eq 'public/ui-review.html') { continue }
    if ($relativePath.StartsWith('app/')) {
        $target = Join-Path $applicationRoot $relativePath
    } elseif ($relativePath.StartsWith('public/assets/') -or $relativePath -in @('public/index.php', 'public/.htaccess', 'public/robots.txt', 'public/favicon.ico', 'public/Pindaiin.ico')) {
        $target = Join-Path $publicRoot $relativePath.Substring(7)
    } else { continue }
    [System.IO.Directory]::CreateDirectory((Split-Path -Parent $target)) | Out-Null
    Copy-Item -LiteralPath (Join-Path $projectRoot $relativePath) -Destination $target
}
foreach ($name in @('composer.json', 'composer.lock', 'LICENSE')) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $name) -Destination (Join-Path $applicationRoot $name)
}

$indexPath = Join-Path $publicRoot 'index.php'
$index = [System.IO.File]::ReadAllText($indexPath)
if (-not $index.Contains("../app/Config/Paths.php")) { throw 'Struktur index.php tidak dikenali.' }
$index = $index.Replace('../app/Config/Paths.php', '../pindai-app/app/Config/Paths.php').Replace("'8.2'", "'8.3'")
Write-Utf8 $indexPath $index

$spark = [System.IO.File]::ReadAllText((Join-Path $projectRoot 'spark'))
$spark = $spark.Replace("__DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR", "__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR")
$spark = $spark.Replace('../app/Config/Paths.php', '../pindai-app/app/Config/Paths.php').Replace("'8.2'", "'8.3'")
Write-Utf8 (Join-Path $applicationRoot 'spark') $spark

foreach ($directory in @('writable/cache', 'writable/logs', 'writable/session', 'writable/uploads', 'writable/debugbar')) {
    [System.IO.Directory]::CreateDirectory((Join-Path $applicationRoot $directory)) | Out-Null
}
foreach ($directory in @('uploads', 'qr')) {
    [System.IO.Directory]::CreateDirectory((Join-Path $publicRoot $directory)) | Out-Null
}
Write-Utf8 (Join-Path $applicationRoot '.htaccess') "Require all denied`n"
Write-Utf8 (Join-Path $applicationRoot '.env.example') @'
CI_ENVIRONMENT = production
app.baseURL = 'https://pindaiin.my.id/'
app.indexPage = ''
database.default.hostname = 'localhost'
database.default.port = 3306
database.default.database = 'ISI_NAMA_DATABASE_HOSTING'
database.default.username = 'ISI_USER_DATABASE_HOSTING'
database.default.password = 'ISI_PASSWORD_DATABASE_HOSTING'
database.default.DBDriver = MySQLi
database.default.DBDebug = false
cookie.secure = true
'@
Copy-Item -LiteralPath (Join-Path $projectRoot 'docs\DIRECTADMIN.md') -Destination (Join-Path $payloadRoot 'PETUNJUK-INSTALASI.md')

Push-Location $applicationRoot
try {
    & $Php -d extension=zip $ComposerPhar install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts --no-plugins
    if ($LASTEXITCODE -ne 0) { throw 'Instalasi dependensi produksi gagal.' }
    & $Php -d extension=zip $ComposerPhar check-platform-reqs --no-dev
    if ($LASTEXITCODE -ne 0) { throw 'Pemeriksaan kebutuhan PHP gagal.' }
} finally { Pop-Location }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archivePath = Join-Path $buildRoot 'pindai-in-directadmin-php83.zip'
& $Php -d extension=zip (Join-Path $PSScriptRoot 'zip-directadmin.php') $payloadRoot $archivePath
if ($LASTEXITCODE -ne 0) { throw 'Pembuatan ZIP dengan permission Unix gagal.' }
$archive = [System.IO.Compression.ZipFile]::OpenRead($archivePath)
try {
    $entryNames = @($archive.Entries | ForEach-Object { $_.FullName.Replace('\', '/') })
    if ($archive.Entries | Where-Object { $_.FullName.Contains('\') }) {
        throw 'ZIP memakai pemisah path Windows.'
    }
    foreach ($required in @('public_html/index.php', 'public_html/.htaccess', 'pindai-app/vendor/autoload.php', 'pindai-app/.env.example')) {
        if ($required -notin $entryNames) { throw "ZIP kehilangan berkas wajib: $required" }
    }
    if ($entryNames | Where-Object { $_ -match '(^|/)\.env$|\.sql$|(^|/)\.git/|^pindai-app/tests/' }) {
        throw 'ZIP berisi berkas yang tidak boleh didistribusikan.'
    }
} finally { $archive.Dispose() }
Write-Host "ZIP: $archivePath"
Write-Host "STAGING: $payloadRoot"
Write-Host ('SIZE_MB: ' + [Math]::Round((Get-Item -LiteralPath $archivePath).Length / 1MB, 2))

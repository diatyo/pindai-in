param(
    [string]$PhpRoot = 'C:/laragon/bin/php/php-8.4.25-Win32-vs17-x64',
    [string]$MySqlRoot = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64',
    [string]$ComposerPhar = 'C:/laragon/bin/composer/composer.phar',
    [string]$InnoCompiler,
    [switch]$SkipCompile,
    [switch]$SourceOnly
)
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot
& (Join-Path $PSScriptRoot 'build-icons.ps1')
$version = (Get-Content (Join-Path $root 'VERSION') -Raw).Trim()
if ($version -notmatch '^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.]+)?$') { throw 'VERSION tidak valid.' }
$releaseDir = Join-Path $root 'build/release'
$stage = Join-Path $root ('build/windows-stage-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
$payload = Join-Path $stage 'payload'
$source = Join-Path $stage "PindaiIn-source-$version"
New-Item -ItemType Directory -Path $releaseDir,$payload,$source -Force | Out-Null
Import-Module (Join-Path $PSScriptRoot 'SourceDistribution.psm1') -Force
function Copy-Source([string]$Destination) { Copy-PindaiSource $root $Destination }

Copy-Source $source
& (Join-Path $PhpRoot 'php.exe') (Join-Path $PSScriptRoot 'audit-public-source.php') $source
if($LASTEXITCODE -ne 0) { throw 'Source gagal audit privasi.' }
Copy-Source (New-Item -ItemType Directory -Path (Join-Path $payload 'app')).FullName
Copy-Item -LiteralPath (Join-Path $root 'VERSION') -Destination $payload
foreach ($file in @('Manage-Pindai.ps1','Install-Service.ps1','router.php','README-INSTALLER.txt','THIRD-PARTY.txt')) { Copy-Item -LiteralPath (Join-Path $root "installer/windows/$file") -Destination $payload }

if(-not $SourceOnly) {
Push-Location (Join-Path $payload 'app')
try {
    & (Join-Path $PhpRoot 'php.exe') $ComposerPhar install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts
    if ($LASTEXITCODE -ne 0) { throw 'Composer production install gagal.' }
} finally { Pop-Location }

$phpDest = Join-Path $payload 'runtime/php'
$mysqlDest = Join-Path $payload 'runtime/mysql'
New-Item -ItemType Directory -Path $phpDest,(Join-Path $phpDest 'ext'),(Join-Path $mysqlDest 'bin'),(Join-Path $payload 'licenses/php'),(Join-Path $payload 'licenses/mysql') -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $PhpRoot 'php.exe') -Destination $phpDest
Get-ChildItem -LiteralPath $PhpRoot -Filter '*.dll' | Copy-Item -Destination $phpDest
foreach ($extension in @('curl','exif','fileinfo','gd','intl','mbstring','mysqli','openssl','zip')) { Copy-Item -LiteralPath (Join-Path $PhpRoot "ext/php_$extension.dll") -Destination (Join-Path $phpDest 'ext') }
Copy-Item -LiteralPath (Join-Path $root 'installer/windows/php.ini') -Destination $phpDest
Get-ChildItem -LiteralPath $PhpRoot -File | Where-Object { $_.Name -match '(?i)license|notice|readme' } | Copy-Item -Destination (Join-Path $payload 'licenses/php')
foreach ($exe in @('mysqld.exe','mysql.exe','mysqladmin.exe','mysqldump.exe')) { Copy-Item -LiteralPath (Join-Path $MySqlRoot "bin/$exe") -Destination (Join-Path $mysqlDest 'bin') }
Get-ChildItem -LiteralPath (Join-Path $MySqlRoot 'bin') -Filter '*.dll' | Where-Object { $_.Name -notmatch 'debug' } | Copy-Item -Destination (Join-Path $mysqlDest 'bin')
foreach ($dir in @('share','docs')) { Copy-Item -LiteralPath (Join-Path $MySqlRoot $dir) -Destination $mysqlDest -Recurse }
$mysqlLib=Join-Path $MySqlRoot 'lib'
foreach($file in (Get-ChildItem -LiteralPath $mysqlLib -Recurse -File | Where-Object { $_.FullName -notmatch '[/\\]debug[/\\]' -and $_.Extension -notin @('.pdb','.lib') })) {
    $relative=$file.FullName.Substring($mysqlLib.Length).TrimStart('\','/')
    $target=Join-Path (Join-Path $mysqlDest 'lib') $relative
    New-Item -ItemType Directory -Path (Split-Path $target) -Force | Out-Null
    Copy-Item -LiteralPath $file.FullName -Destination $target
}
Copy-Item -LiteralPath (Join-Path $MySqlRoot 'LICENSE') -Destination $mysqlDest
Copy-Item -LiteralPath (Join-Path $MySqlRoot 'LICENSE') -Destination (Join-Path $payload 'licenses/mysql')
$compiler = Join-Path $env:WINDIR 'Microsoft.NET/Framework64/v4.0.30319/csc.exe'
& $compiler /nologo /target:winexe /reference:System.Windows.Forms.dll /reference:System.Web.Extensions.dll ('/win32icon:' + (Join-Path $root 'public/Pindaiin.ico')) ('/out:' + (Join-Path $payload 'PindaiIn.exe')) (Join-Path $root 'installer/windows/Launcher.cs')
if ($LASTEXITCODE -ne 0) { throw 'Launcher build gagal.' }
& $compiler /nologo /target:winexe /reference:System.ServiceProcess.dll /reference:System.Web.Extensions.dll ('/out:' + (Join-Path $payload 'PindaiService.exe')) (Join-Path $root 'installer/windows/Service.cs')
if ($LASTEXITCODE -ne 0) { throw 'Windows Service build gagal.' }
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = Join-Path $releaseDir "PindaiIn-source-$version.zip"
if (Test-Path -LiteralPath $archive) { Remove-Item -LiteralPath $archive }
[IO.Compression.ZipFile]::CreateFromDirectory($source,$archive,[IO.Compression.CompressionLevel]::Optimal,$true)
$zip = [IO.Compression.ZipFile]::OpenRead($archive)
try {
    $unsafe = @($zip.Entries | Where-Object { $_.FullName -match '(?i)(?:^|/)(?:\.env|uploads|qr|backups|session)/|(?:^|/)\.env$|\.(?:sql|pdf|docx|xlsx)$' -and $_.FullName -notmatch 'writable/(?:uploads|session)/index.html$' })
    if ($unsafe.Count -gt 0) { throw ('Source ZIP memuat file tidak diizinkan: ' + ($unsafe.FullName -join ', ')) }
} finally { $zip.Dispose() }
if (-not $SkipCompile -and -not $SourceOnly) {
    if (-not $InnoCompiler) { $InnoCompiler=Join-Path $root 'build/installer-tools/inno/ISCC.exe' }
    & $InnoCompiler ('/DPayloadDir=' + $payload) ('/DAppVersion=' + $version) (Join-Path $root 'installer/windows/PindaiIn.iss')
    if ($LASTEXITCODE -ne 0) { throw 'Installer compile gagal.' }
}
Write-Host ('STAGE=' + $stage)
Write-Host ('PAYLOAD=' + $payload)
Write-Host ('SOURCE_ZIP=' + $archive)

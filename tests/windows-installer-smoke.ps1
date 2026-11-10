param([Parameter(Mandatory=$true)][string]$Payload)
$ErrorActionPreference = 'Stop'
$root=Split-Path $PSScriptRoot
$sourcePayload=[IO.Path]::GetFullPath($Payload)
$Payload=Join-Path $root ('build/windows-program-' + [Guid]::NewGuid().ToString('N').Substring(0,8))
& (Join-Path $env:WINDIR 'System32/robocopy.exe') $sourcePayload $Payload /E /XJ /R:1 /W:1 /NFL /NDL /NJH /NJS /NP | Out-Null
if ($LASTEXITCODE -gt 7) { throw 'Cannot stage isolated program directory.' }
$data=Join-Path $root ('build/windows smoke-' + [Guid]::NewGuid().ToString('N').Substring(0,8))
$shell=Join-Path $env:WINDIR 'System32/WindowsPowerShell/v1.0/powershell.exe'
$inputFile=Join-Path $root 'build/windows-smoke-input.json'
$utf8=New-Object Text.UTF8Encoding($false)
$setup=@{school_name='Sekolah Uji Instalasi';full_name='Admin Uji';username='admin.uji';password='Only-Test-Init-2026!'}
[IO.File]::WriteAllText($inputFile,($setup | ConvertTo-Json -Compress),$utf8)
function Launch([string]$Mode='Start') {
    & $shell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $Payload 'Manage-Pindai.ps1') -InstallRoot $Payload -DataRoot $data -Headless -NoBrowser -SetupInputFile $inputFile -Action $Mode
    if ($LASTEXITCODE -ne 0) { throw "Launcher failed: $Mode" }
}
function Query([string]$Sql) {
    $output=& (Join-Path $Payload 'runtime/mysql/bin/mysql.exe') ('--defaults-extra-file=' + (Join-Path $data 'config/mysql-client.ini')) --batch --skip-column-names -e $Sql
    if ($LASTEXITCODE -ne 0) { throw 'Fixture query failed.' }
    return ($output -join "`n").Trim()
}
$versionPath=Join-Path $Payload 'VERSION'
$version=Get-Content -LiteralPath $versionPath -Raw
try {
    Launch
    $state=Get-Content (Join-Path $data 'config/desktop.json') -Raw | ConvertFrom-Json
    if (-not $state.initialized) { throw 'Initializer did not finish.' }
    if ((Query 'SELECT COUNT(*) FROM pindai_in.users') -ne '1') { throw 'Unexpected demo accounts.' }
    foreach ($table in @('items','borrowers','loans','damage_reports')) {
        if ((Query "SELECT COUNT(*) FROM pindai_in.$table") -ne '0') { throw "Unexpected operational data: $table" }
    }
    if ((Query "SELECT setting_value FROM pindai_in.settings WHERE setting_key='school_name'") -ne $setup.school_name) { throw 'Wrong school identity.' }
    if ((Query 'SELECT COUNT(*) FROM pindai_in.item_references') -lt 1) { throw 'No default references.' }
    $url='http://127.0.0.1:'+$state.web_port+'/'
    $login=Invoke-WebRequest ($url+'login') -UseBasicParsing
    if ($login.StatusCode -ne 200 -or $login.Content -notmatch 'csrf_test_name') { throw 'Login did not load.' }
    foreach ($route in @('.env','assets/../.env','uploads/not-allowed.png')) {
        try { $response=Invoke-WebRequest ($url+$route) -UseBasicParsing -MaximumRedirection 0 -ErrorAction Stop; if ($response.StatusCode -eq 200) { throw 'Protected file route returned HTTP 200.' } } catch {
            if ($_.Exception.Message -eq 'Protected file route returned HTTP 200.') { throw }
        }
    }
    # Test the actual login and report/settings screens in a production-vendor build.
    $env:PINDAI_DESKTOP_TEST_URL=$url
    & node (Join-Path $PSScriptRoot 'windows-browser-smoke.cjs')
    if ($LASTEXITCODE -ne 0) { throw 'Desktop browser test failed.' }
    $userHash=Query "SELECT password_hash FROM pindai_in.users WHERE username='admin.uji'"
    # Retry must not initialize a second time or reset accounts.
    Launch
    if ((Query 'SELECT COUNT(*) FROM pindai_in.users') -ne '1') { throw 'Restart changed accounts.' }
    $env:PINDAI_DATA_ROOT=$data
    $env:PHPRC=Join-Path $data 'config/php.ini'
    $ErrorActionPreference='Continue'
    $setup | ConvertTo-Json -Compress | & (Join-Path $Payload 'runtime/php/php.exe') (Join-Path $Payload 'app/spark') install:initialize *> (Join-Path $data 'initialize-again.log')
    $initializerExit=$LASTEXITCODE
    $ErrorActionPreference='Stop'
    if ($initializerExit -eq 0) { throw 'Initializer should refuse an occupied database.' }
    if ((Query "SELECT password_hash FROM pindai_in.users WHERE username='admin.uji'") -cne $userHash) { throw 'Initializer changed existing account.' }
    Launch Stop
    [IO.File]::WriteAllText((Join-Path $data 'writable/uploads/preserve-me.txt'),'fixture-only',$utf8)
    # Exercise a version upgrade with real backup and a no-op migration.
    [IO.File]::WriteAllText($versionPath,'0.9.0-beta.smoketest',$utf8)
    Launch
    if ((Query 'SELECT COUNT(*) FROM pindai_in.users') -ne '1') { throw 'Upgrade changed accounts.' }
    $backups=@(Get-ChildItem (Join-Path $data 'backups') -Directory)
    if ($backups.Count -ne 1 -or -not (Test-Path (Join-Path $backups[0].FullName 'database.sql')) -or -not (Test-Path (Join-Path $backups[0].FullName 'uploads/preserve-me.txt'))) { throw 'Upgrade backup incomplete.' }
    Launch Stop
    $state=Get-Content (Join-Path $data 'config/desktop.json') -Raw | ConvertFrom-Json
    if ($state.mysql_pid -ne 0 -or $state.web_pid -ne 0) { throw 'Shutdown state incorrect.' }
    Write-Host 'PASS fresh install, empty operational database, real login, repeat initialization refusal, restart, upgrade backup, and shutdown.'
    Write-Host ('TEST_DATA='+$data)
} finally {
    [IO.File]::WriteAllText($versionPath,$version,$utf8)
    try { Launch Stop } catch { Write-Warning $_ }
    if (Test-Path $inputFile) { Remove-Item -LiteralPath $inputFile }
    Remove-Item Env:PINDAI_DATA_ROOT -ErrorAction SilentlyContinue
    Remove-Item Env:PHPRC -ErrorAction SilentlyContinue
}

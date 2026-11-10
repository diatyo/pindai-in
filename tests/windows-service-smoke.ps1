param([Parameter(Mandatory=$true)][string]$Payload,[Parameter(Mandatory=$true)][string]$ResultFile)
$ErrorActionPreference='Stop'
$identity=[Security.Principal.WindowsIdentity]::GetCurrent()
if(-not (New-Object Security.Principal.WindowsPrincipal($identity)).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Service smoke test requires administrator.' }
$root=Split-Path $PSScriptRoot
$token=[Guid]::NewGuid().ToString('N').Substring(0,8)
$program=Join-Path $root "build/service-program-$token"
$data=Join-Path $root "build/service-data-$token"
$name="PindaiInSmoke$token"
$shell=Join-Path $env:WINDIR 'System32/WindowsPowerShell/v1.0/powershell.exe'
$sc=Join-Path $env:WINDIR 'System32/sc.exe'
$utf8=New-Object Text.UTF8Encoding($false)
$log=Join-Path $root "build/service-smoke-$token.log"
$passed=$false
Start-Transcript -Path $log | Out-Null
function Manager([string]$Mode) {
    & $shell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $program 'Manage-Pindai.ps1') -InstallRoot $program -DataRoot $data -Headless -NoBrowser -SetupInputFile (Join-Path $program 'setup-fixture.json') -Action $Mode
    if($LASTEXITCODE -ne 0) { throw "Manager failed: $Mode" }
}
function Wait-Health {
    $state=Get-Content (Join-Path $data 'config/desktop.json') -Raw | ConvertFrom-Json
    for($i=0;$i -lt 90;$i++) {
        try { $health=Invoke-RestMethod ('http://127.0.0.1:'+$state.web_port+'/_desktop/health') -TimeoutSec 1; if($health.instance -eq $state.instance) { return } } catch {}
        Start-Sleep -Seconds 1
    }
    throw 'Service did not become healthy.'
}
try {
    & (Join-Path $env:WINDIR 'System32/robocopy.exe') $Payload $program /E /XJ /R:1 /W:1 /NFL /NDL /NJH /NJS /NP | Out-Null
    if($LASTEXITCODE -gt 7) { throw 'Cannot stage isolated service program.' }
    [IO.File]::WriteAllText((Join-Path $program 'setup-fixture.json'),(@{school_name='Sekolah Uji Instalasi';full_name='Admin Uji';username='admin.uji';password='Only-Test-Init-2026!'} | ConvertTo-Json -Compress),$utf8)
    Manager Start
    Manager Stop
    Remove-Item -LiteralPath (Join-Path $program 'setup-fixture.json')
    # Grant only read/execute to LocalService on this isolated code copy.
    & (Join-Path $env:WINDIR 'System32/icacls.exe') $program /grant '*S-1-5-19:(OI)(CI)RX' /T /Q | Out-Null
    if($LASTEXITCODE -ne 0) { throw 'Cannot grant service runtime read access.' }
    $binary='"'+(Join-Path $program 'PindaiService.exe')+'" --name '+$name+' --data "'+$data+'"'
    & $sc create $name 'binPath=' $binary 'start=' 'auto' 'obj=' 'NT AUTHORITY\LocalService' | Out-Null
    if($LASTEXITCODE -ne 0) { throw 'Cannot create isolated service.' }
    Start-Service $name
    Wait-Health
    $service=Get-CimInstance Win32_Service -Filter "Name='$name'"
    if($service.StartMode -ne 'Auto' -or $service.StartName -ne 'NT AUTHORITY\LocalService') { throw 'Incorrect service startup/account.' }
    $state=Get-Content (Join-Path $data 'config/desktop.json') -Raw | ConvertFrom-Json
    $env:PINDAI_DESKTOP_TEST_URL='http://127.0.0.1:'+$state.web_port+'/'
    & node (Join-Path $PSScriptRoot 'windows-browser-smoke.cjs')
    if($LASTEXITCODE -ne 0) { throw 'Service browser test failed.' }
    $web=Get-CimInstance Win32_Process -Filter ('ProcessId='+$state.web_pid)
    if($web.ExecutablePath -ne (Join-Path $program 'runtime/php/php.exe') -or -not $web.CommandLine.Contains((Join-Path $program 'router.php'))) { throw 'Refusing to stop unrelated process.' }
    Stop-Process -Id $state.web_pid -Force
    Start-Sleep -Seconds 35
    Wait-Health
    Stop-Service $name
    (Get-Service $name).WaitForStatus([ServiceProcess.ServiceControllerStatus]::Stopped,[TimeSpan]::FromMinutes(4))
    Start-Service $name
    Wait-Health
    $passed=$true
    Write-Host 'PASS SCM Automatic/LocalService, browser login/PDF, child recovery, stop/start, data retention.'
} catch { Write-Host $_.Exception.ToString() } finally {
    $service=Get-Service $name -ErrorAction SilentlyContinue
    if($service) {
        try { if($service.Status -ne 'Stopped') { Stop-Service $name; $service.WaitForStatus([ServiceProcess.ServiceControllerStatus]::Stopped,[TimeSpan]::FromMinutes(4)) } } catch { Write-Host $_ }
        & $sc delete $name | Out-Null
    }
    Stop-Transcript | Out-Null
    [IO.File]::WriteAllText($ResultFile,(@{passed=$passed;log=$log;data=$data;program=$program;service=$name} | ConvertTo-Json),$utf8)
}
if(-not $passed) { exit 1 }

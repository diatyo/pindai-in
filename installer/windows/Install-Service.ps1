param([ValidateSet('Install','Stop','Uninstall')][string]$Action='Install')
$ErrorActionPreference='Stop'
$identity=[Security.Principal.WindowsIdentity]::GetCurrent()
if(-not (New-Object Security.Principal.WindowsPrincipal($identity)).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Jalankan penyiapan dengan izin administrator Windows.' }
$name='PindaiIn'
$shell=Join-Path $env:WINDIR 'System32/WindowsPowerShell/v1.0/powershell.exe'
$manager=Join-Path $PSScriptRoot 'Manage-Pindai.ps1'
$dataRoot=Join-Path $env:ProgramData 'PindaiIn/data'
$service=Get-Service -Name $name -ErrorAction SilentlyContinue
if ($service -and $service.Status -ne 'Stopped') {
    Stop-Service -Name $name -ErrorAction Stop
    $service.WaitForStatus([ServiceProcess.ServiceControllerStatus]::Stopped,[TimeSpan]::FromMinutes(4))
}
if($Action -in @('Stop','Uninstall')) {
    & $shell -NoProfile -ExecutionPolicy Bypass -File $manager -Action Stop -Headless
    if($LASTEXITCODE -ne 0) { throw 'Server lokal belum dapat dihentikan.' }
    if($Action -eq 'Uninstall' -and $service) {
        & (Join-Path $env:WINDIR 'System32/sc.exe') delete $name | Out-Null
        if($LASTEXITCODE -ne 0) { throw 'Service belum dapat dihapus.' }
    }
    if($Action -eq 'Uninstall') {
        $qr=Join-Path $PSScriptRoot 'app/public/qr'
        if(Test-Path -LiteralPath $qr) {
            $link=Get-Item -LiteralPath $qr
            if($link.LinkType -eq 'Junction' -and [string]::Equals([IO.Path]::GetFullPath([string]$link.Target[0]),(Join-Path $dataRoot 'writable/qr'),[StringComparison]::OrdinalIgnoreCase)) { [IO.Directory]::Delete($qr) }
        }
    }
    exit 0
}
# Setup runs elevated and asks for the first administrator only on a fresh install.
& $shell -NoProfile -ExecutionPolicy Bypass -File $manager -Action Start -NoBrowser
if($LASTEXITCODE -ne 0) { throw 'Penyiapan belum selesai. Jalankan Siapkan Pindai.in kembali.' }
& $shell -NoProfile -ExecutionPolicy Bypass -File $manager -Action Stop -Headless
if($LASTEXITCODE -ne 0) { throw 'Server lokal belum dapat dihentikan.' }
$state=Get-Content -LiteralPath (Join-Path $dataRoot 'config/desktop.json') -Raw | ConvertFrom-Json
$connection=Join-Path $env:ProgramData 'PindaiIn/connection.json'
$sharedRoot=New-Object IO.DirectoryInfo((Split-Path $dataRoot))
$acl=$sharedRoot.GetAccessControl([Security.AccessControl.AccessControlSections]::Access)
$acl.SetAccessRuleProtection($true,$false)
foreach($existing in @($acl.Access)) { [void]$acl.RemoveAccessRuleSpecific($existing) }
foreach($entry in @(@('S-1-5-18','FullControl'),@('S-1-5-32-544','FullControl'),@('S-1-5-32-545','ReadAndExecute'))) {
    $sid=New-Object Security.Principal.SecurityIdentifier($entry[0])
    $rule=New-Object Security.AccessControl.FileSystemAccessRule($sid,$entry[1],'ContainerInherit,ObjectInherit','None','Allow')
    $acl.AddAccessRule($rule)
}
$sharedRoot.SetAccessControl($acl)
[IO.File]::WriteAllText($connection,(@{url=('http://127.0.0.1:'+$state.web_port+'/')} | ConvertTo-Json),(New-Object Text.UTF8Encoding($false)))
$serviceExe=Join-Path $PSScriptRoot 'PindaiService.exe'
$sc=Join-Path $env:WINDIR 'System32/sc.exe'
if(-not $service) {
    & $sc create $name 'binPath=' ('"'+$serviceExe+'"') 'start=' 'auto' 'obj=' 'NT AUTHORITY\LocalService' 'DisplayName=' 'Pindai.in Local Server' | Out-Null
} else {
    & $sc config $name 'binPath=' ('"'+$serviceExe+'"') 'start=' 'auto' 'obj=' 'NT AUTHORITY\LocalService' | Out-Null
}
if($LASTEXITCODE -ne 0) { throw 'Pendaftaran Windows Service gagal. Gunakan izin administrator Windows.' }
& $sc description $name 'Server inventaris Pindai.in mandiri: PHP dan MySQL lokal, tanpa Laragon.' | Out-Null
& $sc failure $name 'reset=' '86400' 'actions=' 'restart/10000/restart/30000/restart/60000' | Out-Null
Start-Service -Name $name

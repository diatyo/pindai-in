param(
    [ValidateSet('Start','Stop')][string]$Action = 'Start',
    [string]$InstallRoot = $PSScriptRoot,
    [string]$DataRoot = (Join-Path $env:ProgramData 'PindaiIn/data'),
    [switch]$Headless,
    [string]$SetupInputFile,
    [switch]$NoBrowser
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2
$InstallRoot = [IO.Path]::GetFullPath($InstallRoot)
$DataRoot = [IO.Path]::GetFullPath($DataRoot)
$utf8 = New-Object Text.UTF8Encoding($false)
$php = Join-Path $InstallRoot 'runtime/php/php.exe'
$mysqlBin = Join-Path $InstallRoot 'runtime/mysql/bin'
$configDir = Join-Path $DataRoot 'config'
$statePath = Join-Path $configDir 'desktop.json'
$script:state = $null
$script:statusForm = $null
$script:statusText = $null
$mutex = New-Object Threading.Mutex($false, ('Local\PindaiIn-' + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($DataRoot)).Replace('/','_')))
if (-not $mutex.WaitOne(0)) { exit 0 }

function Write-Utf8([string]$Path, [string]$Value) {
    $temporary=$Path+'.'+[Guid]::NewGuid().ToString('N')+'.tmp'
    $previous=$temporary+'.previous'
    try {
        [IO.File]::WriteAllText($temporary,$Value,$utf8)
        if ([IO.File]::Exists($Path)) { [IO.File]::Replace($temporary,$Path,$previous) }
        else { [IO.File]::Move($temporary,$Path) }
    } finally {
        if ([IO.File]::Exists($temporary)) { [IO.File]::Delete($temporary) }
        if ([IO.File]::Exists($previous)) { [IO.File]::Delete($previous) }
    }
}
function Status([string]$Message) {
    if ($Headless) { Write-Host $Message }
    elseif ($script:statusText) { $script:statusText.Text = $Message; [Windows.Forms.Application]::DoEvents() }
}
function Protect-Data {
    # Credentials and school records are private to the installing Windows account.
    $directory = New-Object IO.DirectoryInfo($DataRoot)
    $acl = $directory.GetAccessControl([Security.AccessControl.AccessControlSections]::Access)
    $acl.SetAccessRuleProtection($true, $false)
    foreach ($existing in @($acl.Access)) { [void]$acl.RemoveAccessRuleSpecific($existing) }
    $sid = [Security.Principal.WindowsIdentity]::GetCurrent().User
    $flags = [Security.AccessControl.InheritanceFlags]'ContainerInherit,ObjectInherit'
    foreach ($identity in @($sid, (New-Object Security.Principal.SecurityIdentifier('S-1-5-18')), (New-Object Security.Principal.SecurityIdentifier('S-1-5-19')), (New-Object Security.Principal.SecurityIdentifier('S-1-5-32-544')))) {
        $rule = New-Object Security.AccessControl.FileSystemAccessRule($identity,'FullControl',$flags,'None','Allow')
        $acl.AddAccessRule($rule)
    }
    $directory.SetAccessControl($acl)
}
function New-Secret {
    $bytes = New-Object byte[] 32
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
    return ([BitConverter]::ToString($bytes)).Replace('-','').ToLowerInvariant()
}
function Free-Port([int]$Preferred) {
    for ($port=$Preferred; $port -lt ($Preferred+100); $port++) {
        $listener = New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback,$port)
        try { $listener.Start(); return $port } catch {} finally { $listener.Stop() }
    }
    throw 'Tidak ada port lokal tersedia. Tutup aplikasi yang tidak diperlukan lalu coba lagi.'
}
function Run-Tool([string]$Exe, [string]$Arguments, [string]$InputText = '') {
    $info = New-Object Diagnostics.ProcessStartInfo
    $info.FileName = $Exe
    $info.Arguments = $Arguments
    $info.WorkingDirectory = Join-Path $InstallRoot 'app'
    $info.UseShellExecute = $false
    $info.CreateNoWindow = $true
    $info.RedirectStandardInput = $true
    $info.RedirectStandardOutput = $true
    $info.RedirectStandardError = $true
    $process = [Diagnostics.Process]::Start($info)
    $out = $process.StandardOutput.ReadToEndAsync()
    $err = $process.StandardError.ReadToEndAsync()
    $process.StandardInput.Write($InputText)
    $process.StandardInput.Close()
    if (-not $process.WaitForExit(180000)) { $process.Kill(); throw 'Proses terlalu lama. Periksa log instalasi.' }
    $result = $out.Result + $err.Result
    if ($process.ExitCode -ne 0) {
        # CLI output may contain database details: keep it in the ACL-protected log.
        [IO.File]::AppendAllText((Join-Path $DataRoot 'desktop.log'), $result + "`r`n", $utf8)
        throw 'Proses lokal gagal. Lihat desktop.log dalam folder data Pindai.in.'
    }
    return $result
}
function Start-Hidden([string]$Exe, [string]$Arguments, [string]$Name) {
    return Start-Process -FilePath $Exe -ArgumentList $Arguments -WorkingDirectory (Split-Path $Exe) -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $DataRoot "$Name-out.log") -RedirectStandardError (Join-Path $DataRoot "$Name-error.log")
}
function Show-Setup {
    if ($SetupInputFile) { return Get-Content -LiteralPath $SetupInputFile -Raw | ConvertFrom-Json }
    if ($Headless) { throw 'Instalasi baru membutuhkan data admin.' }
    Add-Type -AssemblyName System.Windows.Forms
    Add-Type -AssemblyName System.Drawing
    $form = New-Object Windows.Forms.Form
    $form.Text = 'Selamat datang di Pindai.in'
    $form.Size = New-Object Drawing.Size(500,525)
    $form.StartPosition = 'CenterScreen'
    $form.FormBorderStyle = 'FixedDialog'
    $form.MaximizeBox = $false
    $form.Font = New-Object Drawing.Font('Segoe UI',10)
    $fields = @{}
    $heading = New-Object Windows.Forms.Label
    $heading.Text = 'Siapkan sekolah dan admin pertama'
    $heading.SetBounds(24,20,440,32)
    $heading.Font = New-Object Drawing.Font('Segoe UI',13,[Drawing.FontStyle]::Bold)
    $form.Controls.Add($heading)
    $definitions = @(@('school_name','Nama sekolah'),@('full_name','Nama lengkap admin'),@('username','Username admin'),@('password','Kata sandi (minimal 12 karakter)'),@('confirm','Ulangi kata sandi'))
    $top = 65
    foreach ($definition in $definitions) {
        $label = New-Object Windows.Forms.Label
        $label.Text = $definition[1]; $label.SetBounds(24,$top,430,24)
        $box = New-Object Windows.Forms.TextBox
        $box.SetBounds(24,($top+24),430,28)
        if ($definition[0] -in @('password','confirm')) { $box.UseSystemPasswordChar = $true; $box.MaxLength = 128 }
        elseif ($definition[0] -eq 'username') { $box.MaxLength = 60 }
        else { $box.MaxLength = 120 }
        $fields[$definition[0]] = $box
        $form.Controls.AddRange(@($label,$box)); $top += 70
    }
    $button = New-Object Windows.Forms.Button
    $button.Text = 'Siapkan & buka Pindai.in'; $button.SetBounds(24,425,430,38)
    $button.Add_Click({
        if (-not $fields.school_name.Text.Trim() -or -not $fields.full_name.Text.Trim() -or $fields.username.Text -notmatch '^[A-Za-z0-9._-]{3,60}$' -or $fields.password.Text.Length -lt 12 -or $fields.password.Text -cne $fields.confirm.Text) {
            [void][Windows.Forms.MessageBox]::Show('Lengkapi sekolah dan nama admin. Username 3-60 huruf/angka/titik/garis. Kata sandi minimal 12 karakter dan harus sama.','Periksa isian'); return
        }
        $form.DialogResult = [Windows.Forms.DialogResult]::OK; $form.Close()
    })
    $form.Controls.Add($button); $form.AcceptButton = $button
    if ($form.ShowDialog() -ne [Windows.Forms.DialogResult]::OK) { throw 'Penyiapan dibatalkan. Buka kembali Pindai.in untuk melanjutkan.' }
    return [PSCustomObject]@{ school_name=$fields.school_name.Text.Trim(); full_name=$fields.full_name.Text.Trim(); username=$fields.username.Text.Trim(); password=$fields.password.Text }
}
function Open-Status {
    if ($Headless) { return }
    Add-Type -AssemblyName System.Windows.Forms
    Add-Type -AssemblyName System.Drawing
    $script:statusForm = New-Object Windows.Forms.Form
    $script:statusForm.Text = 'Pindai.in'; $script:statusForm.Size = New-Object Drawing.Size(470,170)
    $script:statusForm.StartPosition = 'CenterScreen'; $script:statusForm.ControlBox = $false
    $script:statusText = New-Object Windows.Forms.Label
    $script:statusText.SetBounds(22,25,415,85); $script:statusText.Font = New-Object Drawing.Font('Segoe UI',11)
    $script:statusForm.Controls.Add($script:statusText); $script:statusForm.Show()
}
function Save-State { Write-Utf8 $statePath ($script:state | ConvertTo-Json) }
function Owned-Process([int]$Id, [string]$Exe, [string]$Marker) {
    if ($Id -le 0) { return $null }
    $process = Get-CimInstance Win32_Process -Filter "ProcessId=$Id" -ErrorAction SilentlyContinue
    if ($process -and $process.ExecutablePath -and [string]::Equals($process.ExecutablePath,$Exe,[StringComparison]::OrdinalIgnoreCase) -and $process.CommandLine.Contains($Marker)) { return $process }
    return $null
}
function Stop-Web {
    if ($script:state.web_pid -and (Owned-Process $script:state.web_pid $php (Join-Path $InstallRoot 'router.php'))) {
        Stop-Process -Id $script:state.web_pid -ErrorAction Stop
    }
    $script:state.web_pid = 0
}
function Client-Args { return ('--defaults-extra-file="' + (Join-Path $configDir 'mysql-client.ini') + '"') }
function Database-Ready {
    try {
        $actual = (Run-Tool (Join-Path $mysqlBin 'mysql.exe') ((Client-Args) + ' --batch --raw --skip-column-names -e "SELECT @@datadir"')).Trim()
        $expected = [IO.Path]::GetFullPath((Join-Path $DataRoot 'mysql')).TrimEnd('\','/')
        return [string]::Equals($actual.Replace('/','\').TrimEnd('\'),$expected,[StringComparison]::OrdinalIgnoreCase)
    } catch { return $false }
}

try {
    if (-not (Test-Path -LiteralPath $php)) { throw 'Runtime PHP tidak ditemukan. Pasang kembali installer lengkap.' }
    if ((Test-Path -LiteralPath $DataRoot) -and -not (Test-Path -LiteralPath (Join-Path $DataRoot 'pindai-data.marker'))) {
        if (@(Get-ChildItem -LiteralPath $DataRoot -Force).Count -gt 0) { throw 'Folder data berisi file dari aplikasi lain. Penyiapan dihentikan.' }
    }
    New-Item -ItemType Directory -Path $DataRoot -Force | Out-Null
    Protect-Data
    Write-Utf8 (Join-Path $DataRoot 'pindai-data.marker') 'Pindai.in desktop data v1'
    foreach ($folder in @('config','writable/cache','writable/logs','writable/session','writable/uploads','writable/branding','writable/qr','backups')) {
        New-Item -ItemType Directory -Path (Join-Path $DataRoot $folder) -Force | Out-Null
    }
    $env:PINDAI_DATA_ROOT = $DataRoot
    $phpIni = (Get-Content -LiteralPath (Join-Path $InstallRoot 'runtime/php/php.ini') -Raw).Replace('extension_dir="ext"', ('extension_dir="' + (Join-Path $InstallRoot 'runtime/php/ext').Replace('\','/') + '"'))
    Write-Utf8 (Join-Path $configDir 'php.ini') $phpIni
    $env:PHPRC = Join-Path $configDir 'php.ini'
    $version = (Get-Content (Join-Path $InstallRoot 'VERSION') -Raw).Trim()
    if (Test-Path -LiteralPath $statePath) { $script:state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json }
    if (-not $script:state) {
        if ($Action -eq 'Stop') { exit 0 }
        $setup = Show-Setup
        $script:state = [PSCustomObject]@{ schema=1; db_port=(Free-Port 33379); web_port=(Free-Port 8793); root_password=(New-Secret); app_password=(New-Secret); instance=(New-Secret); mysql_pid=0; web_pid=0; version=''; initialized=$false }
        Save-State
    } elseif (-not $script:state.initialized -and $Action -eq 'Start') { $setup = Show-Setup }
    $env:PINDAI_DESKTOP_INSTANCE = $script:state.instance
    $myConfig = Join-Path $configDir 'my.ini'
    $clientConfig = Join-Path $configDir 'mysql-client.ini'
    if ($Action -eq 'Stop') {
        Stop-Web
        if ($script:state.mysql_pid -and (Owned-Process $script:state.mysql_pid (Join-Path $mysqlBin 'mysqld.exe') $myConfig)) {
            [void](Run-Tool (Join-Path $mysqlBin 'mysqladmin.exe') ((Client-Args) + ' shutdown'))
        }
        $script:state.mysql_pid=0; Save-State
        if (-not $Headless) { Add-Type -AssemblyName System.Windows.Forms; [void][Windows.Forms.MessageBox]::Show('Server Pindai.in sudah dihentikan. Data tetap tersimpan.','Pindai.in') }
        exit 0
    }
    Open-Status
    Status 'Menyiapkan server lokal. Instalasi pertama dapat memerlukan beberapa menit...'
    $base = (Join-Path $InstallRoot 'runtime/mysql').Replace('\','/')
    $data = (Join-Path $DataRoot 'mysql').Replace('\','/')
    $errorLog = (Join-Path $DataRoot 'mysql-error.log').Replace('\','/')
    Write-Utf8 $myConfig @"
[mysqld]
basedir="$base"
datadir="$data"
port=$($script:state.db_port)
bind-address=127.0.0.1
mysqlx=OFF
skip-name-resolve
local-infile=OFF
secure-file-priv=NULL
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
innodb-buffer-pool-size=128M
log-error="$errorLog"
"@
    Write-Utf8 $clientConfig @"
[client]
host=127.0.0.1
port=$($script:state.db_port)
protocol=tcp
user=root
password=$($script:state.root_password)
[mysql]
connect-timeout=2
[mysqladmin]
connect-timeout=2
"@
    if (-not (Test-Path -LiteralPath (Join-Path $DataRoot 'mysql/mysql'))) {
        if ((Test-Path (Join-Path $DataRoot 'mysql')) -and @(Get-ChildItem (Join-Path $DataRoot 'mysql') -Force).Count -gt 0) {
            throw 'Inisialisasi MySQL sebelumnya belum selesai. Hubungi pengelola; folder data tidak dihapus otomatis.'
        }
        [void](Run-Tool (Join-Path $mysqlBin 'mysqld.exe') ('--defaults-file="' + $myConfig + '" --initialize-insecure'))
    }
    if (-not (Owned-Process $script:state.mysql_pid (Join-Path $mysqlBin 'mysqld.exe') $myConfig)) {
        $arguments = '--defaults-file="' + $myConfig + '"'
        if (-not $script:state.initialized) {
            $init = Join-Path $configDir 'mysql-initialize.sql'
            Write-Utf8 $init @"
ALTER USER 'root'@'localhost' IDENTIFIED BY '$($script:state.root_password)';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '$($script:state.root_password)';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
CREATE DATABASE IF NOT EXISTS pindai_in CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'pindai_app'@'127.0.0.1' IDENTIFIED BY '$($script:state.app_password)';
GRANT ALL PRIVILEGES ON pindai_in.* TO 'pindai_app'@'127.0.0.1';
"@
            $arguments += ' --init-file="' + $init + '"'
        }
        $dbProcess = Start-Hidden (Join-Path $mysqlBin 'mysqld.exe') $arguments 'mysql'
        $script:state.mysql_pid=$dbProcess.Id; Save-State
    }
    $ready = $false
    for ($attempt=0; $attempt -lt 60; $attempt++) {
        if (Database-Ready) { $ready=$true; break }
        if (-not (Owned-Process $script:state.mysql_pid (Join-Path $mysqlBin 'mysqld.exe') $myConfig)) { break }
        Start-Sleep -Milliseconds 500
        if (-not $Headless) { [Windows.Forms.Application]::DoEvents() }
    }
    if (-not $ready) { throw 'MySQL belum siap atau folder datanya tidak cocok. Periksa mysql-error.log; data tidak diubah.' }
    $initFile = Join-Path $configDir 'mysql-initialize.sql'
    if (Test-Path $initFile) { Remove-Item -LiteralPath $initFile }
    # Keep generated QR files outside the program directory too.
    $qrLink = Join-Path $InstallRoot 'app/public/qr'
    $qrTarget = Join-Path $DataRoot 'writable/qr'
    if (-not (Test-Path -LiteralPath $qrLink)) { New-Item -ItemType Junction -Path $qrLink -Target $qrTarget | Out-Null }
    $qrItem = Get-Item -LiteralPath $qrLink
    if ($qrItem.LinkType -ne 'Junction' -or -not [string]::Equals([IO.Path]::GetFullPath([string]$qrItem.Target[0]),$qrTarget,[StringComparison]::OrdinalIgnoreCase)) { throw 'Folder QR tidak cocok dengan folder data. Hubungi pengelola.' }
    $envFile = Join-Path $configDir '.env'
    $url = 'http://127.0.0.1:' + $script:state.web_port + '/'
    Write-Utf8 $envFile @"
CI_ENVIRONMENT = production
app.baseURL = '$url'
app.indexPage = ''
database.default.hostname = 127.0.0.1
database.default.port = $($script:state.db_port)
database.default.database = pindai_in
database.default.username = pindai_app
database.default.password = '$($script:state.app_password)'
database.default.DBDriver = MySQLi
database.default.DBDebug = false
session.cookieName = pindai_desktop_session
"@
    if ($script:state.version -ne $version) {
        Stop-Web
        if ($script:state.initialized) {
            Status 'Mencadangkan database dan foto sebelum pembaruan...'
            $backup = Join-Path $DataRoot ('backups/' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff'))
            New-Item -ItemType Directory -Path $backup | Out-Null
            [void](Run-Tool (Join-Path $mysqlBin 'mysqldump.exe') ((Client-Args) + ' --single-transaction --no-tablespaces --routines --triggers --result-file="' + (Join-Path $backup 'database.sql') + '" pindai_in'))
            foreach ($folder in @('uploads','branding','qr')) { Copy-Item -LiteralPath (Join-Path $DataRoot "writable/$folder") -Destination $backup -Recurse }
            Copy-Item -LiteralPath $configDir -Destination (Join-Path $backup 'config') -Recurse
        }
        # Clear only compiled caches in this application's dedicated writable folder.
        $cache = [IO.Path]::GetFullPath((Join-Path $DataRoot 'writable/cache'))
        if (-not $cache.StartsWith($DataRoot + [IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)) { throw 'Path cache tidak aman.' }
        Get-ChildItem -LiteralPath $cache -Force | Remove-Item -Recurse -Force
        Status 'Memeriksa struktur database...'
        [void](Run-Tool $php ('"' + (Join-Path $InstallRoot 'app/spark') + '" migrate --all'))
    }
    if (-not $script:state.initialized) {
        Status 'Membuat akun admin pertama...'
        # A retry after interruption must preserve an already committed first account.
        $users = (Run-Tool (Join-Path $mysqlBin 'mysql.exe') ((Client-Args) + ' --batch --skip-column-names -e "SELECT COUNT(*) FROM pindai_in.users"')).Trim()
        if ($users -eq '0') { [void](Run-Tool $php ('"' + (Join-Path $InstallRoot 'app/spark') + '" install:initialize') ($setup | ConvertTo-Json -Compress)) }
        $script:state.initialized=$true
        $setup=$null
    }
    $script:state.version=$version; Save-State
    $healthy=$false
    if (Owned-Process $script:state.web_pid $php (Join-Path $InstallRoot 'router.php')) {
        try { $health=Invoke-RestMethod ($url + '_desktop/health') -TimeoutSec 2; $healthy=($health.instance -eq $script:state.instance) } catch {}
    }
    if (-not $healthy) {
        Stop-Web
        $arguments='-S 127.0.0.1:' + $script:state.web_port + ' -t "' + (Join-Path $InstallRoot 'app/public') + '" "' + (Join-Path $InstallRoot 'router.php') + '"'
        $webProcess=Start-Hidden $php $arguments 'php'
        $script:state.web_pid=$webProcess.Id; Save-State
        for ($attempt=0; $attempt -lt 30; $attempt++) {
            try { $health=Invoke-RestMethod ($url + '_desktop/health') -TimeoutSec 1; if ($health.instance -eq $script:state.instance) { $healthy=$true; break } } catch {}
            Start-Sleep -Milliseconds 300
        }
    }
    if (-not $healthy) { throw 'Web lokal belum siap. Periksa php-error.log.' }
    Status ('Siap: ' + $url)
    if (-not $NoBrowser) { Start-Process $url }
} catch {
    if ($Headless) { Write-Error $_ -ErrorAction Continue; exit 1 }
    Add-Type -AssemblyName System.Windows.Forms
    [void][Windows.Forms.MessageBox]::Show($_.Exception.Message,'Pindai.in belum siap',[Windows.Forms.MessageBoxButtons]::OK,[Windows.Forms.MessageBoxIcon]::Error)
    exit 1
} finally {
    if ($script:statusForm) { $script:statusForm.Close(); $script:statusForm.Dispose() }
    $mutex.ReleaseMutex(); $mutex.Dispose()
}

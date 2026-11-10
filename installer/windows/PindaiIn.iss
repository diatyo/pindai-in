#ifndef PayloadDir
  #error PayloadDir must be supplied by tools/build-windows.ps1
#endif
#ifndef AppVersion
  #define AppVersion "0.9.0-beta.1"
#endif
[Setup]
AppId={{1B5FCE1A-5C5A-4974-8E85-302FB9A02A36}
AppName=Pindai.in
AppComments=SISTEM INFORMASI PEMINJAMAN DAN MONITORING INVENTARIS BARANG BERBASIS WEB DENGAN IDENTIFIKASI QR CODE
AppVersion={#AppVersion}
AppPublisher=Dimas Adi Prasetyo
AppPublisherURL=https://github.com/diatyo/pindai-in
DefaultDirName={autopf}\PindaiIn
DefaultGroupName=Pindai.in
PrivilegesRequired=admin
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
MinVersion=10.0
OutputDir=..\..\build\release
OutputBaseFilename=PindaiIn-Setup-{#AppVersion}-win64
SetupIconFile=..\..\public\Pindaiin.ico
UninstallDisplayIcon={app}\PindaiIn.exe
Compression=lzma2/normal
SolidCompression=yes
WizardStyle=modern
CloseApplications=yes
RestartApplications=no
SetupLogging=yes
InfoBeforeFile=README-INSTALLER.txt
DisableProgramGroupPage=yes
LicenseFile=THIRD-PARTY.txt

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Files]
Source: "{#PayloadDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "..\..\build\installer-tools\vc_redist.x64.exe"; Flags: dontcopy

[Icons]
Name: "{group}\Pindai.in"; Filename: "{app}\PindaiIn.exe"
Name: "{group}\Siapkan Pindai.in"; Filename: "{app}\PindaiIn.exe"; Parameters: "--configure"
Name: "{group}\Panduan Pindai.in"; Filename: "{app}\README-INSTALLER.txt"
Name: "{autodesktop}\Pindai.in"; Filename: "{app}\PindaiIn.exe"

[Run]
Filename: "{app}\PindaiIn.exe"; Parameters: "--configure"; Flags: waituntilterminated runhidden skipifsilent
Filename: "{app}\PindaiIn.exe"; Description: "Siapkan dan buka Pindai.in"; Flags: nowait postinstall skipifsilent

[UninstallRun]
Filename: "{sys}\WindowsPowerShell\v1.0\powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File ""{app}\Install-Service.ps1"" -Action Uninstall"; Flags: runhidden waituntilterminated; RunOnceId: "StopPindai"

[Code]
function PrepareToInstall(var NeedsRestart: Boolean): String;
var
  ExitCode: Integer;
  Installed: Cardinal;
begin
  Result := '';
  // Install the unmodified Microsoft prerequisite only when missing.
  if not RegQueryDWordValue(HKLM64, 'SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64', 'Installed', Installed) or (Installed <> 1) then
  begin
    ExtractTemporaryFile('vc_redist.x64.exe');
    if not ShellExec('runas', ExpandConstant('{tmp}\vc_redist.x64.exe'), '/install /quiet /norestart', '', SW_HIDE, ewWaitUntilTerminated, ExitCode) then
      Result := 'Visual C++ Runtime belum terpasang. Izinkan pemasangan prasyarat Microsoft lalu jalankan setup kembali.'
    else if (ExitCode <> 0) and (ExitCode <> 1638) and (ExitCode <> 3010) then
      Result := 'Pemasangan Visual C++ Runtime gagal. Kode: ' + IntToStr(ExitCode)
    else if ExitCode = 3010 then
      NeedsRestart := True;
  end;
  if (Result = '') and FileExists(ExpandConstant('{app}\Install-Service.ps1')) then
  begin
    if not Exec(ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe'), '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "' + ExpandConstant('{app}\Install-Service.ps1') + '" -Action Stop', '', SW_HIDE, ewWaitUntilTerminated, ExitCode) or (ExitCode <> 0) then
      Result := 'Server Pindai.in belum dapat dihentikan. Tutup aplikasi dan coba lagi sebelum memperbarui.';
  end;
end;

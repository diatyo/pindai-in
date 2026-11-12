#ifndef PayloadDir
  #error PayloadDir must be supplied by tools/build-windows.ps1
#endif
#ifndef AppVersion
  #define AppVersion "0.9.0-beta.1"
#endif
[Setup]
AppId={{1B5FCE1A-5C5A-4974-8E85-302FB9A02A36}
AppName=Pindai.in
AppComments=Sistem informasi peminjaman dan monitoring inventaris barang berbasis web dengan identifikasi QR Code
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
var
  ReinstallPage: TInputOptionWizardPage;
  RemoveDataOnUninstall: Boolean;

function PindaiDataDir: String;
begin
  Result := ExpandConstant('{commonappdata}\PindaiIn\data');
end;

function HasRetainedData: Boolean;
begin
  Result := FileExists(PindaiDataDir + '\pindai-data.marker') and
    not RegKeyExists(HKLM64, 'SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall\{1B5FCE1A-5C5A-4974-8E85-302FB9A02A36}_is1') and
    not RegKeyExists(HKLM, 'SYSTEM\CurrentControlSet\Services\PindaiIn');
end;

procedure InitializeWizard;
begin
  ReinstallPage := CreateInputOptionPage(wpSelectDir,
    'Siapkan Pindai.in setelah instal ulang', 'Pilih data yang akan digunakan',
    'Pindai.in menemukan data dari instalasi sebelumnya di komputer ini.', True, False);
  ReinstallPage.Add('Gunakan data lama dan masuk dengan akun yang sudah ada');
  ReinstallPage.Add('Siapkan dari awal dan simpan data lama sebagai arsip');
  ReinstallPage.SelectedValueIndex := 0;
end;

function ShouldSkipPage(PageID: Integer): Boolean;
begin
  Result := (PageID = ReinstallPage.ID) and (WizardSilent or not HasRetainedData);
end;

function IsPlainDirectory(const Directory: String): Boolean;
var
  Entry: TFindRec;
begin
  Result := False;
  if FindFirst(Directory, Entry) then
  begin
    Result := ((Entry.Attributes and $10) <> 0) and ((Entry.Attributes and $400) = 0);
    FindClose(Entry);
  end;
end;

function DataDirIsOwned(const DataDir: String): Boolean;
begin
  Result := IsPlainDirectory(ExtractFileDir(DataDir)) and IsPlainDirectory(DataDir) and
    FileExists(DataDir + '\pindai-data.marker');
end;

function ArchiveRetainedData: String;
var
  DataDir, ArchiveDir: String;
begin
  Result := '';
  DataDir := PindaiDataDir;
  if not DataDirIsOwned(DataDir) then
  begin
    Result := 'Lokasi data Pindai.in tidak dapat diarsipkan. Pilih Gunakan data lama untuk melanjutkan.';
    Exit;
  end;
  ArchiveDir := DataDir + '-sebelum-instal-ulang-' + GetDateTimeString('yyyymmdd-hhnnss', #0, #0);
  if RenameFile(DataDir, ArchiveDir) then
    Log('Data Pindai.in sebelumnya diarsipkan di ' + ArchiveDir)
  else
    Result := 'Data Pindai.in belum dapat diarsipkan. Tutup proses yang masih membuka folder data, lalu coba lagi.';
end;

function StopLocalServer: Boolean;
var
  ExitCode: Integer;
begin
  Result := Exec(ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe'),
    '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "' +
    ExpandConstant('{app}\Install-Service.ps1') + '" -Action Stop', '', SW_HIDE,
    ewWaitUntilTerminated, ExitCode) and (ExitCode = 0);
end;

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
    if not StopLocalServer then
      Result := 'Server Pindai.in belum dapat dihentikan. Tutup aplikasi dan coba lagi sebelum memperbarui.';
  end;
  if (Result = '') and not NeedsRestart and not WizardSilent and
    (ReinstallPage.SelectedValueIndex = 1) and HasRetainedData then
    Result := ArchiveRetainedData;
end;

function InitializeUninstall: Boolean;
var
  Choice: Integer;
begin
  Result := True;
  if UninstallSilent or not DataDirIsOwned(PindaiDataDir) then
    Exit;

  Choice := TaskDialogMsgBox('Data lokal Pindai.in',
    'Pilih apa yang ingin dilakukan pada database, akun, foto, dan cadangan di folder data aktif.',
    mbConfirmation, MB_YESNOCANCEL, ['Pertahankan data'#13#10'Gunakan kembali data dan akun ini saat instal ulang.',
     'Hapus data'#13#10'Hapus permanen data aktif. Cadangan Google Drive dan arsip instal ulang tetap tersedia.',
     'Batal'], 0);
  RemoveDataOnUninstall := Choice = IDNO;
  Result := (Choice = IDYES) or RemoveDataOnUninstall;
end;

procedure RemoveLocalData;
var
  DataDir, DeleteDir: String;
begin
  DataDir := PindaiDataDir;
  if not DataDirIsOwned(DataDir) or RegKeyExists(HKLM, 'SYSTEM\CurrentControlSet\Services\PindaiIn') then
  begin
    MsgBox('Data lokal masih tersimpan. Status layanan atau lokasi data perlu diperiksa.', mbError, MB_OK);
    Exit;
  end;

  DeleteDir := DataDir + '-hapus-' + GetDateTimeString('yyyymmdd-hhnnss', #0, #0);
  if not RenameFile(DataDir, DeleteDir) then
  begin
    MsgBox('Folder data masih tersimpan di ' + DataDir + '. Tutup aplikasi yang sedang memakai folder itu.', mbError, MB_OK);
    Exit;
  end;

  if not DataDirIsOwned(DeleteDir) or not DelTree(DeleteDir, True, True, True) then
    MsgBox('Penghapusan belum selesai. Data yang tersisa berada di ' + DeleteDir + '.', mbError, MB_OK);
end;

procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if not RemoveDataOnUninstall then
    Exit;

  if CurUninstallStep = usUninstall then
  begin
    if not StopLocalServer then
    begin
      RemoveDataOnUninstall := False;
      MsgBox('Data lokal masih tersimpan karena server Pindai.in belum dapat dihentikan.', mbError, MB_OK);
    end;
  end
  else if CurUninstallStep = usPostUninstall then
    RemoveLocalData;
end;

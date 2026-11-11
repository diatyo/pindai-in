# One allowlist is used by both the public repository and Windows/source packages.
$RootFiles = @('composer.json','composer.lock','spark','env','LICENSE','README.md','CONTRIBUTORS.md','VERSION','.gitignore','phpunit.dist.xml','SECURITY.md')
$Docs = @('INSTALL-SOURCE.md','INSTALL-WINDOWS.md','PENGATURAN-KOP-PDF.md','PUBLIC-SOURCE.md','BACKUP-PEMULIHAN.md','assets/alur-pindai.svg','assets/preview-dashboard.png','assets/preview-loan-mobile.png')
$Tools = @('build-windows.ps1','build-icons.ps1','SourceDistribution.psm1','audit-public-source.php','export-public-source.ps1','package-directadmin.ps1','zip-directadmin.php','generate-auth-upgrade-sql.php','generate-reference-upgrade-sql.php')
$Tests = @('accessory-browser-smoke.php','accessory-browser-smoke.cjs','accessory-test-router.php','school-letterhead-browser.cjs','borrower-generator-browser.cjs','windows-installer-smoke.ps1','windows-service-smoke.ps1','windows-browser-smoke.cjs','borrower-classes-browser.cjs','inventory-desktop-browser.cjs','item-duplicate-serial-browser.cjs','loan-tablet-browser.cjs','browser-runtime.cjs','drive-oauth-smoke.php','source-privacy-smoke.php','README.md')

function Get-PindaiSourceFiles([string]$Root) {
    $Root=[IO.Path]::GetFullPath($Root).TrimEnd('\','/')
    $paths=New-Object Collections.Generic.List[string]
    foreach($folder in @('app','public/assets','installer/windows','tests/unit','tests/_support','tests/js')) {
        foreach($file in (Get-ChildItem -LiteralPath (Join-Path $Root $folder) -Recurse -File)) {
            $relative=$file.FullName.Substring($Root.Length+1).Replace('\','/')
            if ($file.Name -like 'client_secret*.json') { continue }
            # Legacy mockup scripts contain prototype records and are unused by CI views.
            if($relative -in @('public/assets/js/app.js','public/assets/js/admin-master.js','public/assets/js/admin-ui.js','public/assets/js/kepala-sekolah-ui.js')) { continue }
            if($file.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "Source link tidak diizinkan: $relative" }
            $ancestor=$file.Directory
            while($ancestor -and $ancestor.FullName.Length -gt $Root.Length) {
                if($ancestor.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "Source link tidak diizinkan: $relative" }
                $ancestor=$ancestor.Parent
            }
            $paths.Add($relative)
        }
    }
    foreach($file in $RootFiles) { $paths.Add($file) }
    foreach($file in $Docs) { $paths.Add("docs/$file") }
    foreach($file in $Tools) { $paths.Add("tools/$file") }
    foreach($file in $Tests) { $paths.Add("tests/$file") }
    foreach($file in @('.htaccess','index.php','favicon.ico','Pindaiin.ico','robots.txt','site.webmanifest')) { $paths.Add("public/$file") }
    $paths.Add('writable/.htaccess')
    return $paths | Sort-Object -Unique
}

function Copy-PindaiSource([string]$Root,[string]$Destination) {
    $Root=[IO.Path]::GetFullPath($Root)
    $Destination=[IO.Path]::GetFullPath($Destination)
    if(Test-Path -LiteralPath $Destination) {
        if(@(Get-ChildItem -LiteralPath $Destination -Force).Count -ne 0) { throw 'Folder tujuan source harus kosong.' }
    }
    New-Item -ItemType Directory -Path $Destination -Force | Out-Null
    foreach($relative in (Get-PindaiSourceFiles $Root)) {
        $source=Join-Path $Root $relative
        if(-not (Test-Path -LiteralPath $source -PathType Leaf)) { throw "Source wajib tidak ditemukan: $relative" }
        $target=Join-Path $Destination $relative
        New-Item -ItemType Directory -Path (Split-Path $target) -Force | Out-Null
        Copy-Item -LiteralPath $source -Destination $target
    }
    foreach($folder in @('cache','logs','session','uploads')) {
        $path=Join-Path $Destination "writable/$folder"
        New-Item -ItemType Directory -Path $path -Force | Out-Null
        [IO.File]::WriteAllText((Join-Path $path 'index.html'),'<!doctype html><title>403</title>')
    }
}
Export-ModuleMember -Function Get-PindaiSourceFiles,Copy-PindaiSource

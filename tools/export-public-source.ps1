param([Parameter(Mandatory=$true)][string]$Destination,[Parameter(Mandatory=$true)][string]$Php,[string]$DenyFile)
$ErrorActionPreference='Stop'
$root=Split-Path $PSScriptRoot
Import-Module (Join-Path $PSScriptRoot 'SourceDistribution.psm1') -Force
Copy-PindaiSource $root $Destination
& $Php (Join-Path $PSScriptRoot 'audit-public-source.php') $Destination $DenyFile
if($LASTEXITCODE -ne 0) { throw 'Audit source publik gagal; jangan unggah folder hasil.' }
Write-Host ('PUBLIC_SOURCE='+[IO.Path]::GetFullPath($Destination))

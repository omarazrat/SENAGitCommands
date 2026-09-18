param(
    [Parameter(Mandatory = $true)][string]$ListFile,
    [string]$HostArg,
    [string]$UserArg,
    [string]$PwdArg
)

[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$ErrorActionPreference = 'Stop'

# ============================================================
#  SUBIDA FTP (InfinityFree) - PowerShell
#  Lee credenciales del entorno: FTP_HOST, FTP_USER, FTP_PWD.
#  (setEnv.bat las define antes de llamar a este script.)
# ============================================================

$ftpHost = $HostArg
$ftpUser = $UserArg
$ftpPwd  = $PwdArg
if ([string]::IsNullOrWhiteSpace($ftpHost) -and -not [string]::IsNullOrWhiteSpace($env:FTP_HOST)) { $ftpHost = [string]$env:FTP_HOST }
if ([string]::IsNullOrWhiteSpace($ftpUser) -and -not [string]::IsNullOrWhiteSpace($env:FTP_USER)) { $ftpUser = [string]$env:FTP_USER }
if ([string]::IsNullOrWhiteSpace($ftpPwd)  -and -not [string]::IsNullOrWhiteSpace($env:FTP_PWD))  { $ftpPwd  = [string]$env:FTP_PWD }
$useSsl  = $true
if ($env:FTP_SSL -and $env:FTP_SSL.Trim().ToUpper() -match '^(NO|0|FALSE)$') { $useSsl = $false }

$rootDir = '/htdocs'
if (-not [string]::IsNullOrWhiteSpace($env:FTP_DIR)) {
    $r = $env:FTP_DIR.Trim().TrimEnd('/')
    if ($r) { $rootDir = $r }
}

if ([string]::IsNullOrWhiteSpace($ftpHost) -or [string]::IsNullOrWhiteSpace($ftpUser)) {
    Write-Host ''
    Write-Host 'NO HAY CONFIGURACION COMPLETA (revisa setEnv.bat / configura las variables).'
    Write-Host ("  HOST=[" + $ftpHost + "]  USER=[" + $ftpUser + "]")
    Write-Host ''
    exit 4
}

# ------------------------------------------------------------ helpers FTP
$cred = New-Object System.Net.NetworkCredential($ftpUser, $ftpPwd)

function New-FtpRequest {
    param([string]$UriText, [string]$Method)
    $r = [System.Net.FtpWebRequest]::Create($UriText)
    $r.Credentials = $cred
    $r.EnableSsl    = $useSsl
    $r.UseBinary    = $true
    $r.KeepAlive    = $false
    $r.Method       = $Method
    $r
}

function Request-Upload {
    param([string]$LocalPath, [string]$RemoteDir)
    $RemoteDir = $RemoteDir.TrimEnd('/')
    if ($RemoteDir -eq '') { $RemoteDir = '/' }

    $remoteName = Split-Path -Leaf $LocalPath
    $uri = 'ftp://' + $ftpHost + $RemoteDir + '/' + [uri]::EscapeDataString($remoteName)
    $req = New-FtpRequest -UriText $uri -Method ([System.Net.WebRequestMethods+Ftp]::UploadFile)
    $bytes = [System.IO.File]::ReadAllBytes($LocalPath)
    $req.ContentLength = $bytes.Length
    $stream = $req.GetRequestStream()
    $stream.Write($bytes, 0, $bytes.Length)
    $stream.Close()
    $resp = $req.GetResponse()
    $resp.Close()
}

function Ensure-RemoteDirs {
    param([string]$RemoteDir)
    $RemoteDir = $RemoteDir.TrimEnd('/')
    if ($RemoteDir -eq '' -or $RemoteDir -eq $rootDir) { return }
    if (-not $RemoteDir.StartsWith($rootDir, [System.StringComparison]::Ordinal)) { return }
    $rel = $RemoteDir.Substring($rootDir.Length).TrimStart('/')
    if ($rel -eq '') { return }

    $cur = $rootDir
    foreach ($part in ($rel -split '/')) {
        if ([string]::IsNullOrEmpty($part)) { continue }
        $cur = ($cur + '/' + $part).TrimEnd('/')
        try {
            $req = New-FtpRequest -UriText ('ftp://' + $ftpHost + $cur + '/') -Method ([System.Net.WebRequestMethods+Ftp]::MakeDirectory)
            $req.GetResponse().Close()
        } catch {
            # el directorio ya existía; se ignora
        }
    }
}

# ============================================================ lista de archivos
$items = @()
if (Test-Path -LiteralPath $ListFile) {
    $items = @(Get-Content -LiteralPath $ListFile)
    $items = @($items | Where-Object { -not [string]::IsNullOrWhiteSpace($_) })
}

if ($items.Count -eq 0) {
    Write-Host 'No se recibieron archivos para subir.'
    exit 0
}

$DirectFiles  = @($items | Where-Object { -not (Test-Path -LiteralPath $_ -PathType Container) })
$Directories  = @($items | Where-Object { Test-Path -LiteralPath $_ -PathType Container })

$jobs = @()
foreach ($f in $DirectFiles) {
    $jobs += ,[pscustomobject]@{ Local = (Resolve-Path -LiteralPath $f).Path; RemoteDir = $rootDir }
}
foreach ($d in $Directories) {
    $base = (Resolve-Path -LiteralPath $d).Path.TrimEnd('\')
    Get-ChildItem -LiteralPath $base -Recurse -File | ForEach-Object {
        $rel = $_.FullName.Substring($base.Length).TrimStart('\')
        $relDir = Split-Path -Parent $rel
        $remote = $rootDir
        if ($relDir) { $remote = $rootDir + '/' + ($relDir -replace '\\', '/') }
        $jobs += ,[pscustomobject]@{ Local = $_.FullName; RemoteDir = $remote }
    }
}

if ($jobs.Count -eq 0) {
    Write-Host 'No se encontraron archivos dentro de lo arrastrado.'
    exit 0
}

$ok   = 0
$fail = 0

foreach ($j in $jobs) {
    try {
        Ensure-RemoteDirs $j.RemoteDir
        Request-Upload -LocalPath $j.Local -RemoteDir $j.RemoteDir
        Write-Host ("  OK  " + (Split-Path -Leaf $j.Local) + "  ->  " + $j.RemoteDir + '/')
        $ok++
    } catch {
        Write-Host ("  ERROR " + $j.Local + " : " + $_.Exception.Message)
        $fail++
    }
}

Write-Host ''
Write-Host ("Completado: subidos=" + $ok + "  fallos=" + $fail)
if ($fail -gt 0) { exit 1 }
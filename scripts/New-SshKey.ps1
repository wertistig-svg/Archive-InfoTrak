[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$sshRoot = Join-Path $env:USERPROFILE '.ssh'
New-Item -ItemType Directory -Force -Path $sshRoot | Out-Null

Write-Host "Creation d'une nouvelle cle SSH" -ForegroundColor Cyan
Write-Host "Dossier principal : $sshRoot"

$folderName = Read-Host "Nom du dossier de rangement (exemple : GitHub, GitLab, Serveur-InfoTrak)"
if ([string]::IsNullOrWhiteSpace($folderName)) {
    throw 'Le nom du dossier est obligatoire.'
}

$keyName = Read-Host "Nom de la cle (exemple : infotrak-pc)"
if ([string]::IsNullOrWhiteSpace($keyName)) {
    throw 'Le nom de la cle est obligatoire.'
}

$comment = Read-Host "Commentaire pour reconnaitre la cle (exemple : GitHub InfoTrak PC)"
if ([string]::IsNullOrWhiteSpace($comment)) {
    $comment = "$keyName@$env:COMPUTERNAME"
}

# Empêche les noms de sortir du dossier .ssh.
if ($folderName.IndexOfAny([IO.Path]::GetInvalidFileNameChars()) -ge 0 -or
    $keyName.IndexOfAny([IO.Path]::GetInvalidFileNameChars()) -ge 0 -or
    $folderName -in '.', '..' -or $keyName -in '.', '..') {
    throw 'Le nom du dossier ou de la cle contient des caracteres interdits.'
}

$keyFolder = Join-Path $sshRoot $folderName
New-Item -ItemType Directory -Force -Path $keyFolder | Out-Null
$keyPath = Join-Path $keyFolder $keyName
$publicKeyPath = "$keyPath.pub"

if ((Test-Path -LiteralPath $keyPath) -or (Test-Path -LiteralPath $publicKeyPath)) {
    throw "Une cle portant ce nom existe deja : $keyPath"
}

Write-Host "`nChoisis une phrase secrete quand ssh-keygen la demande." -ForegroundColor Yellow
Write-Host "Elle protege la cle privee. Les caracteres saisis ne seront pas affiches.`n"

& ssh-keygen -t ed25519 -C $comment -f $keyPath
if ($LASTEXITCODE -ne 0) {
    throw "ssh-keygen a echoue avec le code $LASTEXITCODE."
}

Get-Content -LiteralPath $publicKeyPath -Raw | Set-Clipboard

Write-Host "`nCle creee avec succes." -ForegroundColor Green
Write-Host "Cle privee (a ne jamais partager) : $keyPath" -ForegroundColor Yellow
Write-Host "Cle publique : $publicKeyPath"
Write-Host "La cle publique est copiee dans le presse-papiers."
Write-Host "Ajoute-la sur GitHub : https://github.com/settings/ssh/new"
Write-Host "`nPour tester cette cle precisement :"
Write-Host "ssh -T -i `"$keyPath`" git@github.com"

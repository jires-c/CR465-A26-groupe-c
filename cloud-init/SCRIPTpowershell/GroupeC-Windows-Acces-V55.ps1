# CR465 Groupe C - Acces Windows -> VM Multipass/VirtualBox.
# Executer dans PowerShell en administrateur. Requiert C:\PSTools\PsExec.exe.
# Exemple : .\GroupeC-Windows-Acces-V55.ps1 -VmName 'groupec-v55-validation'
param(
    [string]$VmName = 'groupec-v55-validation'
)

$ErrorActionPreference = 'Stop'
$admin = [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
if (-not $admin.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Ouvrir PowerShell en administrateur.'
}
if ($VmName -notmatch '^[A-Za-z0-9._-]+$') {
    throw 'Nom de VM invalide. Utiliser des lettres, chiffres, points, tirets ou underscores.'
}

$vbox = 'C:\Program Files\Oracle\VirtualBox\VBoxManage.exe'
$psexec = 'C:\PSTools\PsExec.exe'
foreach ($path in @($vbox, $psexec)) {
    if (-not (Test-Path -LiteralPath $path)) { throw "Fichier introuvable : $path" }
}

# PsExec ecrit parfois "Connecting to local system..." sur STDERR, meme en cas de succes.
# Start-Process capture STDOUT et STDERR dans des fichiers distincts ; aucun
# NativeCommandError PowerShell ne peut interrompre la commande a tort.
function Invoke-VBoxAsSystem {
    param([Parameter(Mandatory = $true)][string[]]$VBoxArguments)
    $stdoutFile = [System.IO.Path]::GetTempFileName()
    $stderrFile = [System.IO.Path]::GetTempFileName()
    try {
        $quotedArgs = @($VBoxArguments | ForEach-Object { '"' + ($_.Replace('"', '\"')) + '"' })
        $arguments = '-accepteula -nobanner -s "' + $vbox + '" ' + ($quotedArgs -join ' ')
        $process = Start-Process -FilePath $psexec -ArgumentList $arguments `
            -NoNewWindow -Wait -PassThru `
            -RedirectStandardOutput $stdoutFile -RedirectStandardError $stderrFile

        $stdout = @(Get-Content -LiteralPath $stdoutFile -ErrorAction SilentlyContinue)
        $stderr = @(Get-Content -LiteralPath $stderrFile -ErrorAction SilentlyContinue)
        if ($process.ExitCode -ne 0) {
            throw ("Echec PsExec/VBoxManage (code {0}). {1}" -f $process.ExitCode, ($stderr -join ' | '))
        }
        return $stdout
    }
    finally {
        Remove-Item -LiteralPath $stdoutFile, $stderrFile -Force -ErrorAction SilentlyContinue
    }
}

$info = @(Invoke-VBoxAsSystem -VBoxArguments @('showvminfo', $VmName, '--machinereadable'))
if (-not ($info -match 'VMState="running"')) {
    throw "VM '$VmName' introuvable ou arretee dans VirtualBox/SYSTEM. Verifier : multipass list"
}

# Si une regle portant ce nom existe, verifier strictement son port et son IP.
$natRule = @($info | Where-Object { $_ -match 'Forwarding\(\d+\)="groupec-https,tcp,' })
if ($natRule.Count -gt 0 -and -not ($natRule -match 'groupec-https,tcp,127\.0\.0\.1,4443,,4443')) {
    throw 'Regle groupec-https existante mais incorrecte. Aucune modification automatique : examiner VBoxManage showvminfo.'
}
if ($natRule.Count -eq 0) {
    # Ne pas derouter un autre service du PC : la redirection ecoute seulement 127.0.0.1.
    $probe = Test-NetConnection 127.0.0.1 -Port 4443 -WarningAction SilentlyContinue
    if ($probe.TcpTestSucceeded) {
        throw 'Le port 127.0.0.1:4443 est deja occupe. Arreter ancienne VM ou liberer le port.'
    }
    $null = Invoke-VBoxAsSystem -VBoxArguments @(
        'controlvm', $VmName, 'natpf1', 'groupec-https,tcp,127.0.0.1,4443,,4443'
    )
    Write-Host 'OK : redirection NAT 127.0.0.1:4443 -> VM:4443 configuree.'
} else {
    Write-Host 'OK : regle NAT GroupeC deja presente, aucun doublon.'
}

# Uniquement les domaines necessaires au laboratoire ; conservation des lignes existantes.
$hosts = "$env:SystemRoot\System32\drivers\etc\hosts"
$lines = [System.Collections.Generic.List[string]]::new()
$lines.AddRange([string[]](Get-Content -LiteralPath $hosts))
$names = @(
    'moodle.groupec.test',
    'keycloak.groupec.test',
    'drupal.groupec.test',
    'n8n.groupec.test',
    'grafana.groupec.test',
    'portainer.localhost',
    'portainer.groupec.test',
    'demo.localhost',
    'dashboard.localhost'
)
foreach ($name in $names) {
    $pattern = "(?im)^\s*127\.0\.0\.1\s+.*\b$([regex]::Escape($name))\b"
    if (-not (([string]::Join("`n", $lines)) -match $pattern)) {
        $lines.Add("127.0.0.1 $name")
    }
}
$backup = "$hosts.groupec.bak"
if (-not (Test-Path -LiteralPath $backup)) { Copy-Item -LiteralPath $hosts -Destination $backup }
Set-Content -LiteralPath $hosts -Value $lines -Encoding Default
& ipconfig.exe /flushdns | Out-Null
Write-Host "OK : VM=$VmName ; redirection NAT et fichier hosts Windows configures."
Write-Host 'Tester : https://drupal.groupec.test:4443/formations'
Write-Host 'Tester : https://moodle.groupec.test:4443/'
Write-Host 'Tester : https://n8n.groupec.test:4443/'

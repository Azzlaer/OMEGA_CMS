param(
    [Parameter(Mandatory=$true)][string]$JobId,
    [Parameter(Mandatory=$true)][string]$DataDir
)

function Initialize-JobFiles {
    param([string]$JobId,[string]$DataDir)
    $script:StatusFile = Join-Path $DataDir ($JobId + '.status.json')
    $script:ResultFile = Join-Path $DataDir ($JobId + '.result.json')
    $script:LogFile = Join-Path $DataDir ($JobId + '.log.txt')
}
function Write-JobLog([string]$Text) {
    Add-Content -LiteralPath $script:LogFile -Value ('[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Text) -Encoding UTF8
}
function Set-JobStatus([string]$State,[int]$Progress,[string]$Step) {
    @{state=$State;progress=$Progress;step=$Step;updated=(Get-Date).ToString('o')} |
      ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $script:StatusFile -Encoding UTF8
    Write-JobLog "$Progress% - $Step"
}
function Save-JobResult($Object) {
    $Object | ConvertTo-Json -Depth 10 | Set-Content -LiteralPath $script:ResultFile -Encoding UTF8
}
function Safe-Block([scriptblock]$Block) {
    try { & $Block }
    catch { [PSCustomObject]@{error=$_.Exception.Message} }
}

$ErrorActionPreference='Continue'
$ProgressPreference='SilentlyContinue'
Initialize-JobFiles $JobId $DataDir

$r=[ordered]@{
    type='diagnostic'
    generated=(Get-Date).ToString('o')
    computer=$env:COMPUTERNAME
    processUser=[Security.Principal.WindowsIdentity]::GetCurrent().Name
    sections=[ordered]@{}
}
try {
    Set-JobStatus running 5 'Comprobando privilegios'
    $r.sections.privileges = Safe-Block {
        $id=[Security.Principal.WindowsIdentity]::GetCurrent()
        $p=New-Object Security.Principal.WindowsPrincipal($id)
        [PSCustomObject]@{
            identity=$id.Name
            isAdministrator=$p.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
        }
    }

    Set-JobStatus running 14 'Leyendo politica de cuentas'
    $r.sections.accountsPolicy = Safe-Block {
        [PSCustomObject]@{text=((cmd.exe /c 'net accounts' 2>&1 | Out-String).Trim())}
    }

    Set-JobStatus running 24 'Leyendo usuarios locales'
    $r.sections.localUsers = Safe-Block {
        if(Get-Command Get-LocalUser -ErrorAction SilentlyContinue) {
            @(Get-LocalUser | Select Name,Enabled,LastLogon,PasswordExpires,PasswordRequired)
        } else {
            [PSCustomObject]@{text=((cmd.exe /c 'net user' 2>&1 | Out-String).Trim())}
        }
    }

    Set-JobStatus running 38 'Leyendo RDP, NLA y puerto'
    $r.sections.rdp = Safe-Block {
        $ts='HKLM:\SYSTEM\CurrentControlSet\Control\Terminal Server'
        $rdp='HKLM:\SYSTEM\CurrentControlSet\Control\Terminal Server\WinStations\RDP-Tcp'
        $t=Get-ItemProperty $ts -ErrorAction Stop
        $x=Get-ItemProperty $rdp -ErrorAction Stop
        [PSCustomObject]@{
            enabled=($t.fDenyTSConnections -eq 0)
            port=$x.PortNumber
            nla=$x.UserAuthentication
            securityLayer=$x.SecurityLayer
            minEncryptionLevel=$x.MinEncryptionLevel
        }
    }

    Set-JobStatus running 48 'Comprobando servicio RDP'
    $r.sections.termService = Safe-Block {
        Get-Service TermService | Select Name,DisplayName,Status,StartType
    }

    Set-JobStatus running 58 'Probando escucha local del puerto RDP'
    $r.sections.listener = Safe-Block {
        $rdp='HKLM:\SYSTEM\CurrentControlSet\Control\Terminal Server\WinStations\RDP-Tcp'
        $port=(Get-ItemProperty $rdp).PortNumber
        $tcp=Test-NetConnection -ComputerName 127.0.0.1 -Port $port -WarningAction SilentlyContinue
        [PSCustomObject]@{port=$port;tcpTestSucceeded=$tcp.TcpTestSucceeded}
    }

    Set-JobStatus running 68 'Leyendo grupos de acceso remoto'
    $r.sections.remoteDesktopUsers = Safe-Block {
        $group=(Get-LocalGroup | Where-Object {$_.SID.Value -eq 'S-1-5-32-555'} | Select-Object -First 1)
        if($group){ @(Get-LocalGroupMember -Group $group.Name | Select Name,ObjectClass,PrincipalSource) }
        else { [PSCustomObject]@{error='No se encontro el grupo Remote Desktop Users.'} }
    }

    Set-JobStatus running 78 'Leyendo firewall'
    $r.sections.firewall = Safe-Block {
        @(
          Get-NetFirewallRule -ErrorAction SilentlyContinue |
          Where-Object {$_.DisplayGroup -eq 'Remote Desktop' -or $_.DisplayName -like 'RDP Guard*'} |
          ForEach-Object {
            $rule=$_
            $pf=Get-NetFirewallPortFilter -AssociatedNetFirewallRule $rule -ErrorAction SilentlyContinue
            $af=Get-NetFirewallAddressFilter -AssociatedNetFirewallRule $rule -ErrorAction SilentlyContinue
            [PSCustomObject]@{
              name=$rule.DisplayName;enabled=[string]$rule.Enabled;action=[string]$rule.Action
              direction=[string]$rule.Direction;protocol=[string]$pf.Protocol
              localPort=[string]$pf.LocalPort;remoteAddress=[string]($af.RemoteAddress -join ', ')
            }
          }
        )
    }

    Set-JobStatus running 88 'Leyendo eventos de bloqueo'
    $r.sections.lockouts = Safe-Block {
        @(Get-WinEvent -FilterHashtable @{LogName='Security';Id=4740} -MaxEvents 10 -ErrorAction SilentlyContinue |
          Select TimeCreated,Id,Message)
    }

    Set-JobStatus running 96 'Guardando diagnostico'
    Save-JobResult $r
    Set-JobStatus done 100 'Diagnostico terminado'
} catch {
    $r.fatalError=$_.Exception.Message
    Save-JobResult $r
    Set-JobStatus error 100 ('Error: '+$_.Exception.Message)
}

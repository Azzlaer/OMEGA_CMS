param(
    [Parameter(Mandatory=$true)][string]$JobId,
    [Parameter(Mandatory=$true)][string]$DataDir,
    [Parameter(Mandatory=$true)][string]$Username,
    [Parameter(Mandatory=$true)][int]$LockThreshold,
    [Parameter(Mandatory=$true)][int]$LockDuration,
    [Parameter(Mandatory=$true)][int]$LockWindow,
    [Parameter(Mandatory=$true)][int]$Port,
    [Parameter(Mandatory=$true)][int]$EnableNla,
    [Parameter(Mandatory=$true)][int]$AddRdpGroup
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

$ErrorActionPreference='Stop'
Initialize-JobFiles $JobId $DataDir
$result=[ordered]@{type='recovery';username=$Username;steps=@();generated=(Get-Date).ToString('o')}

function Add-Step([string]$Name,[bool]$Ok,[string]$Detail){
  $script:result.steps += [PSCustomObject]@{name=$Name;ok=$Ok;detail=$Detail}
}

try {
  Set-JobStatus running 5 "Buscando usuario $Username"
  $local=Get-LocalUser -Name $Username -ErrorAction SilentlyContinue
  if(-not $local){ throw "No existe el usuario local: $Username" }
  Add-Step 'Usuario encontrado' $true $local.Name

  Set-JobStatus running 15 'Habilitando cuenta'
  try { Enable-LocalUser -Name $Username -ErrorAction Stop; Add-Step 'Cuenta habilitada' $true 'Cuenta habilitada.' }
  catch { Add-Step 'Cuenta habilitada' $false $_.Exception.Message }

  Set-JobStatus running 25 'Intentando desbloquear cuenta'
  try {
    $u=[ADSI]("WinNT://./"+$Username+",user")
    $u.IsAccountLocked=$false
    $u.SetInfo()
    Add-Step 'Desbloqueo' $true 'Estado de bloqueo limpiado por ADSI.'
  } catch { Add-Step 'Desbloqueo' $false $_.Exception.Message }

  Set-JobStatus running 35 'Configurando politica de bloqueo'
  cmd.exe /c "net accounts /lockoutthreshold:$LockThreshold" | Out-Null
  cmd.exe /c "net accounts /lockoutduration:$LockDuration" | Out-Null
  cmd.exe /c "net accounts /lockoutwindow:$LockWindow" | Out-Null
  Add-Step 'Politica de bloqueo' $true "Umbral=$LockThreshold, duracion=$LockDuration, ventana=$LockWindow"

  Set-JobStatus running 48 'Habilitando Escritorio remoto'
  $ts='HKLM:\SYSTEM\CurrentControlSet\Control\Terminal Server'
  $rdp='HKLM:\SYSTEM\CurrentControlSet\Control\Terminal Server\WinStations\RDP-Tcp'
  Set-ItemProperty -Path $ts -Name fDenyTSConnections -Type DWord -Value 0
  Set-ItemProperty -Path $rdp -Name UserAuthentication -Type DWord -Value $EnableNla
  Set-ItemProperty -Path $rdp -Name PortNumber -Type DWord -Value $Port
  Add-Step 'RDP habilitado' $true "Puerto $Port, NLA=$EnableNla"

  Set-JobStatus running 60 'Configurando TermService'
  Set-Service TermService -StartupType Automatic
  Start-Service TermService -ErrorAction SilentlyContinue
  Add-Step 'TermService' $true ((Get-Service TermService).Status.ToString())

  if($AddRdpGroup -eq 1){
    Set-JobStatus running 70 'Agregando usuario a Remote Desktop Users'
    try {
      $grp=Get-LocalGroup | Where-Object {$_.SID.Value -eq 'S-1-5-32-555'} | Select-Object -First 1
      if($grp){
        Add-LocalGroupMember -Group $grp.Name -Member $Username -ErrorAction SilentlyContinue
        Add-Step 'Grupo RDP' $true "Agregado/verificado en $($grp.Name)"
      } else { Add-Step 'Grupo RDP' $false 'No se encontro el grupo por SID.' }
    } catch { Add-Step 'Grupo RDP' $false $_.Exception.Message }
  }

  Set-JobStatus running 82 'Creando regla de firewall de recuperacion'
  Get-NetFirewallRule -DisplayName 'RDP Guard - Recovery' -ErrorAction SilentlyContinue | Remove-NetFirewallRule
  New-NetFirewallRule -DisplayName 'RDP Guard - Recovery' -Direction Inbound -Action Allow -Protocol TCP -LocalPort $Port -Profile Any | Out-Null
  Add-Step 'Firewall recovery' $true "TCP $Port permitido. Restringelo por IP despues de recuperar acceso."

  Set-JobStatus running 92 'Probando listener RDP'
  Start-Sleep -Seconds 1
  $test=Test-NetConnection -ComputerName 127.0.0.1 -Port $Port -WarningAction SilentlyContinue
  Add-Step 'Listener local' ([bool]$test.TcpTestSucceeded) ("TCP $Port = "+$test.TcpTestSucceeded)

  $result.listenerOk=[bool]$test.TcpTestSucceeded
  Save-JobResult $result
  Set-JobStatus done 100 'Recuperacion terminada'
} catch {
  $result.error=$_.Exception.Message
  Save-JobResult $result
  Set-JobStatus error 100 ('Error: '+$_.Exception.Message)
}

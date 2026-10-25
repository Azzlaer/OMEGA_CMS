param(
    [Parameter(Mandatory=$true)][string]$JobId,
    [Parameter(Mandatory=$true)][string]$DataDir,
    [Parameter(Mandatory=$true)][int]$Threshold,
    [Parameter(Mandatory=$true)][int]$WindowMinutes,
    [Parameter(Mandatory=$true)][int]$BlockMinutes,
    [Parameter(Mandatory=$true)][string]$WhitelistCsv,
    [Parameter(Mandatory=$true)][string]$WorkerSource
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
try{
  Set-JobStatus running 10 'Creando directorio de servicio'
  $base='C:\ProgramData\RDPGuard'
  New-Item -ItemType Directory -Path $base -Force | Out-Null

  Set-JobStatus running 25 'Instalando worker anti-bruteforce'
  Copy-Item -LiteralPath $WorkerSource -Destination (Join-Path $base 'AntiBruteForce.ps1') -Force

  Set-JobStatus running 40 'Guardando whitelist'
  $WhitelistCsv.Split(',') | ForEach-Object {$_.Trim()} | Where-Object {$_} |
    Set-Content (Join-Path $base 'whitelist.txt') -Encoding UTF8

  Set-JobStatus running 60 'Creando tarea programada'
  $task='RDP Guard Anti-Bruteforce'
  Unregister-ScheduledTask -TaskName $task -Confirm:$false -ErrorAction SilentlyContinue
  $ps=(Join-Path $base 'AntiBruteForce.ps1')
  $args="-NoProfile -ExecutionPolicy Bypass -File `"$ps`" -Threshold $Threshold -WindowMinutes $WindowMinutes -BlockMinutes $BlockMinutes"
  $action=New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $args
  $trigger=New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 5)
  $principal=New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
  Register-ScheduledTask -TaskName $task -Action $action -Trigger $trigger -Principal $principal -Description 'RDP Guard: bloqueo temporal de IPs con intentos RDP fallidos' | Out-Null

  Set-JobStatus running 80 'Ejecutando prueba inicial'
  Start-ScheduledTask -TaskName $task
  Start-Sleep -Seconds 2

  Save-JobResult @{
    type='antibrute';ok=$true;threshold=$Threshold;windowMinutes=$WindowMinutes
    blockMinutes=$BlockMinutes;taskName=$task;whitelist=$WhitelistCsv
  }
  Set-JobStatus done 100 'Anti-bruteforce instalado'
}catch{
  Save-JobResult @{type='antibrute';ok=$false;error=$_.Exception.Message}
  Set-JobStatus error 100 ('Error: '+$_.Exception.Message)
}

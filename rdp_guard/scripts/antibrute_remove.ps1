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
Initialize-JobFiles $JobId $DataDir
try{
  Set-JobStatus running 20 'Eliminando tarea'
  Unregister-ScheduledTask -TaskName 'RDP Guard Anti-Bruteforce' -Confirm:$false -ErrorAction SilentlyContinue
  Set-JobStatus running 50 'Eliminando reglas temporales'
  Get-NetFirewallRule -DisplayName 'RDP Guard Block *' -ErrorAction SilentlyContinue | Remove-NetFirewallRule
  Set-JobStatus running 75 'Eliminando archivos'
  Remove-Item 'C:\ProgramData\RDPGuard' -Recurse -Force -ErrorAction SilentlyContinue
  Save-JobResult @{type='antibrute_remove';ok=$true}
  Set-JobStatus done 100 'Anti-bruteforce eliminado'
}catch{
  Save-JobResult @{type='antibrute_remove';ok=$false;error=$_.Exception.Message}
  Set-JobStatus error 100 ('Error: '+$_.Exception.Message)
}

param(
    [Parameter(Mandatory=$true)][string]$JobId,
    [Parameter(Mandatory=$true)][string]$DataDir,
    [Parameter(Mandatory=$true)][int]$Port,
    [Parameter(Mandatory=$true)][string]$AllowedCsv,
    [Parameter(Mandatory=$true)][int]$DisableGeneral
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
try {
  Set-JobStatus running 15 'Procesando allowlist'
  $allowed=@($AllowedCsv.Split(',') | ForEach-Object {$_.Trim()} | Where-Object {$_})
  if($allowed.Count -lt 1){throw 'Allowlist vacia.'}

  Set-JobStatus running 40 'Creando regla RDP restringida'
  Get-NetFirewallRule -DisplayName 'RDP Guard - Restricted' -ErrorAction SilentlyContinue | Remove-NetFirewallRule
  New-NetFirewallRule -DisplayName 'RDP Guard - Restricted' -Direction Inbound -Action Allow `
    -Protocol TCP -LocalPort $Port -RemoteAddress $allowed -Profile Any | Out-Null

  if($DisableGeneral -eq 1){
    Set-JobStatus running 70 'Deshabilitando reglas RDP generales'
    Get-NetFirewallRule -DisplayGroup 'Remote Desktop' -ErrorAction SilentlyContinue | Disable-NetFirewallRule
    Get-NetFirewallRule -DisplayName 'RDP Guard - Recovery' -ErrorAction SilentlyContinue | Disable-NetFirewallRule
    Enable-NetFirewallRule -DisplayName 'RDP Guard - Restricted'
  }

  Set-JobStatus running 90 'Verificando firewall'
  $rule=Get-NetFirewallRule -DisplayName 'RDP Guard - Restricted'
  $af=Get-NetFirewallAddressFilter -AssociatedNetFirewallRule $rule
  Save-JobResult @{
    type='firewall';ok=$true;port=$Port;allowed=$allowed;disableGeneral=($DisableGeneral -eq 1)
    rule=[PSCustomObject]@{name=$rule.DisplayName;enabled=[string]$rule.Enabled;remoteAddress=$af.RemoteAddress}
  }
  Set-JobStatus done 100 'Firewall configurado'
} catch {
  Save-JobResult @{type='firewall';ok=$false;error=$_.Exception.Message}
  Set-JobStatus error 100 ('Error: '+$_.Exception.Message)
}

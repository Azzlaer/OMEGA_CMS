param(
  [int]$Threshold=12,
  [int]$WindowMinutes=10,
  [int]$BlockMinutes=180,
  [string]$WhitelistFile='C:\ProgramData\RDPGuard\whitelist.txt'
)
$ErrorActionPreference='SilentlyContinue'
$base='C:\ProgramData\RDPGuard'
$state=Join-Path $base 'blocked.json'
New-Item -ItemType Directory -Path $base -Force | Out-Null

$white=@('127.0.0.1','::1')
if(Test-Path $WhitelistFile){
  $white += @(Get-Content $WhitelistFile | ForEach-Object {$_.Trim()} | Where-Object {$_ -and -not $_.StartsWith('#')})
}

# Eliminar bloqueos expirados
$current=@()
if(Test-Path $state){
  try {$current=@(Get-Content $state -Raw | ConvertFrom-Json)} catch {$current=@()}
}
$now=Get-Date
$keep=@()
foreach($item in $current){
  $expires=[datetime]$item.expires
  if($expires -le $now){
    Remove-NetFirewallRule -DisplayName ('RDP Guard Block '+$item.ip) -ErrorAction SilentlyContinue
  } else {$keep += $item}
}

$start=(Get-Date).AddMinutes(-$WindowMinutes)
$events=@(Get-WinEvent -FilterHashtable @{LogName='Security';Id=4625;StartTime=$start} -ErrorAction SilentlyContinue)
$ips=foreach($e in $events){
  try{
    $xml=[xml]$e.ToXml()
    $d=@{}
    foreach($x in $xml.Event.EventData.Data){$d[$x.Name]=$x.'#text'}
    $ip=[string]$d['IpAddress']
    if($ip -and $ip -notin @('-','127.0.0.1','::1')){$ip}
  }catch{}
}

$groups=@($ips|Group-Object|Where-Object {$_.Count -ge $Threshold})
foreach($g in $groups){
  $ip=$g.Name
  if($white -contains $ip){continue}
  if($keep.ip -contains $ip){continue}
  try{
    New-NetFirewallRule -DisplayName ('RDP Guard Block '+$ip) -Direction Inbound -Action Block -RemoteAddress $ip -Profile Any | Out-Null
    $keep += [PSCustomObject]@{ip=$ip;blocked=$now.ToString('o');expires=$now.AddMinutes($BlockMinutes).ToString('o');count=$g.Count}
  }catch{}
}
@($keep)|ConvertTo-Json -Depth 5 | Set-Content $state -Encoding UTF8

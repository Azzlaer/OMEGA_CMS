param(
    [Parameter(Mandatory=$true)][string]$JobId,
    [Parameter(Mandatory=$true)][string]$DataDir,
    [Parameter(Mandatory=$true)][int]$EventId,
    [Parameter(Mandatory=$true)][int]$MaxEvents
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

function EventData($e) {
    $d=@{}
    try {
      $xml=[xml]$e.ToXml()
      foreach($x in $xml.Event.EventData.Data){$d[[string]$x.Name]=[string]$x.'#text'}
    } catch {}
    $d
}

try {
    Set-JobStatus running 5 "Consultando evento $EventId"
    $events=@(Get-WinEvent -FilterHashtable @{LogName='Security';Id=$EventId} -MaxEvents $MaxEvents -ErrorAction Stop)
    Set-JobStatus running 30 "Procesando $($events.Count) eventos"
    $rows=New-Object System.Collections.Generic.List[object]
    $i=0
    foreach($e in $events){
      $i++; $d=EventData $e
      $o=[ordered]@{
        time=if($e.TimeCreated){$e.TimeCreated.ToString('yyyy-MM-dd HH:mm:ss')}else{''}
        id=$e.Id;recordId=$e.RecordId;provider=$e.ProviderName
        user='';ip='';workstation='';logonType='';status='';subStatus='';callerComputer=''
        message=[string]$e.Message
      }
      if($EventId -eq 4625){
        $o.user=[string]$d['TargetUserName'];$o.ip=[string]$d['IpAddress']
        $o.workstation=[string]$d['WorkstationName'];$o.logonType=[string]$d['LogonType']
        $o.status=[string]$d['Status'];$o.subStatus=[string]$d['SubStatus']
      } elseif($EventId -eq 4624){
        $o.user=[string]$d['TargetUserName'];$o.ip=[string]$d['IpAddress']
        $o.workstation=[string]$d['WorkstationName'];$o.logonType=[string]$d['LogonType']
      } elseif($EventId -eq 4740){
        $o.user=[string]$d['TargetUserName'];$o.callerComputer=[string]$d['CallerComputerName']
      }
      $rows.Add([PSCustomObject]$o)
      if(($i % 25)-eq 0 -and $events.Count -gt 0){
        $pct=30+[int](55*($i/$events.Count));if($pct -gt 88){$pct=88}
        Set-JobStatus running $pct "Procesando $i de $($events.Count)"
      }
    }
    Set-JobStatus running 92 'Calculando resumen'
    $summary=@()
    if($EventId -in 4624,4625){
      $summary=@($rows|Where-Object{$_.ip -and $_.ip -notin @('-','127.0.0.1','::1')}|
        Group-Object ip|Sort-Object Count -Descending|
        Select-Object -First 50 @{n='ip';e={$_.Name}},Count)
    }
    Save-JobResult @{
      type='events';eventId=$EventId;generated=(Get-Date).ToString('o')
      count=$rows.Count;summary=$summary;rows=$rows
    }
    Set-JobStatus done 100 "Visor listo: $($rows.Count) eventos"
} catch {
    Save-JobResult @{type='events';eventId=$EventId;count=0;rows=@();error=$_.Exception.Message}
    Set-JobStatus error 100 ('Error: '+$_.Exception.Message)
}

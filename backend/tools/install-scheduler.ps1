param([string]$PhpPath = (Get-Command php -ErrorAction Stop).Source)
$ErrorActionPreference = 'Stop'
$backendPath = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$phpExecutable = (Resolve-Path -LiteralPath $PhpPath).Path
$runnerPath = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot 'run-scheduled-forecasts.ps1')).Path
$identity = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$hasher = [System.Security.Cryptography.SHA256]::Create()
try { $suffix = [BitConverter]::ToString($hasher.ComputeHash([Text.Encoding]::UTF8.GetBytes($backendPath))).Replace('-', '').Substring(0, 12) } finally { $hasher.Dispose() }
$taskName = 'FinancaPessoal-Previsoes-' + $suffix
$arguments = '-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $runnerPath + '" -PhpPath "' + $phpExecutable + '"'
$action = New-ScheduledTaskAction -Execute (Get-Command powershell.exe -ErrorAction Stop).Source -Argument $arguments -WorkingDirectory $backendPath
$trigger = New-ScheduledTaskTrigger -Daily -At '03:00'
$principal = New-ScheduledTaskPrincipal -UserId $identity -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -Hidden
$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existing -and (($existing.Actions | Select-Object -First 1).Arguments -ne $arguments)) { throw 'Uma tarefa com este nome possui outra ação. Nenhuma alteração realizada.' }
$task = Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description ('Previsões diárias de recorrências: ' + $backendPath) -Force
Write-Output ('Tarefa configurada: ' + $task.TaskName + '. Executa às 03:00 no fuso do Windows, quando este usuário está conectado. StartWhenAvailable habilitado.')

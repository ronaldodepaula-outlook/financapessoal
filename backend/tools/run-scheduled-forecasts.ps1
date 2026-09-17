param([Parameter(Mandatory = $true)][string]$PhpPath)
$ErrorActionPreference = 'Stop'
$backendPath = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$phpExecutable = (Resolve-Path -LiteralPath $PhpPath).Path
$process = Start-Process -FilePath $phpExecutable -ArgumentList @('artisan', 'finance:generate-forecasts', '--months=2', '--no-interaction') -WorkingDirectory $backendPath -WindowStyle Hidden -PassThru
# Wait only for PHP, not the Task Scheduler job containing this PowerShell process.
$process.WaitForExit()
exit $process.ExitCode

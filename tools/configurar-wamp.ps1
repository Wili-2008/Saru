$ErrorActionPreference = 'Stop'
$taskConfigPath = 'C:\wamp64\bin\apache\apache2.4.65\conf\extra\httpd-vhosts.conf'
$taskApache = 'C:\wamp64\bin\apache\apache2.4.65\bin\httpd.exe'
$taskProject = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$taskBackup = Join-Path $taskProject 'storage\httpd-vhosts.before-saru.conf'
$taskOriginal = [System.IO.File]::ReadAllBytes($taskConfigPath)
$taskText = [System.IO.File]::ReadAllText($taskConfigPath)
$taskBlocks = @([regex]::Matches($taskText, '(?is)<VirtualHost\b[^>]*>.*?</VirtualHost>') | Where-Object { $_.Value -match '(?im)^\s*ServerName\s+saru\.com\s*$' })
if ($taskBlocks.Count -ne 1) { throw 'Se esperaba un único VirtualHost de saru.com. No se modificó Apache.' }
$taskNew = [System.IO.File]::ReadAllText((Join-Path $taskProject 'docs\saru-vhost.conf'))
$taskNew = $taskNew.Substring($taskNew.IndexOf('<VirtualHost'))
if (-not (Test-Path -LiteralPath $taskBackup)) { [System.IO.File]::WriteAllBytes($taskBackup, $taskOriginal) }
$taskUpdated = $taskText.Replace($taskBlocks[0].Value, $taskNew.Trim())
[System.IO.File]::WriteAllText($taskConfigPath, $taskUpdated, [System.Text.UTF8Encoding]::new($false))
& $taskApache -t
if ($LASTEXITCODE -ne 0) { [System.IO.File]::WriteAllBytes($taskConfigPath, $taskOriginal); throw 'Apache rechazó la configuración; se restauró el archivo original.' }
Write-Output 'VirtualHost saru.com configurado. Los demás VirtualHosts se conservaron.'
Write-Output 'Reinicia Apache desde el menú de WampServer para aplicar la configuración.'

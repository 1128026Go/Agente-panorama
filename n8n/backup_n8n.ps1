# Creates a timestamped zip backup of n8n/n8n_data
$dt = (Get-Date).ToString('yyyyMMdd_HHmmss')
$src = Join-Path $PSScriptRoot 'n8n_data'
$dest = Join-Path $PSScriptRoot "n8n_data_backup_$dt.zip"
if (-not (Test-Path $src)) { Write-Host "Source folder not found: $src"; exit 2 }
Compress-Archive -Path $src -DestinationPath $dest -Force
Write-Host "Backup created: $dest"

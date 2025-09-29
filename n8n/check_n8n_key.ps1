<#
Checks that the encryption key stored in n8n_data/config matches the N8N_ENCRYPTION_KEY in .env
It does not print the key; it only returns status and shows a masked snippet if desired.
#>

param(
    [switch]$ShowLast4
)

$envFile = Join-Path $PSScriptRoot '.env'
$configFile = Join-Path $PSScriptRoot 'n8n_data\config'

if (-not (Test-Path $envFile)) { Write-Host ".env not found at $envFile"; exit 2 }
if (-not (Test-Path $configFile)) { Write-Host "config not found at $configFile"; exit 2 }

# read env key
$envContent = Get-Content $envFile | Where-Object { $_ -match '^N8N_ENCRYPTION_KEY=' }
if (-not $envContent) { Write-Host "N8N_ENCRYPTION_KEY not set in .env"; exit 2 }
$envKey = $envContent -replace '^N8N_ENCRYPTION_KEY=', ''

# read config
$configJson = Get-Content $configFile -Raw | ConvertFrom-Json
$configKey = $configJson.encryptionKey

if ($envKey -eq $configKey) {
    Write-Host "OK: encryption keys match"
    exit 0
} else {
    Write-Host "MISMATCH: encryption keys differ"
    if ($ShowLast4) {
        $e = '****' + $envKey.Substring($envKey.Length - 4)
        $c = '****' + $configKey.Substring($configKey.Length - 4)
        Write-Host "env: $e  config: $c"
    }
    exit 1
}

<#
setup-laragon.ps1
Verifica PHP/Composer/Node/NPM y configura git hooksPath a .githooks.
Ejecutar desde la raíz del repo: PowerShell (no elevar a admin es suficiente para git config local).
#>

Write-Host "== Setup Laragon / Repo quick-check ==" -ForegroundColor Cyan

function Show-Version($cmd, $args) {
    try {
        $proc = Start-Process -FilePath $cmd -ArgumentList $args -NoNewWindow -RedirectStandardOutput -RedirectStandardError -PassThru -WindowStyle Hidden
        $proc.WaitForExit()
        $out = $proc.StandardOutput.ReadToEnd() + $proc.StandardError.ReadToEnd()
        if ($out.Trim().Length -gt 0) { Write-Host "$cmd $args`n$out`n" -ForegroundColor Green } else { Write-Host "$cmd $args -> no output" -ForegroundColor Yellow }
    } catch {
        Write-Host "$cmd not found or failed: $_" -ForegroundColor Red
    }
}

Write-Host "Checking installed tools...`n" -ForegroundColor White
Show-Version php "-v"
Show-Version composer "-V"
Show-Version node "-v"
Show-Version npm "-v"

# Python/pip detection (optional for detect-secrets)
try {
    $py = & python --version 2>&1
    if ($LASTEXITCODE -eq 0) { Write-Host "python: $py" -ForegroundColor Green } else { Write-Host "python not found" -ForegroundColor Yellow }
} catch { Write-Host "python not found" -ForegroundColor Yellow }

Write-Host "Configuring repository to use local hooks directory '.githooks'...`n" -ForegroundColor Cyan
try {
    git config core.hooksPath .githooks
    $current = git config --get core.hooksPath
    Write-Host "core.hooksPath set to: $current" -ForegroundColor Green
} catch {
    Write-Host "Failed to set core.hooksPath: $_" -ForegroundColor Red
}

Write-Host ""; Write-Host "Sanity checks:" -ForegroundColor White
if (Test-Path ".githooks/pre-commit") {
    Write-Host "Found .githooks/pre-commit" -ForegroundColor Green
} else {
    Write-Host "Warning: .githooks/pre-commit not found. If you want the PHP hook to run, ensure the file exists and is executable." -ForegroundColor Yellow
}

Write-Host "`nNext recommended steps:" -ForegroundColor Cyan
Write-Host " - If you have Python/pip and quieres usar detect-secrets: `n     pip install pre-commit detect-secrets`n     detect-secrets scan > .secrets.baseline` -ForegroundColor White
Write-Host " - To run the PHP checker manually: `n     php scripts/check_secrets.php`" -ForegroundColor White
Write-Host " - Para probar hooks: intenta hacer un commit; el hook PHP abortará si detecta secretos." -ForegroundColor White

Write-Host "\nDone." -ForegroundColor Cyan

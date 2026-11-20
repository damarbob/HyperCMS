# Stop the throwaway web server and drop the regression database.
# usage: powershell -File down.ps1 [-Port 8099] [-Database name]
param([int]$Port = 8099, [string]$Database = '')

$here = $PSScriptRoot
$repo = (Resolve-Path "$here\..\..").Path
. "$here\env.ps1"
if ($Database -ne '') { Set-EnvDatabase "$repo\.env" $Database }

function Get-Php([string]$Pattern) {
    Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object { $_.CommandLine -match $Pattern }
}

# Stop the `spark serve` parent first. It starts a new child on the next port when its child dies.
Get-Php "spark serve --port $Port\b" | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
Start-Sleep -Milliseconds 500
Get-Php "-S localhost:$Port\b" | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
Start-Sleep -Milliseconds 500
"web server stopped: " + (-not (Get-Php "-S localhost:$Port\b"))

php "$here\db.php" "$repo\.env" drop

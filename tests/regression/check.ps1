# Replay the saved requests against the working tree and compare the answers with the baseline.
# usage: powershell -File check.ps1 [-Admin] [-Probes] [-Record] [-Port 8099]
#   -Admin   also replay the admin pages and write flows
#   -Probes  also send the hostile sort and find values
#   -Record  save the answers as the new baseline instead of comparing them
param([switch]$Admin, [switch]$Probes, [switch]$Record, [int]$Port = 8099)

$here    = $PSScriptRoot
$repo    = (Resolve-Path "$here\..\..").Path
$envFile = Join-Path $repo '.env'
$out     = Join-Path $here 'captures'
$base    = "http://localhost:$Port"
$script:failed = $false

if (-not (Test-Path $envFile)) { "No .env found in $repo"; exit 2 }
New-Item -ItemType Directory -Force $out | Out-Null

# A syntax error would show up as 58 differences, so catch it first.
$files = @()
$files += git -C $repo diff --name-only
$files += git -C $repo diff --name-only --cached
$files += git -C $repo ls-files --others --exclude-standard -- '*.php'
foreach ($f in ($files | Sort-Object -Unique)) {
    if ($f -like '*.php' -and (Test-Path "$repo\$f")) {
        $r = php -l "$repo\$f" 2>&1
        if ($LASTEXITCODE -ne 0) { "LINT FAIL $f"; $r; $script:failed = $true }
    }
}

function Test-Listening { [bool](Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) }

if (-not (Test-Listening)) {
    Start-Process php -ArgumentList 'spark', 'serve', '--port', $Port -WorkingDirectory $repo -WindowStyle Hidden | Out-Null
    for ($i = 0; $i -lt 60 -and -not (Test-Listening); $i++) { Start-Sleep -Milliseconds 500 }
    if (-not (Test-Listening)) { "The web server did not start on port $Port"; exit 1 }
}

# Every run starts from the seeded data, because logins and the admin write flows change it.
function Invoke-Run([string]$Name, [string]$Baseline, [scriptblock]$Capture) {
    php "$here\db.php" $envFile restore "$here\seed.sql" | Out-Null
    if ($LASTEXITCODE -ne 0) { "The database could not be restored"; exit 1 }
    Remove-Item -Recurse -Force "$out\$Name" -ErrorAction SilentlyContinue
    & $Capture "$out\$Name" | Select-Object -Last 1
    if ($Record) {
        Remove-Item "$here\$Baseline\*.json" -ErrorAction SilentlyContinue
        New-Item -ItemType Directory -Force "$here\$Baseline" | Out-Null
        Copy-Item "$out\$Name\*.json" "$here\$Baseline"
        "recorded $Baseline"
    } else {
        "--- $Baseline"
        php "$here\harness.php" compare "$here\$Baseline" "$out\$Name"
        if ($LASTEXITCODE -ne 0) { $script:failed = $true }
    }
}

Invoke-Run 'api' 'golden' { param($dir) php "$here\harness.php" capture $base $dir $envFile }
if ($Admin) {
    Invoke-Run 'admin' 'golden-admin' { param($dir) php "$here\admin_harness.php" capture $base $dir $envFile }
}
if ($Probes) {
    "--- probes"
    php "$here\probe.php" $base
    if ($LASTEXITCODE -ne 0) { $script:failed = $true }
}

if ($script:failed) { exit 1 }

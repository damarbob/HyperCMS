# Shared by check.ps1 and down.ps1.

# Point the repo's .env at another database, so a second checkout or lane never shares one.
# The app reads the first uncommented database_default_database line, so that is the line changed.
function Set-EnvDatabase([string]$EnvFile, [string]$Name) {
    if ($Name -notmatch '^[A-Za-z0-9_]+$') { "The database name may hold letters, digits and underscores only"; exit 2 }
    $lines = [System.IO.File]::ReadAllLines($EnvFile)
    $done = $false
    for ($i = 0; $i -lt $lines.Count -and -not $done; $i++) {
        if ($lines[$i] -match '^\s*database[._]default[._]database\s*=') {
            $lines[$i] = "database_default_database = $Name"
            $done = $true
        }
    }
    if (-not $done) { "No database_default_database line found in $EnvFile"; exit 2 }
    [System.IO.File]::WriteAllLines($EnvFile, $lines)
}

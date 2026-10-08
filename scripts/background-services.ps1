param(
    [ValidateSet('Start', 'Status', 'Stop')]
    [string] $Action = 'Status',
    [string] $PhpPath
)

$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$artisanPath = Join-Path $projectRoot 'artisan'
if (-not (Test-Path -LiteralPath $artisanPath)) { throw 'Project artisan file was not found.' }

function Get-ProjectService([string] $service) {
    @(Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object {
        $_.CommandLine -and $_.CommandLine.Contains($artisanPath) -and $_.CommandLine.Contains($service)
    })
}

if ($Action -eq 'Start') {
    if (-not $PhpPath) {
        $phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
        if ($phpCommand) { $PhpPath = $phpCommand.Source }
    }
    if (-not $PhpPath -or -not (Test-Path -LiteralPath $PhpPath)) {
        throw 'Pass -PhpPath with the absolute path to php.exe, or add PHP to PATH.'
    }
    $PhpPath = (Resolve-Path -LiteralPath $PhpPath).Path
    if ([IO.Path]::GetFileName($PhpPath) -ne 'php.exe') { throw 'PhpPath must refer to php.exe.' }
    $logRoot = Join-Path $projectRoot 'storage\logs'
    New-Item -ItemType Directory -Path $logRoot -Force | Out-Null
}

$services = @(
    @{ Name = 'photos'; Command = 'queue:work photos'; Arguments = 'queue:work photos --queue=photos --tries=1 --timeout=210 --sleep=3' },
    @{ Name = 'scheduler'; Command = 'schedule:work'; Arguments = 'schedule:work' }
)
foreach ($service in $services) {
    $running = @(Get-ProjectService $service.Command)
    if ($Action -eq 'Start' -and $running.Count -eq 0) {
        $stamp = Get-Date -Format 'yyyyMMdd-HHmmss-ffff'
        $startArgs = @{
            FilePath = $PhpPath
            ArgumentList = ('"{0}" {1}' -f $artisanPath, $service.Arguments)
            WorkingDirectory = $projectRoot
            WindowStyle = 'Hidden'
            RedirectStandardOutput = Join-Path $logRoot ($service.Name + '-' + $stamp + '.out.log')
            RedirectStandardError = Join-Path $logRoot ($service.Name + '-' + $stamp + '.err.log')
            PassThru = $true
        }
        $started = Start-Process @startArgs
        Write-Output ($service.Name + ': started PID ' + $started.Id)
    } elseif ($Action -eq 'Stop') {
        foreach ($process in $running) {
            # Recheck ownership immediately before touching a process; never stop other projects.
            $current = Get-CimInstance Win32_Process -Filter ('ProcessId = ' + $process.ProcessId)
            if ($current -and $current.CommandLine.Contains($artisanPath) -and $current.CommandLine.Contains($service.Command)) {
                Stop-Process -Id $process.ProcessId
                Write-Output ($service.Name + ': stopped PID ' + $process.ProcessId)
            }
        }
        if ($running.Count -eq 0) { Write-Output ($service.Name + ': already stopped') }
    } else {
        if ($running.Count) {
            Write-Output ($service.Name + ': running PID ' + (($running | ForEach-Object ProcessId) -join ', '))
        } else {
            Write-Output ($service.Name + ': stopped')
        }
    }
}
if ($Action -eq 'Start') {
    Write-Output 'Check becoming:doctor --services after the next minute boundary. Process presence alone does not prove health.'
}

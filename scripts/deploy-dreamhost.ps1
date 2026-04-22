[CmdletBinding()]
param(
    [string]$Branch,
    [switch]$SkipTests,
    [switch]$SkipBuild
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Read-ServerConfig {
    param(
        [Parameter(Mandatory)]
        [string]$Path
    )

    $server = [ordered]@{}
    $mysql = [ordered]@{}
    $deploy = [ordered]@{}
    $section = 'server'

    foreach ($rawLine in Get-Content -LiteralPath $Path) {
        $line = $rawLine.Trim()

        if (-not $line -or $line.StartsWith('#')) {
            continue
        }

        if ($line -match '^mysql\b') {
            $section = 'mysql'
            continue
        }

        if ($line -notmatch '^(?<key>[^:]+):(?<value>.*)$') {
            continue
        }

        $key = $matches['key'].Trim()
        $value = $matches['value'].Trim()

        switch ($section) {
            'server' {
                if ($key -like 'deploy_*') {
                    $deploy[$key] = $value
                }
                else {
                    $server[$key] = $value
                }
            }
            'mysql' {
                if ($key -like 'deploy_*') {
                    $deploy[$key] = $value
                }
                else {
                    $mysql[$key] = $value
                }
            }
        }

        if ($key -like 'deploy_*') {
            $deploy[$key] = $value
        }
    }

    return @{
        server = $server
        mysql = $mysql
        deploy = $deploy
    }
}

function Read-DotEnv {
    param(
        [Parameter(Mandatory)]
        [string]$Path
    )

    $values = [ordered]@{}

    foreach ($rawLine in Get-Content -LiteralPath $Path) {
        if ($rawLine -match '^\s*#' -or $rawLine -match '^\s*$') {
            continue
        }

        if ($rawLine -notmatch '^\s*([A-Za-z_][A-Za-z0-9_]*)=(.*)$') {
            continue
        }

        $key = $matches[1]
        $value = $matches[2]

        if (
            ($value.StartsWith('"') -and $value.EndsWith('"')) -or
            ($value.StartsWith("'") -and $value.EndsWith("'"))
        ) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        $values[$key] = $value
    }

    return $values
}

function Write-DotEnvFile {
    param(
        [Parameter(Mandatory)]
        [System.Collections.Specialized.OrderedDictionary]$Values,
        [Parameter(Mandatory)]
        [string]$Path
    )

    $lines = foreach ($entry in $Values.GetEnumerator()) {
        $value = [string]$entry.Value

        if ($value -match '\s') {
            '{0}="{1}"' -f $entry.Key, $value.Replace('"', '\"')
        }
        else {
            '{0}={1}' -f $entry.Key, $value
        }
    }

    $content = ($lines -join "`n") + "`n"
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $content, $utf8NoBom)
}

function Get-ConfigValue {
    param(
        [Parameter(Mandatory)]
        [System.Collections.IDictionary]$Values,
        [Parameter(Mandatory)]
        [string]$Key,
        [Parameter(Mandatory)]
        [string]$Default
    )

    if ($Values.Contains($Key) -and -not [string]::IsNullOrWhiteSpace([string]$Values[$Key])) {
        return [string]$Values[$Key]
    }

    return $Default
}

function Get-ToolPath {
    param(
        [Parameter(Mandatory)]
        [string[]]$Names,
        [string[]]$FallbackPaths = @()
    )

    foreach ($name in $Names) {
        $command = Get-Command $name -ErrorAction SilentlyContinue
        if ($command) {
            return $command.Source
        }
    }

    foreach ($fallbackPath in $FallbackPaths) {
        if (Test-Path -LiteralPath $fallbackPath) {
            return (Resolve-Path -LiteralPath $fallbackPath).Path
        }
    }

    throw "Nao foi possivel localizar a ferramenta: $($Names -join ', ')"
}

function Invoke-CheckedCommand {
    param(
        [Parameter(Mandatory)]
        [string]$FilePath,
        [Parameter(Mandatory)]
        [string[]]$Arguments,
        [Parameter(Mandatory)]
        [string]$FailureMessage
    )

    & $FilePath @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$FailureMessage (exit code $LASTEXITCODE)."
    }
}

function Invoke-CheckedCapture {
    param(
        [Parameter(Mandatory)]
        [string]$FilePath,
        [Parameter(Mandatory)]
        [string[]]$Arguments,
        [Parameter(Mandatory)]
        [string]$FailureMessage
    )

    $stdoutFile = [System.IO.Path]::GetTempFileName()
    $stderrFile = [System.IO.Path]::GetTempFileName()
    try {
        & $FilePath @Arguments 1> $stdoutFile 2> $stderrFile
        $stdout = if (Test-Path -LiteralPath $stdoutFile) { Get-Content -LiteralPath $stdoutFile -Raw } else { '' }
        $stderr = if (Test-Path -LiteralPath $stderrFile) { Get-Content -LiteralPath $stderrFile -Raw } else { '' }

        if ($LASTEXITCODE -ne 0) {
            $details = ($stderr, $stdout | Where-Object { -not [string]::IsNullOrWhiteSpace($_) }) -join "`n"
            throw "$FailureMessage`n$details"
        }

        return $stdout.Trim()
    }
    finally {
        foreach ($tempFile in @($stdoutFile, $stderrFile)) {
            if (Test-Path -LiteralPath $tempFile) {
                Remove-Item -LiteralPath $tempFile -Force
            }
        }
    }
}

function Invoke-RemoteCommand {
    param(
        [Parameter(Mandatory)]
        [string]$Command
    )

    Invoke-CheckedCommand -FilePath $script:PlinkPath -Arguments @(
        '-batch',
        '-ssh',
        '-hostkey', $script:HostKey,
        '-P', $script:Port,
        '-l', $script:ServerUser,
        '-pw', $script:ServerPassword,
        $script:ServerHost,
        $Command
    ) -FailureMessage 'Falha ao executar comando remoto'
}

function Copy-ToRemote {
    param(
        [Parameter(Mandatory)]
        [string]$LocalPath,
        [Parameter(Mandatory)]
        [string]$RemotePath
    )

    Invoke-CheckedCommand -FilePath $script:PscpPath -Arguments @(
        '-batch',
        '-hostkey', $script:HostKey,
        '-P', $script:Port,
        '-l', $script:ServerUser,
        '-pw', $script:ServerPassword,
        $LocalPath,
        ('{0}@{1}:{2}' -f $script:ServerUser, $script:ServerHost, $RemotePath)
    ) -FailureMessage "Falha ao enviar $LocalPath para o servidor"
}

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$serverConfigPath = Join-Path $repoRoot '.serverconfig'
$localEnvPath = Join-Path $repoRoot '.env'

if (-not (Test-Path -LiteralPath $serverConfigPath)) {
    throw "Arquivo .serverconfig nao encontrado em $serverConfigPath"
}

if (-not (Test-Path -LiteralPath $localEnvPath)) {
    throw "Arquivo .env nao encontrado em $localEnvPath"
}

$script:PlinkPath = Get-ToolPath -Names @('plink.exe', 'plink') -FallbackPaths @('D:\programas\Putty\plink.exe')
$script:PscpPath = Get-ToolPath -Names @('pscp.exe', 'pscp') -FallbackPaths @('D:\programas\Putty\pscp.exe')
$script:TarPath = Get-ToolPath -Names @('tar.exe', 'tar')
$script:GitPath = Get-ToolPath -Names @('git.exe', 'git')
$script:HostKey = 'ssh-ed25519 255 SHA256:isu/1ILrdNVjwsCA05BF9GzHU1XwQVKlIu/+4DKoRfg'

$config = Read-ServerConfig -Path $serverConfigPath
$localEnv = Read-DotEnv -Path $localEnvPath

$script:ServerHost = [string]$config.server['host']
$script:ServerUser = [string]$config.server['user']
$script:ServerPassword = [string]$config.server['password']
$script:Port = Get-ConfigValue -Values $config.server -Key 'port' -Default '22'
$productionUrl = [string]$config.server['url']

$appName = Split-Path $repoRoot -Leaf
$configuredDeployBranch = Get-ConfigValue -Values $config.deploy -Key 'deploy_branch' -Default 'main'
$currentBranch = Invoke-CheckedCapture -FilePath $script:GitPath -Arguments @('rev-parse', '--abbrev-ref', 'HEAD') -FailureMessage 'Nao foi possivel descobrir a branch atual'
$sourceBranch = if ($Branch) { $Branch } elseif ($currentBranch -and $currentBranch -ne 'HEAD') { $currentBranch } else { $configuredDeployBranch }
$remoteBranch = if ($Branch) { $Branch } else { $configuredDeployBranch }

if (-not $Branch -and $currentBranch -ne 'HEAD' -and $sourceBranch -ne $remoteBranch) {
    throw "A branch atual e '$sourceBranch', mas o deploy padrao aponta para '$remoteBranch'. Rode o script com -Branch para confirmar o destino."
}

$sourceRef = "refs/heads/$sourceBranch"
Invoke-CheckedCommand -FilePath $script:GitPath -Arguments @('show-ref', '--verify', '--quiet', $sourceRef) -FailureMessage "A branch local '$sourceBranch' nao existe"

$remoteGitPath = Get-ConfigValue -Values $config.deploy -Key 'deploy_git_path' -Default "/home/$script:ServerUser/repos/$appName.git"
$remoteAppPath = Get-ConfigValue -Values $config.deploy -Key 'deploy_app_path' -Default "/home/$script:ServerUser/apps/$appName"
$remoteWebPath = Get-ConfigValue -Values $config.deploy -Key 'deploy_web_path' -Default "/home/$script:ServerUser/$productionUrl"
$remoteGitParent = ($remoteGitPath -replace '/[^/]+$','')
$remoteAppParent = ($remoteAppPath -replace '/[^/]+$','')
$remoteTempPath = "/home/$script:ServerUser/tmp/$appName-deploy"
$remotePhpBin = Get-ConfigValue -Values $config.deploy -Key 'deploy_php_bin' -Default '/usr/local/bin/php-8.4'

if (-not (Test-Path -LiteralPath (Join-Path $repoRoot 'artisan'))) {
    throw 'O script precisa ser executado na raiz de uma aplicacao Laravel.'
}

if (-not $SkipTests) {
    Write-Host 'Executando testes e validacoes locais...' -ForegroundColor Cyan
    Push-Location $repoRoot
    try {
        Invoke-CheckedCommand -FilePath 'php' -Arguments @('artisan', 'test') -FailureMessage 'Os testes do Laravel falharam'
        Invoke-CheckedCommand -FilePath 'npm.cmd' -Arguments @('run', 'lint') -FailureMessage 'O lint do frontend falhou'
    }
    finally {
        Pop-Location
    }
}

if (-not $SkipBuild) {
    Write-Host 'Gerando build de producao local...' -ForegroundColor Cyan
    Push-Location $repoRoot
    try {
        Invoke-CheckedCommand -FilePath 'npm.cmd' -Arguments @('run', 'build') -FailureMessage 'O build de producao falhou'
    }
    finally {
        Pop-Location
    }
}

$vendorPath = Join-Path $repoRoot 'vendor'
$buildPath = Join-Path $repoRoot 'public\build'

if (-not (Test-Path -LiteralPath $vendorPath)) {
    throw 'A pasta vendor nao existe. Rode composer install antes do deploy.'
}

if (-not (Test-Path -LiteralPath $buildPath)) {
    throw 'A pasta public/build nao existe. Rode npm run build antes do deploy.'
}

$deployDir = Join-Path $repoRoot '.deploy'
New-Item -ItemType Directory -Force -Path $deployDir | Out-Null

$bundleFile = Join-Path $deployDir "$appName.bundle"
$vendorArchive = Join-Path $deployDir 'vendor.tar.gz'
$publicArchive = Join-Path $deployDir 'public-build.tar.gz'
$remoteEnvFile = Join-Path $deployDir 'dreamhost.env'
$remoteIndexFile = Join-Path $deployDir 'index.php'

foreach ($artifact in @($bundleFile, $vendorArchive, $publicArchive, $remoteEnvFile, $remoteIndexFile)) {
    if (Test-Path -LiteralPath $artifact) {
        Remove-Item -LiteralPath $artifact -Force
    }
}

Write-Host 'Gerando bundle Git e artefatos locais...' -ForegroundColor Cyan
Push-Location $repoRoot
try {
    Invoke-CheckedCommand -FilePath $script:GitPath -Arguments @('bundle', 'create', $bundleFile, $sourceRef) -FailureMessage 'Falha ao gerar o bundle Git'
    Invoke-CheckedCommand -FilePath $script:TarPath -Arguments @('-czf', $vendorArchive, '-C', $repoRoot, 'vendor') -FailureMessage 'Falha ao compactar vendor/'
    Invoke-CheckedCommand -FilePath $script:TarPath -Arguments @('-czf', $publicArchive, '-C', $repoRoot, 'public/build') -FailureMessage 'Falha ao compactar public/build'
}
finally {
    Pop-Location
}

$remoteEnvValues = [ordered]@{}
foreach ($entry in $localEnv.GetEnumerator()) {
    $remoteEnvValues[$entry.Key] = $entry.Value
}

$remoteEnvValues['APP_ENV'] = 'production'
$remoteEnvValues['APP_DEBUG'] = 'false'
$remoteEnvValues['APP_URL'] = "https://$productionUrl"
$remoteEnvValues['DB_CONNECTION'] = 'mysql'
$remoteEnvValues['DB_HOST'] = [string]$config.mysql['host']
$remoteEnvValues['DB_PORT'] = '3306'
$remoteEnvValues['DB_DATABASE'] = [string]$config.mysql['dbname']
$remoteEnvValues['DB_USERNAME'] = [string]$config.mysql['user']
$remoteEnvValues['DB_PASSWORD'] = [string]$config.mysql['senha']

$existingRemoteAppKey = Invoke-CheckedCapture -FilePath $script:PlinkPath -Arguments @(
    '-batch',
    '-ssh',
    '-hostkey', $script:HostKey,
    '-P', $script:Port,
    '-l', $script:ServerUser,
    '-pw', $script:ServerPassword,
    $script:ServerHost,
    "if [ -f '$remoteAppPath/.env' ]; then grep '^APP_KEY=' '$remoteAppPath/.env' | head -n 1; fi"
) -FailureMessage 'Falha ao ler APP_KEY remoto'

if (-not [string]::IsNullOrWhiteSpace($existingRemoteAppKey) -and $existingRemoteAppKey.StartsWith('APP_KEY=')) {
    $remoteEnvValues['APP_KEY'] = $existingRemoteAppKey.Substring('APP_KEY='.Length)
}

Write-DotEnvFile -Values $remoteEnvValues -Path $remoteEnvFile

$remoteIndexTemplate = @'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = '__REMOTE_APP_PATH__/storage/framework/maintenance.php')) {
    require $maintenance;
}

require '__REMOTE_APP_PATH__/vendor/autoload.php';

/** @var Application $app */
$app = require_once '__REMOTE_APP_PATH__/bootstrap/app.php';

$app->handleRequest(Request::capture());
'@
$remoteIndex = $remoteIndexTemplate.Replace('__REMOTE_APP_PATH__', $remoteAppPath)
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($remoteIndexFile, $remoteIndex, $utf8NoBom)

Write-Host 'Preparando estrutura remota na DreamHost...' -ForegroundColor Cyan
$setupCommand = @"
set -e
mkdir -p '$remoteGitParent' '$remoteAppParent' '$remoteWebPath' '$remoteTempPath'
if [ ! -d '$remoteGitPath' ]; then
  git init --bare '$remoteGitPath'
fi
if [ ! -x '$remotePhpBin' ]; then
  echo "PHP remoto nao encontrado em $remotePhpBin" >&2
  exit 1
fi
"@
Invoke-RemoteCommand -Command $setupCommand

Write-Host 'Enviando bundle Git, artefatos e .env remoto...' -ForegroundColor Cyan
Copy-ToRemote -LocalPath $bundleFile -RemotePath "$remoteTempPath/$appName.bundle"
Copy-ToRemote -LocalPath $vendorArchive -RemotePath "$remoteTempPath/vendor.tar.gz"
Copy-ToRemote -LocalPath $publicArchive -RemotePath "$remoteTempPath/public-build.tar.gz"
Copy-ToRemote -LocalPath $remoteEnvFile -RemotePath "$remoteTempPath/dreamhost.env"

Write-Host 'Atualizando checkout remoto e publicando release...' -ForegroundColor Cyan
$finalizeCommand = @"
set -e
git -c pack.threads=1 --git-dir='$remoteGitPath' fetch --force '$remoteTempPath/$appName.bundle' '${sourceRef}:refs/heads/$remoteBranch'
mkdir -p '$remoteAppPath'
git --work-tree='$remoteAppPath' --git-dir='$remoteGitPath' checkout -f '$remoteBranch'
rm -rf '$remoteAppPath/vendor' '$remoteAppPath/public/build'
tar -xzf '$remoteTempPath/vendor.tar.gz' -C '$remoteAppPath'
tar -xzf '$remoteTempPath/public-build.tar.gz' -C '$remoteAppPath'
cp '$remoteTempPath/dreamhost.env' '$remoteAppPath/.env'
mkdir -p '$remoteAppPath/bootstrap/cache' '$remoteAppPath/storage/framework/cache/data' '$remoteAppPath/storage/framework/sessions' '$remoteAppPath/storage/framework/views' '$remoteAppPath/storage/logs'
chmod -R u+rwX '$remoteAppPath/storage' '$remoteAppPath/bootstrap/cache'
rsync -a --delete --exclude='.well-known' '$remoteAppPath/public/' '$remoteWebPath/'
if [ -f '$remoteAppPath/public/.htaccess' ]; then
  cp '$remoteAppPath/public/.htaccess' '$remoteWebPath/.htaccess'
fi
cat > '$remoteWebPath/index.php' <<'PHP'
$remoteIndex
PHP
cd '$remoteAppPath'
'$remotePhpBin' artisan migrate --force
'$remotePhpBin' artisan config:clear
'$remotePhpBin' artisan route:clear
'$remotePhpBin' artisan view:clear
'$remotePhpBin' artisan config:cache
'$remotePhpBin' artisan route:cache
'$remotePhpBin' artisan view:cache
rm -rf '$remoteTempPath'
"@
Invoke-RemoteCommand -Command $finalizeCommand

Write-Host ''
Write-Host 'Deploy concluido.' -ForegroundColor Green
Write-Host "Aplicacao: https://$productionUrl" -ForegroundColor Green
Write-Host "PHP remoto: $remotePhpBin" -ForegroundColor DarkGray
Write-Host "Branch publicada: $remoteBranch" -ForegroundColor DarkGray
Write-Host "Codigo remoto: $remoteAppPath" -ForegroundColor DarkGray
Write-Host "Webroot remoto: $remoteWebPath" -ForegroundColor DarkGray

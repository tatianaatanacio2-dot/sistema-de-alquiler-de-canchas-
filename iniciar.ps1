param(
    [switch]$RedLocal,
    [string]$Php = 'C:/xampp/htdocs/topico/.runtime/php/php.exe'
)

$ErrorActionPreference = 'Stop'
if (!(Test-Path -LiteralPath $Php)) {
    $Php = (Get-Command php -ErrorAction Stop).Source
}
if ([version](& $Php -r 'echo PHP_VERSION;') -lt [version]'8.3') {
    throw 'Laravel 13 requiere PHP 8.3 o superior. Usa el PHP de Topico o especifica -Php.'
}

$backend = Join-Path $PSScriptRoot 'backend'
if (!(Test-Path -LiteralPath (Join-Path $backend 'vendor/autoload.php'))) {
    throw 'Instala las dependencias con Composer en la carpeta backend.'
}
if (!(Test-Path -LiteralPath (Join-Path $backend 'public/index.html'))) {
    throw 'Compila la interfaz con compilar.ps1.'
}

$direccion = if ($RedLocal) { '0.0.0.0' } else { '127.0.0.1' }
Write-Host 'Alquiler de cancha: http://127.0.0.1:8012/'
if ($RedLocal) {
    $ipsPrivadas = @(Get-NetIPConfiguration |
        ForEach-Object { $_.IPv4Address } |
        Where-Object IPAddress -Match '^(?:10\.\d+|192\.168|172\.(?:1[6-9]|2\d|3[01]))\.\d+\.\d+$' |
        Select-Object -ExpandProperty IPAddress -Unique)
    foreach ($ip in $ipsPrivadas) {
        Write-Host ('Servidor para el APK: http://{0}:8012' -f $ip)
    }
    if (!$ipsPrivadas) {
        Write-Warning 'No se encontro una direccion IPv4 privada para el APK.'
    }
}
Push-Location -LiteralPath $backend
try {
    & $Php artisan serve "--host=$direccion" --port=8012 --tries=1 --no-reload
    exit $LASTEXITCODE
} finally {
    Pop-Location
}

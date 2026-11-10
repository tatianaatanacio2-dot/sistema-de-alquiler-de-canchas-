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
    $redes = Get-NetIPConfiguration | Where-Object IPv4DefaultGateway
    foreach ($red in $redes) {
        foreach ($ip in $red.IPv4Address) {
            Write-Host ('Servidor para el APK: http://{0}:8012' -f $ip.IPAddress)
        }
    }
}
Push-Location -LiteralPath $backend
try {
    & $Php artisan serve "--host=$direccion" --port=8012 --tries=1 --no-reload
    exit $LASTEXITCODE
} finally {
    Pop-Location
}

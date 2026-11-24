param(
    [switch]$Android,
    [switch]$Publicacion,
    [string]$JavaHome = $env:JAVA_HOME
)

$ErrorActionPreference = 'Stop'
$frontend = Join-Path $PSScriptRoot 'frontend'
Push-Location -LiteralPath $frontend
try {
    if (!$Android -and !$Publicacion) {
        & npm.cmd run build
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo compilar la web.' }
        Copy-Item -Path (Join-Path $frontend 'dist/spa/*') -Destination (Join-Path $PSScriptRoot 'backend/public') -Recurse -Force
        return
    }

    if (!$JavaHome) {
        $jdkRoot = Join-Path $env:LOCALAPPDATA 'AlquilerCancha/jdk'
        $JavaHome = Get-ChildItem -LiteralPath $jdkRoot -Directory -ErrorAction SilentlyContinue |
            Where-Object { Test-Path -LiteralPath (Join-Path $_.FullName 'bin/java.exe') } |
            Select-Object -First 1 -ExpandProperty FullName
    }
    if (!$JavaHome) { throw 'Configura JAVA_HOME o usa -JavaHome con la ruta de Java 21.' }
    $env:JAVA_HOME = $JavaHome
    if (!$env:ANDROID_HOME) { $env:ANDROID_HOME = Join-Path $env:LOCALAPPDATA 'Android/Sdk' }
    if ($Publicacion -and $env:CANCHAS_SERVIDOR -notmatch '^https://') {
        throw 'Configura CANCHAS_SERVIDOR con la dirección HTTPS pública antes de compilar para publicación.'
    }
    $modoAnterior = $env:CANCHAS_PUBLICACION
    $env:CANCHAS_PUBLICACION = if ($Publicacion) { '1' } else { '0' }
    & npx.cmd quasar build -m capacitor -T android --skip-pkg
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar Capacitor.' }
    Set-Location -LiteralPath (Join-Path $frontend 'src-capacitor/android')
    if ($Publicacion) {
        & .\gradlew.bat --no-daemon bundleRelease
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo generar el paquete de publicación.' }
        Copy-Item -LiteralPath 'app/build/outputs/bundle/release/app-release.aab' -Destination (Join-Path $PSScriptRoot 'Alquiler-de-cancha-sin-firmar.aab') -Force
        return
    }
    & .\gradlew.bat --no-daemon assembleDebug
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo generar el APK de pruebas.' }
    Copy-Item -LiteralPath 'app/build/outputs/apk/debug/app-debug.apk' -Destination (Join-Path $PSScriptRoot 'Alquiler-de-cancha.apk') -Force
} finally {
    if (Test-Path variable:modoAnterior) { $env:CANCHAS_PUBLICACION = $modoAnterior }
    Pop-Location
}

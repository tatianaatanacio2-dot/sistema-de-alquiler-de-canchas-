<?php

// Ejecutar: php backend/tests/multicancha.php. Todos los datos de prueba se
// revierten en una transacción; no requiere PHPUnit ni credenciales impresas.
use App\Http\Controllers\AlquilerController;
use App\Http\Controllers\FinanzasController;
use App\Http\Controllers\HorariosPublicosController;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$comprobaciones = 0;
function comprobar(bool $condicion, string $mensaje): void
{
    global $comprobaciones;
    if (! $condicion) {
        throw new RuntimeException($mensaje);
    }
    $comprobaciones++;
}
function peticion(array $datos = []): Request
{
    return Request::create('/api/prueba', 'POST', $datos);
}
function conflicto(callable $accion): void
{
    try {
        $accion();
    } catch (HttpExceptionInterface $e) {
        comprobar($e->getStatusCode() === 409, 'Se esperaba un conflicto 409.');

        return;
    }
    throw new RuntimeException('La operación debía rechazar el conflicto.');
}
function instantanea(): string
{
    return hash('sha256', json_encode(array_map(
        fn ($tabla) => DB::table($tabla)->orderBy('id')->get()->all(),
        ['canchas', 'clientes', 'reservas', 'pagos', 'historial', 'lista_espera'],
    ), JSON_THROW_ON_ERROR));
}

$antes = instantanea();
$administracion = new AlquilerController;
$finanzas = new FinanzasController;
$publico = new HorariosPublicosController;
$fecha = now()->addYears(20)->startOfDay();
while (DB::table('reservas')->where('inicio', '<', $fecha->copy()->addDay()->format('Y-m-d\T00:00'))
    ->where('fin', '>', $fecha->format('Y-m-d\T00:00'))->exists()) {
    $fecha->addDay();
}
Carbon::setTestNow($fecha->copy()->subDay()->setTime(10, 0));
config(['horarios.apertura' => '16:00', 'horarios.cierre' => '22:00']);
$dia = $fecha->toDateString();
$horario = ['inicio' => $dia.'T16:00', 'fin' => $dia.'T17:00'];
DB::beginTransaction();
try {
    comprobar(DB::table('canchas')->whereIn('id', range(1, 5))->count() === 5, 'Faltan canchas: ejecuta artisan migrate.');
    DB::table('canchas')->whereIn('id', range(1, 5))->update(['activa' => 1, 'tarifa' => 30]);
    $listado = $publico->canchas()->getData(true);
    comprobar(count($listado) === 5 && array_column($listado, 'id') === range(1, 5), 'El catálogo debe exponer las cinco canchas.');
    $uno = $administracion->guardarReserva(peticion($horario))->getData(true);
    $dos = $administracion->guardarReserva(peticion($horario + ['cancha_id' => 2]))->getData(true);
    comprobar($uno['cancha_id'] === 1 && $dos['cancha_id'] === 2, 'Debe permitirse el mismo horario en canchas diferentes.');
    comprobar(isset($dos['cancha_nombre']), 'La agenda debe identificar el nombre de la cancha.');
    conflicto(fn () => $administracion->guardarReserva(peticion($horario + ['cancha_id' => 2])));
    conflicto(fn () => $administracion->guardarReserva(peticion($horario + ['cancha_id' => 1]), $dos['id']));
    $editada = $administracion->guardarReserva(peticion($horario), $dos['id'])->getData(true);
    comprobar($editada['cancha_id'] === 2, 'Omitir la cancha al editar debe conservar la original.');
    $adyacente = $administracion->guardarReserva(peticion([
        'cancha_id' => 2, 'inicio' => $dia.'T17:00', 'fin' => $dia.'T18:00',
    ]))->getData(true);
    comprobar($adyacente['id'] !== $dos['id'], 'Debe permitirse un turno que empieza al terminar el anterior.');
    conflicto(fn () => $administracion->guardarReserva(peticion([
        'inicio' => $dia.'T16:30', 'fin' => $dia.'T17:30',
    ]), $adyacente['id']));

    $filtros = $administracion->reservas(peticion(['cancha_id' => 2, 'fecha' => $dia]))->getData(true);
    comprobar(count($filtros) === 2 && count(array_unique(array_column($filtros, 'cancha_id'))) === 1, 'El filtro administrativo debe aislar una cancha.');
    $turnosDos = $publico(peticion(['cancha_id' => 2, 'fecha' => $dia]))->getData(true);
    $turnosTres = $publico(peticion(['cancha_id' => 3, 'fecha' => $dia, 'vista' => 'semana']))->getData(true);
    comprobar($turnosDos['cancha']['id'] === 2 && $turnosDos['tramos'][0]['estado'] === 'reservado', 'El horario debe mostrar las reservas de la cancha seleccionada.');
    comprobar($turnosTres['cancha']['id'] === 3 && $turnosTres['tramos'][0]['estado'] === 'libre', 'Otra cancha debe seguir libre.');
    comprobar(count($turnosTres['semana']) === 7 && $turnosTres['tramos'][0]['fin'] === '22:00', 'La semana debe mantener su disponibilidad independiente.');
    comprobar($publico(peticion(['fecha' => $dia]))->getData(true)['cancha']['id'] === 1, 'El horario antiguo debe usar la cancha 1.');

    $pago = ['operacion' => (string) Str::uuid(), 'monto' => '10.00', 'metodo' => 'efectivo', 'tipo' => 'cobro', 'reserva_id' => $dos['id']];
    $finanzas->guardarPago(peticion($pago));
    $finanzas->guardarPago(peticion($pago));
    comprobar(DB::table('pagos')->where('operacion', $pago['operacion'])->count() === 1, 'Reintentar un pago no debe duplicarlo.');
    conflicto(fn () => $finanzas->guardarPago(peticion(array_replace($pago, ['reserva_id' => $uno['id']]))));
    conflicto(fn () => $finanzas->guardarPago(peticion(array_replace($pago, [
        'operacion' => (string) Str::uuid(), 'monto' => '21.00', 'metodo' => 'yape',
    ]))));
    conflicto(fn () => $administracion->guardarReserva(peticion([
        'inicio' => $dia.'T16:00', 'fin' => $dia.'T16:10',
    ]), $dos['id']));
    $detalle = $administracion->detalleReserva($dos['id'])->getData(true);
    comprobar($detalle['cancha']['id'] === 2 && (float) $detalle['reserva']['abonado'] === 10.0, 'La constancia debe mantener cancha y pagos.');
    $administracion->cerrarReserva(peticion(['estado' => 'cancelada']), $dos['id']);
    conflicto(fn () => $finanzas->guardarPago(peticion(array_replace($pago, [
        'operacion' => (string) Str::uuid(), 'monto' => '1.00',
    ]))));
    $liberada = $publico(peticion(['cancha_id' => 2, 'fecha' => $dia]))->getData(true);
    comprobar($liberada['tramos'][0]['estado'] === 'libre' && $liberada['tramos'][0]['fin'] === '17:00', 'Cancelar debe liberar solo el turno de esa cancha.');
    comprobar($publico(peticion(['cancha_id' => 1, 'fecha' => $dia]))->getData(true)['tramos'][0]['estado'] === 'reservado', 'Cancelar en cancha 2 no debe liberar la cancha 1.');

    $administracion->guardarCancha(peticion(['cancha_id' => 3, 'nombre' => 'Nombre de prueba', 'tarifa' => 45, 'activa' => 0]));
    comprobar($administracion->cancha(peticion(['cancha_id' => 3]))->getData(true)['nombre'] === 'Nombre de prueba', 'La configuración debe guardar la cancha elegida.');
    comprobar($administracion->cancha(peticion())->getData(true)['tarifa'] === 30, 'Configurar otra cancha no debe modificar la primera.');
    comprobar(count($publico->canchas()->getData(true)) === 5, 'El catálogo también debe incluir las canchas inactivas.');
    comprobar($publico(peticion(['cancha_id' => 3, 'fecha' => $dia]))->getData(true)['tramos'] === [], 'Una cancha inactiva no debe ofrecer turnos.');
    conflicto(fn () => $administracion->guardarReserva(peticion($horario + ['cancha_id' => 3])));
    $resumen = $administracion->resumen()->getData(true);
    comprobar(count($resumen['canchas']) === 5 && $resumen['cancha']['id'] === 1, 'El resumen debe incluir todas las canchas y conservar la anterior.');
    $repetidas = $administracion->guardarReservasRepetidas(peticion($horario + ['cancha_id' => 4, 'cantidad' => 2]))->getData(true);
    comprobar(array_column($repetidas, 'cancha_id') === [4, 4], 'Las reservas repetidas deben guardar la cancha escogida.');
    $agenda = $administracion->disponibilidad(peticion(['cancha_id' => 4, 'fecha' => $dia]))->getData(true);
    comprobar(array_column($agenda['ocupados'], 'id') === [$repetidas[0]['id']], 'La agenda administrativa debe filtrar por cancha.');
} finally {
    DB::rollBack();
    Carbon::setTestNow();
}
comprobar(instantanea() === $antes, 'La prueba no debe dejar cambios en los registros.');
echo "OK: {$comprobaciones} comprobaciones multicancha; datos revertidos.\n";

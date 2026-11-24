<?php

namespace App\Console\Commands;

use App\Http\Controllers\AlquilerController;
use App\Http\Controllers\FinanzasController;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class PrepararDemostracion extends Command
{
    protected $signature = 'canchas:demostracion';

    protected $description = 'Carga una semana de reservas y movimientos ficticios identificados como demostración.';

    public function handle(AlquilerController $alquiler, FinanzasController $finanzas): int
    {
        if (app()->isProduction()) {
            $this->error('La demostración está deshabilitada en producción.');

            return self::FAILURE;
        }
        $ahora = now()->toImmutable();
        $relojAnterior = Date::getTestNow();
        $actorAnterior = request()->attributes->get('admin');
        request()->attributes->set('admin', ['nombre' => 'Demostración', 'correo' => null]);
        $creadas = 0;
        try {
            DB::transaction(function () use ($alquiler, $finanzas, $ahora, &$creadas) {
                $creadas = 0;
                $cancha = DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
                if (! $cancha || ! $cancha->activa || ! $cancha->tarifa) {
                    throw new \RuntimeException('Activa la cancha y configura su tarifa antes de cargar la demostración.');
                }
                $perfiles = [
                    ['Camila Rojas', 'camila.rojas'], ['Mateo Vargas', 'mateo.vargas'],
                    ['Valentina Ruiz', 'valentina.ruiz'], ['Samuel Torres', 'samuel.torres'],
                    ['Isabella Moreno', 'isabella.moreno'], ['Nicolás Castillo', 'nicolas.castillo'],
                ];
                $clientes = [];
                foreach ($perfiles as $indice => [$nombre, $alias]) {
                    $correo = $alias.'@example.test';
                    $clientes[] = DB::table('clientes')->where('correo', $correo)->first()
                        ?? $alquiler->guardarCliente(Request::create('/api/clientes', 'POST', [
                            'nombre' => $nombre, 'telefono' => '900000'.(201 + $indice), 'correo' => $correo,
                        ]))->getData();
                }
                $planes = [];
                foreach (['07:00', '10:00', '14:00', '18:00'] as $indice => $hora) {
                    $planes[] = [$ahora->format('Y-m-d').'T'.$hora, [60, 90, 60, 120][$indice], false];
                }
                for ($dia = 1; $dia <= 6; $dia++) {
                    foreach (['17:00', '19:00'] as $indice => $hora) {
                        $planes[] = [$ahora->addDays($dia)->format('Y-m-d').'T'.$hora, $indice === 0 ? 60 : 90, false];
                    }
                }
                $planes[] = [$ahora->format('Y-m-d').'T12:00', 60, true];
                foreach ($planes as $indice => [$desde, $minutos, $cancelar]) {
                    $inicio = CarbonImmutable::parse($desde);
                    $fin = $inicio->addMinutes($minutos)->format('Y-m-d\TH:i');
                    $cliente = $clientes[$indice % count($clientes)];
                    $existente = DB::table('reservas')->where('cancha_id', 1)->where('cliente_id', $cliente->id)
                        ->where('inicio', $desde)->where('fin', $fin)->exists();
                    if ($existente || DB::table('reservas')->where('cancha_id', 1)->where('estado', '<>', 'cancelada')
                        ->where('inicio', '<', $fin)->where('fin', '>', $desde)->exists()) {
                        continue;
                    }
                    Date::setTestNow(min($ahora, $inicio->subMinutes(30)));
                    $datos = ['cliente_id' => $cliente->id, 'inicio' => $desde, 'fin' => $fin];
                    $total = (int) round($cancha->tarifa * $minutos * 100 / 60);
                    $pagar = $cancelar || $indice % 3 !== 2;
                    if ($pagar) {
                        $datos['pago_inicial'] = [
                            'operacion' => $this->operacion('pago', $desde, $indice),
                            'monto' => FinanzasController::soles($cancelar || $indice % 3 === 1 ? intdiv($total, 2) : $total),
                            'metodo' => FinanzasController::METODOS[$indice % count(FinanzasController::METODOS)],
                            'nota' => 'DEMO '.$ahora->format('Y-m-d').' reserva de ejemplo',
                        ];
                    }
                    $reserva = $alquiler->guardarReserva(Request::create('/api/reservas', 'POST', $datos))->getData();
                    $creadas++;
                    Date::setTestNow($ahora);
                    if ($cancelar) {
                        $alquiler->cerrarReserva(Request::create('/api/reservas/estado', 'PATCH', ['estado' => 'cancelada']), $reserva->id);
                        $finanzas->guardarPago(Request::create('/api/pagos', 'POST', [
                            'operacion' => $this->operacion('devolucion', $desde, $indice), 'reserva_id' => $reserva->id,
                            'tipo' => 'devolucion', 'monto' => $datos['pago_inicial']['monto'],
                            'metodo' => $datos['pago_inicial']['metodo'], 'nota' => 'DEMO devolución de una reserva cancelada',
                        ]));
                    } elseif ($fin <= $ahora->format('Y-m-d\TH:i')) {
                        $alquiler->cerrarReserva(Request::create('/api/reservas/estado', 'PATCH', ['estado' => 'completada']), $reserva->id);
                    }
                }
                Date::setTestNow($ahora);
                foreach ([['Limpieza', '15.00', 'efectivo'], ['Agua y útiles de aseo', '12.50', 'yape']] as $indice => [$concepto, $monto, $metodo]) {
                    $finanzas->guardarGasto(Request::create('/api/gastos', 'POST', [
                        'operacion' => $this->operacion('gasto', $ahora->format('Y-m-d'), $indice),
                        'concepto' => 'DEMO '.$concepto, 'monto' => $monto, 'metodo' => $metodo,
                    ]));
                }
            }, 3);
        } finally {
            Date::setTestNow($relojAnterior);
            if ($actorAnterior === null) {
                request()->attributes->remove('admin');
            } else {
                request()->attributes->set('admin', $actorAnterior);
            }
        }
        $this->info("Demostración preparada. Reservas agregadas: $creadas. Se reutilizan los ejemplos existentes.");

        return self::SUCCESS;
    }

    private function operacion(string $tipo, string $fecha, int $indice): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'canchas-alquiler/demo/'.$tipo.'/'.$fecha.'/'.$indice)->toString();
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\Historial;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinanzasController
{
    public const METODOS = ['efectivo', 'yape', 'plin', 'transferencia', 'tarjeta'];

    public static function centimos(string $monto): int
    {
        [$entero, $decimal] = array_pad(explode('.', $monto, 2), 2, '');

        return (int) $entero * 100 + (int) str_pad($decimal, 2, '0');
    }

    public static function soles(int $centimos): string
    {
        return sprintf('%s%d.%02d', $centimos < 0 ? '-' : '', intdiv(abs($centimos), 100), abs($centimos) % 100);
    }

    public static function abonado(int $reservaId): int
    {
        return self::centimos((string) DB::table('pagos')->where('reserva_id', $reservaId)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'cobro' THEN monto ELSE -monto END), 0) AS abonado")
            ->value('abonado'));
    }

    public function pagos(Request $request): JsonResponse
    {
        $datos = $request->validate(['reserva_id' => ['required', 'integer', 'exists:reservas,id']]);

        return response()->json(DB::table('pagos')->where('reserva_id', $datos['reserva_id'])->orderByDesc('id')->get()
            ->map(fn ($pago) => $this->conVersion($pago)));
    }

    public function guardarPago(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'operacion' => ['required', 'uuid'],
            'reserva_id' => ['required', 'integer', 'exists:reservas,id'],
            'tipo' => ['required', Rule::in(['cobro', 'devolucion'])],
            'monto' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'metodo' => ['required', Rule::in(self::METODOS)],
            'nota' => ['nullable', 'string', 'max:250'],
        ]);
        $monto = self::centimos((string) $datos['monto']);
        abort_unless($monto > 0, 422, 'El importe debe ser mayor que cero.');
        $datos['monto'] = self::soles($monto);
        $datos['nota'] ??= '';
        $pago = DB::transaction(function () use ($datos, $monto) {
            DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            $existente = DB::table('pagos')->where('operacion', $datos['operacion'])->first();
            if ($existente) {
                $existente->monto = self::soles(self::centimos((string) $existente->monto));
                foreach ($datos as $campo => $valor) {
                    abort_unless((string) $existente->$campo === (string) $valor, 409, 'Esta operación ya se utilizó con otros datos.');
                }

                return $existente;
            }
            $reserva = DB::table('reservas')->find($datos['reserva_id']);
            abort_unless($reserva, 404, 'La reserva no existe.');
            $abonado = self::abonado($reserva->id);
            if ($datos['tipo'] === 'cobro') {
                abort_if($reserva->estado === 'cancelada', 409, 'La reserva está cancelada. Solo admite devoluciones.');
                abort_if($monto > self::centimos($reserva->total) - $abonado, 409, 'El cobro supera el saldo pendiente de la reserva.');
            } else {
                abort_if($monto > $abonado, 409, 'La devolución supera el importe abonado.');
            }
            $id = DB::table('pagos')->insertGetId($datos + ['fecha' => now()->format('Y-m-d H:i:s')]);
            Historial::registrar('pagos', $id, $datos['tipo'] === 'cobro' ? 'Registrar cobro' : 'Registrar devolución');

            return DB::table('pagos')->find($id);
        }, 3);

        return response()->json($this->conVersion($pago));
    }

    public function editarPago(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'monto' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'metodo' => ['required', Rule::in(self::METODOS)],
            'nota' => ['nullable', 'string', 'max:250'],
            'version' => ['required', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        $monto = self::centimos((string) $datos['monto']);
        abort_unless($monto > 0, 422, 'El importe debe ser mayor que cero.');
        $version = $datos['version'];
        unset($datos['version']);
        $datos['monto'] = self::soles($monto);
        $datos['nota'] ??= '';
        $pago = DB::transaction(function () use ($id, $datos, $version) {
            DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            $antes = DB::table('pagos')->where('id', $id)->lockForUpdate()->first();
            abort_unless($antes, 404, 'El pago no existe.');
            $actual = $this->conVersion(clone $antes);
            $sinCambios = $actual->monto === $datos['monto'] && $actual->metodo === $datos['metodo']
                && $actual->nota === $datos['nota'];
            if ($sinCambios) {
                return $antes;
            }
            abort_unless(hash_equals($actual->version, $version), 409,
                'Este pago fue modificado desde otra pantalla. Cierra la edición y actualiza los movimientos antes de editarlo.');
            $reserva = DB::table('reservas')->find($antes->reserva_id);
            abort_unless($reserva, 404, 'La reserva del pago no existe.');
            $saldo = 0;
            foreach (DB::table('pagos')->where('reserva_id', $antes->reserva_id)->orderBy('id')->get() as $movimiento) {
                $importe = (int) $movimiento->id === $id ? $datos['monto'] : $movimiento->monto;
                $saldo += ($movimiento->tipo === 'cobro' ? 1 : -1) * self::centimos((string) $importe);
                abort_if($saldo < 0, 409,
                    'Este importe dejaría una devolución mayor que lo cobrado. Revisa primero las devoluciones de la reserva.');
            }
            abort_if($saldo > self::centimos((string) $reserva->total), 409,
                'El importe editado haría que los pagos superen el total de la reserva.');
            DB::table('pagos')->where('id', $id)->update($datos);
            Historial::registrar('pagos', $id, 'Editar pago', $antes);

            return DB::table('pagos')->find($id);
        }, 3);

        return response()->json($this->conVersion($pago));
    }

    private function conVersion(object $pago): object
    {
        $pago->monto = self::soles(self::centimos((string) $pago->monto));
        $pago->version = hash('sha256', json_encode([
            $pago->id, $pago->reserva_id, $pago->tipo, $pago->monto,
            $pago->metodo, $pago->nota, $pago->fecha,
        ], JSON_THROW_ON_ERROR));

        return $pago;
    }

    public function caja(Request $request): JsonResponse
    {
        $fecha = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']])['fecha'] ?? now()->format('Y-m-d');
        $desde = $fecha.' 00:00:00';
        $hasta = CarbonImmutable::parse($fecha)->addDay()->format('Y-m-d').' 00:00:00';

        return DB::transaction(function () use ($fecha, $desde, $hasta) {
            $pagos = DB::table('pagos')->where('fecha', '>=', $desde)->where('fecha', '<', $hasta)->get();
            $gastos = DB::table('gastos')->where('fecha', '>=', $desde)->where('fecha', '<', $hasta)->get();
            $porMetodo = [];
            $movimientos = [];
            foreach (self::METODOS as $metodo) {
                $porMetodo[$metodo] = ['ingresos' => 0, 'devoluciones' => 0, 'gastos' => 0];
            }
            foreach ($pagos as $pago) {
                $campo = $pago->tipo === 'cobro' ? 'ingresos' : 'devoluciones';
                $porMetodo[$pago->metodo][$campo] += self::centimos($pago->monto);
                $movimientos[] = [
                    'clave' => 'p'.$pago->id, 'id' => $pago->id, 'fecha' => $pago->fecha,
                    'tipo' => $pago->tipo, 'concepto' => 'Reserva #'.$pago->reserva_id,
                    'metodo' => $pago->metodo, 'monto' => $pago->monto, 'anulado' => false,
                ];
            }
            foreach ($gastos as $gasto) {
                if (! $gasto->anulado) {
                    $porMetodo[$gasto->metodo]['gastos'] += self::centimos($gasto->monto);
                }
                $movimientos[] = [
                    'clave' => 'g'.$gasto->id, 'id' => $gasto->id, 'fecha' => $gasto->fecha,
                    'tipo' => 'gasto', 'concepto' => $gasto->concepto,
                    'metodo' => $gasto->metodo, 'monto' => $gasto->monto, 'anulado' => (bool) $gasto->anulado,
                ];
            }
            $totales = ['ingresos' => 0, 'devoluciones' => 0, 'gastos' => 0, 'neto' => 0];
            foreach ($porMetodo as $metodo => $importes) {
                $importes['neto'] = $importes['ingresos'] - $importes['devoluciones'] - $importes['gastos'];
                foreach ($importes as $campo => $importe) {
                    $totales[$campo] += $importe;
                    $porMetodo[$metodo][$campo] = self::soles($importe);
                }
            }
            usort($movimientos, fn ($a, $b) => strcmp($b['fecha'], $a['fecha']) ?: strcmp($b['clave'], $a['clave']));

            return response()->json([
                'fecha' => $fecha, 'totales' => array_map(self::soles(...), $totales),
                'porMetodo' => $porMetodo, 'movimientos' => $movimientos,
            ]);
        });
    }

    public function guardarGasto(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'operacion' => ['required', 'uuid'],
            'concepto' => ['required', 'string', 'max:150'],
            'monto' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
            'metodo' => ['required', Rule::in(self::METODOS)],
        ]);
        $centimos = self::centimos((string) $datos['monto']);
        abort_unless($centimos > 0, 422, 'El gasto debe ser mayor que cero.');
        $datos['monto'] = self::soles($centimos);
        $gasto = DB::transaction(function () use ($datos) {
            DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            $existente = DB::table('gastos')->where('operacion', $datos['operacion'])->first();
            if ($existente) {
                $existente->monto = self::soles(self::centimos((string) $existente->monto));
                foreach ($datos as $campo => $valor) {
                    abort_unless((string) $existente->$campo === (string) $valor, 409, 'Esta operación ya se utilizó con otros datos.');
                }

                return $existente;
            }
            $id = DB::table('gastos')->insertGetId($datos + ['fecha' => now()->format('Y-m-d H:i:s')]);
            Historial::registrar('gastos', $id, 'Registrar gasto');

            return DB::table('gastos')->find($id);
        }, 3);

        return response()->json($gasto);
    }

    public function anularGasto(int $id): JsonResponse
    {
        $gasto = DB::transaction(function () use ($id) {
            DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            $antes = DB::table('gastos')->find($id);
            abort_unless($antes, 404, 'El gasto no existe.');
            DB::table('gastos')->where('id', $id)->update(['anulado' => true]);
            Historial::registrar('gastos', $id, 'Anular gasto', $antes);

            return DB::table('gastos')->find($id);
        }, 3);

        return response()->json($gasto);
    }

    public function historial(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'entidad' => ['nullable', Rule::in(['cancha', 'clientes', 'reservas', 'pagos', 'gastos'])],
        ]);
        $consulta = DB::table('historial');
        if ($fecha = $filtros['fecha'] ?? null) {
            $consulta->where('fecha', '>=', $fecha.' 00:00:00')
                ->where('fecha', '<', CarbonImmutable::parse($fecha)->addDay()->format('Y-m-d').' 00:00:00');
        }
        if ($entidad = $filtros['entidad'] ?? null) {
            $consulta->where('entidad', $entidad);
        }
        $pagina = $consulta->orderByDesc('id')->paginate(25);
        $pagina->getCollection()->transform(function ($fila) {
            $fila->antes = $fila->antes ? json_decode($fila->antes) : null;
            $fila->despues = json_decode($fila->despues);

            return $fila;
        });

        return response()->json($pagina);
    }
}

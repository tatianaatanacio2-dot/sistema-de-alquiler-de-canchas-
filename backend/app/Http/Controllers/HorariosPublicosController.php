<?php

namespace App\Http\Controllers;

use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HorariosPublicosController
{
    public function canchas(): JsonResponse
    {
        return response()->json(DB::table('canchas')->orderBy('id')->get(['id', 'nombre', 'tarifa', 'activa']))
            ->header('Cache-Control', 'no-store');
    }

    public function __invoke(Request $request): JsonResponse
    {
        $ahora = now();
        $hoy = $ahora->toDateString();
        $limite = $ahora->copy()->addDays(30)->toDateString();
        $filtros = $request->validate([
            'cancha_id' => ['nullable', 'integer', 'exists:canchas,id'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'vista' => ['nullable', 'in:dia,semana'],
        ]);
        $fecha = $filtros['fecha'] ?? $hoy;
        if ($fecha < $hoy || $fecha > $limite) {
            throw ValidationException::withMessages(['fecha' => 'Elige un día dentro de los próximos 30 días.']);
        }

        $apertura = config('horarios.apertura');
        $cierre = config('horarios.cierre');
        abort_unless(preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $apertura)
            && preg_match('/^(?:(?:[01][0-9]|2[0-3]):[0-5][0-9]|24:00)$/D', $cierre)
            && $apertura < $cierre, 503, 'Revisa el horario de atención de la cancha.');
        $whatsapp = config('horarios.whatsapp');
        $canchaId = $filtros['cancha_id'] ?? 1;
        $cancha = DB::table('canchas')->select('id', 'nombre', 'tarifa', 'activa')->find($canchaId);
        abort_unless($cancha, 503, 'La cancha todavía no está configurada.');

        $dias = [$fecha];
        if (($filtros['vista'] ?? null) === 'semana') {
            $lunes = $ahora->copy()->setDateFrom($fecha)->startOfWeek(CarbonInterface::MONDAY);
            $dias = array_map(fn ($offset) => $lunes->copy()->addDays($offset)->toDateString(), range(0, 6));
        }
        $activa = $cancha->activa && $cancha->tarifa;
        $reservas = $activa ? DB::table('reservas')->where('cancha_id', $canchaId)->where('estado', '<>', 'cancelada')
            ->where('inicio', '<', end($dias).'T'.$cierre)->where('fin', '>', reset($dias).'T'.$apertura)
            ->orderBy('inicio')->get(['inicio', 'fin']) : [];
        $corte = $ahora->copy()->ceilMinute()->format('Y-m-d\TH:i');
        $semana = [];
        $tramos = [];
        foreach ($dias as $dia) {
            $consultable = $dia >= $hoy && $dia <= $limite;
            $agenda = $activa && $consultable ? $this->tramos($dia, $apertura, $cierre, $corte, $reservas) : [];
            if ($dia === $fecha) {
                $tramos = $agenda;
            }
            $semana[] = ['fecha' => $dia, 'consultable' => $consultable, 'tramos' => $agenda];
        }

        return response()->json([
            'cancha' => $cancha,
            'fecha' => $fecha,
            'hoy' => $hoy,
            'limite' => $limite,
            'apertura' => $apertura,
            'cierre' => $cierre,
            'whatsapp' => preg_match('/^[1-9][0-9]{7,14}$/D', $whatsapp) ? $whatsapp : null,
            'tramos' => $tramos,
            'semana' => count($dias) === 7 ? $semana : [],
        ])->header('Cache-Control', 'no-store');
    }

    private function tramos(string $fecha, string $apertura, string $cierre, string $corte, iterable $reservas): array
    {
        $inicio = $fecha.'T'.$apertura;
        $fin = $fecha.'T'.$cierre;
        $tramos = [];
        $agregar = static function (string $desde, string $hasta, string $estado) use (&$tramos): void {
            if ($desde < $hasta) {
                $tramos[] = ['inicio' => substr($desde, 11), 'fin' => substr($hasta, 11), 'estado' => $estado];
            }
        };
        $cursor = $inicio;
        $agregarHueco = static function (string $desde, string $hasta) use ($agregar, $corte): void {
            $agregar($desde, min($corte, $hasta), 'pasado');
            $agregar(max($desde, $corte), $hasta, 'libre');
        };
        foreach ($reservas as $reserva) {
            if ($reserva->inicio >= $fin) {
                break;
            }
            if ($reserva->fin <= $cursor) {
                continue;
            }
            $agregarHueco($cursor, $reserva->inicio);
            $hasta = min($reserva->fin, $fin);
            $agregar(max($cursor, $reserva->inicio), $hasta, 'reservado');
            $cursor = $hasta;
        }
        $agregarHueco($cursor, $fin);

        return $tramos;
    }
}

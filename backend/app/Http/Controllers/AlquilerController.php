<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AlquilerController
{
    private const HORARIO = 'Y-m-d\TH:i';

    private const ESTADOS = ['confirmada', 'completada', 'cancelada'];

    private const CAMPOS_CLIENTE = [
        'dni' => ['nullable', 'string', 'max:20'],
        'nombre' => ['nullable', 'string', 'max:120'],
        'telefono' => ['nullable', 'string', 'max:30'],
        'correo' => ['nullable', 'email', 'max:254'],
    ];

    public function resumen(): JsonResponse
    {
        $ahora = now();
        $hoy = $ahora->format('Y-m-d');
        $proximas = $this->agenda()->where('r.estado', 'confirmada')->where('r.fin', '>', $ahora->format(self::HORARIO));
        $reservasHoy = $this->agenda()->where('r.estado', '<>', 'cancelada')
            ->where('r.inicio', '<', $ahora->copy()->addDay()->format('Y-m-d\T00:00'))
            ->where('r.fin', '>', $hoy.'T00:00');

        return response()->json([
            'fecha' => $hoy,
            'zonaHoraria' => config('app.timezone'),
            'cancha' => DB::table('canchas')->find(1),
            'clientes' => DB::table('clientes')->count(),
            'totalHoy' => (clone $reservasHoy)->count(),
            'totalProximas' => (clone $proximas)->count(),
            'hoy' => $reservasHoy->orderBy('r.inicio')->get(),
            'proximas' => $proximas->orderBy('r.inicio')->limit(5)->get(),
        ]);
    }

    public function cancha(): JsonResponse
    {
        return response()->json(DB::table('canchas')->find(1));
    }

    public function guardarCancha(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'tarifa' => ['required', 'integer', 'between:1,100000000'],
            'activa' => ['required', 'boolean'],
        ]);
        DB::table('canchas')->where('id', 1)->update($datos);

        return $this->cancha();
    }

    public function clientes(): JsonResponse
    {
        return response()->json(DB::table('clientes')->orderBy('nombre')->get());
    }

    public function guardarCliente(Request $request, ?int $id = null): JsonResponse
    {
        $datos = array_map(fn ($valor) => $valor ?? '', $request->validate(self::CAMPOS_CLIENTE));
        $clientes = DB::table('clientes');
        if ($id === null) {
            $id = $this->insertarCliente($datos);
        } else {
            abort_unless($clientes->find($id), 404, 'El cliente no existe.');
            if ($datos !== []) {
                $clientes->where('id', $id)->update($datos);
            }
        }

        return response()->json(DB::table('clientes')->find($id));
    }

    public function reservas(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', Rule::in(self::ESTADOS)],
        ]);
        $reservas = $this->agenda();
        if ($fecha = $filtros['fecha'] ?? null) {
            $siguiente = CarbonImmutable::parse($fecha)->addDay()->format('Y-m-d');
            $reservas->where('r.inicio', '<', $siguiente.'T00:00')->where('r.fin', '>', $fecha.'T00:00');
        }
        if ($estado = $filtros['estado'] ?? null) {
            $reservas->where('r.estado', $estado);
        }

        return response()->json($reservas->orderByDesc('r.inicio')->orderByDesc('r.id')->get());
    }

    public function guardarReserva(Request $request, ?int $id = null): JsonResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id', 'prohibits:cliente_nuevo'],
            'cliente_nuevo' => ['nullable', 'array:dni,nombre,telefono'],
            'inicio' => ['required', 'date_format:'.self::HORARIO],
            'fin' => ['required', 'date_format:'.self::HORARIO, 'after:inicio'],
        ], [
            'cliente_id.exists' => 'Selecciona un cliente registrado.',
            'cliente_id.prohibits' => 'Elige un cliente registrado o registra uno nuevo.',
            'fin.after' => 'La hora de fin debe ser posterior al inicio.',
        ]);
        $nuevoCliente = array_map(fn ($valor) => $valor ?? '',
            Validator::make($datos['cliente_nuevo'] ?? [], self::CAMPOS_CLIENTE)->validate());
        unset($datos['cliente_nuevo']);
        $id = DB::transaction(function () use ($datos, $nuevoCliente, $id) {
            $cancha = DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            abort_unless($cancha, 409, 'La cancha necesita configuración.');
            $anterior = $id === null ? null : DB::table('reservas')->find($id);
            abort_if($id !== null && ! $anterior, 404, 'La reserva no existe.');
            abort_if($anterior && $anterior->estado !== 'confirmada', 409, 'Solo se pueden editar reservas confirmadas.');
            $mismoHorario = $anterior && $anterior->inicio === $datos['inicio'] && $anterior->fin === $datos['fin'];
            if (! $mismoHorario) {
                abort_unless($cancha->activa && $cancha->tarifa, 409, 'Activa la cancha y configura su tarifa.');
                if ($datos['inicio'] < now()->format(self::HORARIO)) {
                    throw ValidationException::withMessages(['inicio' => 'El nuevo horario debe comenzar a partir de ahora.']);
                }
            }

            $cruces = DB::table('reservas')->where('cancha_id', 1)->where('estado', '<>', 'cancelada')
                ->where('inicio', '<', $datos['fin'])->where('fin', '>', $datos['inicio']);
            if ($id !== null) {
                $cruces->where('id', '<>', $id);
            }
            abort_if($cruces->exists(), 409, 'Ya hay una reserva que se cruza con ese horario.');

            $datos['cliente_id'] = array_filter($nuevoCliente, fn ($valor) => $valor !== '')
                ? $this->insertarCliente($nuevoCliente)
                : ($datos['cliente_id'] ?? null);

            $tarifa = $anterior->tarifa ?? $cancha->tarifa;
            $inicio = CarbonImmutable::parse($datos['inicio']);
            $fin = CarbonImmutable::parse($datos['fin']);
            $minutos = intdiv($fin->getTimestamp() - $inicio->getTimestamp(), 60);
            $centimosPorHora = $tarifa * 100;
            $centimos = intdiv($minutos, 60) * $centimosPorHora + intdiv(($minutos % 60) * $centimosPorHora + 30, 60);
            $valores = $datos + ['tarifa' => $tarifa, 'total' => sprintf('%d.%02d', intdiv($centimos, 100), $centimos % 100)];
            if ($id === null) {
                return DB::table('reservas')->insertGetId($valores + ['cancha_id' => 1]);
            }
            DB::table('reservas')->where('id', $id)->update($valores);

            return $id;
        }, 3);

        return response()->json($this->agenda()->where('r.id', $id)->first());
    }

    public function cerrarReserva(Request $request, int $id): JsonResponse
    {
        $estado = $request->validate(['estado' => ['required', Rule::in(['cancelada', 'completada'])]])['estado'];
        DB::transaction(function () use ($estado, $id) {
            DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            $reserva = DB::table('reservas')->find($id);
            abort_unless($reserva, 404, 'La reserva no existe.');
            if ($reserva->estado === $estado) {
                return;
            }
            abort_unless($reserva->estado === 'confirmada', 409, 'La reserva ya está cerrada.');
            abort_if($estado === 'completada' && $reserva->fin > now()->format(self::HORARIO), 409, 'Podrás completarla cuando termine su horario.');
            DB::table('reservas')->where('id', $id)->update(['estado' => $estado]);
        }, 3);

        return response()->json($this->agenda()->where('r.id', $id)->first());
    }

    private function agenda(): Builder
    {
        return DB::table('reservas as r')->leftJoin('clientes as c', 'c.id', '=', 'r.cliente_id')
            ->select('r.*')
            ->selectRaw("COALESCE(NULLIF(c.nombre, ''), NULLIF(c.dni, ''), NULLIF(c.telefono, ''), CONCAT('Cliente #', c.id), 'Sin cliente') AS cliente");
    }

    private function insertarCliente(array $datos): int
    {
        return DB::table('clientes')->insertGetId($datos + ['dni' => '', 'nombre' => '', 'telefono' => '', 'correo' => '']);
    }
}

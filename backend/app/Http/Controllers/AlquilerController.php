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

    public function historialCliente(int $id): JsonResponse
    {
        abort_unless(DB::table('clientes')->where('id', $id)->exists(), 404, 'El cliente no existe.');
        $reservas = $this->agenda()->where('r.cliente_id', $id)
            ->orderByDesc('r.inicio')->orderByDesc('r.id')->get();

        return response()->json([
            'reservas' => $reservas,
            'totalReservas' => $reservas->count(),
            'completadas' => $reservas->where('estado', 'completada')->count(),
            'canceladas' => $reservas->where('estado', 'cancelada')->count(),
        ]);
    }

    public function listaEspera(): JsonResponse
    {
        return response()->json(DB::table('lista_espera as l')->leftJoin('clientes as c', 'c.id', '=', 'l.cliente_id')
            ->select('l.*')
            ->selectRaw("COALESCE(NULLIF(c.nombre, ''), NULLIF(c.dni, ''), NULLIF(c.telefono, ''), CONCAT('Cliente #', c.id), 'Sin cliente') AS cliente")
            ->orderByRaw("FIELD(l.estado, 'disponible', 'pendiente', 'avisado', 'atendida', 'cancelada')")
            ->orderBy('l.inicio')->orderBy('l.id')->get());
    }

    public function guardarEnEspera(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id', 'prohibits:cliente_nuevo'],
            'cliente_nuevo' => ['nullable', 'array:dni,nombre,telefono,correo'],
            'inicio' => ['required', 'date_format:'.self::HORARIO],
            'fin' => ['required', 'date_format:'.self::HORARIO, 'after:inicio'],
        ]);
        $nuevoCliente = array_map(fn ($valor) => $valor ?? '',
            Validator::make($datos['cliente_nuevo'] ?? [], self::CAMPOS_CLIENTE)->validate());
        unset($datos['cliente_nuevo']);
        if (empty($datos['cliente_id']) && ! array_filter($nuevoCliente, fn ($valor) => $valor !== '')) {
            throw ValidationException::withMessages(['cliente_id' => 'Selecciona un cliente o ingresa los datos de uno nuevo.']);
        }
        if ($datos['inicio'] < now()->format(self::HORARIO)) {
            throw ValidationException::withMessages(['inicio' => 'El horario solicitado debe comenzar a partir de ahora.']);
        }

        $datos['cliente_id'] = array_filter($nuevoCliente, fn ($valor) => $valor !== '')
            ? $this->insertarCliente($nuevoCliente)
            : $datos['cliente_id'];
        $id = DB::table('lista_espera')->insertGetId($datos + ['estado' => 'pendiente']);

        return response()->json(DB::table('lista_espera as l')->leftJoin('clientes as c', 'c.id', '=', 'l.cliente_id')
            ->select('l.*')->selectRaw("COALESCE(NULLIF(c.nombre, ''), NULLIF(c.dni, ''), NULLIF(c.telefono, ''), CONCAT('Cliente #', c.id), 'Sin cliente') AS cliente")
            ->where('l.id', $id)->first(), 201);
    }

    public function cambiarEstadoEspera(Request $request, int $id): JsonResponse
    {
        $estado = $request->validate(['estado' => ['required', Rule::in(['avisado', 'atendida', 'cancelada'])]])['estado'];
        $solicitud = DB::table('lista_espera')->find($id);
        abort_unless($solicitud, 404, 'La solicitud de espera no existe.');
        abort_if(in_array($solicitud->estado, ['atendida', 'cancelada'], true), 409, 'La solicitud ya está cerrada.');
        DB::table('lista_espera')->where('id', $id)->update(['estado' => $estado]);

        return response()->json(DB::table('lista_espera as l')->leftJoin('clientes as c', 'c.id', '=', 'l.cliente_id')
            ->select('l.*')->selectRaw("COALESCE(NULLIF(c.nombre, ''), NULLIF(c.dni, ''), NULLIF(c.telefono, ''), CONCAT('Cliente #', c.id), 'Sin cliente') AS cliente")
            ->where('l.id', $id)->first());
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

    public function movimientosPago(int $id): JsonResponse
    {
        $reserva = DB::table('reservas')->find($id);
        abort_unless($reserva, 404, 'La reserva no existe.');
        $pagos = DB::table('pagos')->where('reserva_id', $id)->orderByDesc('registrado_en')->orderByDesc('id')->get();
        $pagadoCentavos = (int) round((float) $pagos->sum('monto') * 100);
        $totalCentavos = (int) round((float) $reserva->total * 100);

        return response()->json([
            'pagos' => $pagos,
            'pagado' => sprintf('%d.%02d', intdiv($pagadoCentavos, 100), $pagadoCentavos % 100),
            'saldo' => sprintf('%d.%02d', intdiv(max(0, $totalCentavos - $pagadoCentavos), 100), max(0, $totalCentavos - $pagadoCentavos) % 100),
        ]);
    }

    public function registrarPago(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'metodo' => ['required', Rule::in(['efectivo', 'transferencia', 'tarjeta', 'yape_plin', 'otro'])],
            'referencia' => ['nullable', 'string', 'max:120'],
        ]);

        DB::transaction(function () use ($datos, $id): void {
            $reserva = DB::table('reservas')->where('id', $id)->lockForUpdate()->first();
            abort_unless($reserva, 404, 'La reserva no existe.');
            abort_if($reserva->estado === 'cancelada', 409, 'No se pueden registrar pagos para una reserva cancelada.');
            $pagadoCentavos = (int) round((float) DB::table('pagos')->where('reserva_id', $id)->sum('monto') * 100);
            $totalCentavos = (int) round((float) $reserva->total * 100);
            $montoCentavos = (int) round((float) $datos['monto'] * 100);
            abort_if($montoCentavos > $totalCentavos - $pagadoCentavos, 422, 'El pago supera el saldo pendiente.');

            DB::table('pagos')->insert([
                'reserva_id' => $id,
                'monto' => sprintf('%d.%02d', intdiv($montoCentavos, 100), $montoCentavos % 100),
                'metodo' => $datos['metodo'],
                'referencia' => $datos['referencia'] ?? null,
            ]);
        }, 3);

        return response()->json($this->agenda()->where('r.id', $id)->first(), 201);
    }

    public function guardarReservasRepetidas(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id', 'prohibits:cliente_nuevo'],
            'cliente_nuevo' => ['nullable', 'array:dni,nombre,telefono'],
            'inicio' => ['required', 'date_format:'.self::HORARIO],
            'fin' => ['required', 'date_format:'.self::HORARIO, 'after:inicio'],
            'cantidad' => ['required', 'integer', 'min:2', 'max:52'],
            'pago' => ['nullable', 'array:tipo,monto,metodo'],
        ]);
        $cantidad = (int) $datos['cantidad'];
        unset($datos['cantidad']);
        $nuevoCliente = array_map(fn ($valor) => $valor ?? '',
            Validator::make($datos['cliente_nuevo'] ?? [], self::CAMPOS_CLIENTE)->validate());
        $pago = isset($datos['pago']) ? Validator::make($datos['pago'], [
            'tipo' => ['required', Rule::in(['completo', 'adelanto'])],
            'monto' => ['nullable', 'required_if:tipo,adelanto', 'numeric', 'gt:0', 'decimal:0,2'],
            'metodo' => ['required', Rule::in(['efectivo', 'transferencia', 'tarjeta', 'yape_plin', 'otro'])],
        ])->validate() : null;
        unset($datos['cliente_nuevo'], $datos['pago']);

        $ids = DB::transaction(function () use ($datos, $nuevoCliente, $pago, $cantidad) {
            $cancha = DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            abort_unless($cancha && $cancha->activa && $cancha->tarifa, 409, 'Activa la cancha y configura su tarifa.');
            if ($datos['inicio'] < now()->format(self::HORARIO)) {
                throw ValidationException::withMessages(['inicio' => 'La primera fecha debe comenzar a partir de ahora.']);
            }

            $inicioBase = CarbonImmutable::parse($datos['inicio']);
            $finBase = CarbonImmutable::parse($datos['fin']);
            $duracion = $finBase->getTimestamp() - $inicioBase->getTimestamp();
            $ocurrencias = [];
            for ($i = 0; $i < $cantidad; $i++) {
                $inicio = $inicioBase->addWeeks($i)->format(self::HORARIO);
                $fin = $finBase->addWeeks($i)->format(self::HORARIO);
                $cruce = DB::table('reservas')->where('cancha_id', 1)->where('estado', '<>', 'cancelada')
                    ->where('inicio', '<', $fin)->where('fin', '>', $inicio)->exists();
                if ($cruce) {
                    throw ValidationException::withMessages(['cantidad' => 'Hay un cruce con una reserva existente el '.substr($inicio, 0, 10).'. No se guardó ninguna fecha.']);
                }
                foreach ($ocurrencias as $anterior) {
                    if ($inicio < $anterior['fin'] && $fin > $anterior['inicio']) {
                        throw ValidationException::withMessages(['cantidad' => 'Las fechas repetidas se cruzan entre sí. No se guardó ninguna fecha.']);
                    }
                }
                $ocurrencias[] = ['inicio' => $inicio, 'fin' => $fin];
            }

            $clienteId = array_filter($nuevoCliente, fn ($valor) => $valor !== '')
                ? $this->insertarCliente($nuevoCliente)
                : ($datos['cliente_id'] ?? null);
            $minutos = intdiv($duracion, 60);
            $centimosPorHora = (int) $cancha->tarifa * 100;
            $centimos = intdiv($minutos, 60) * $centimosPorHora + intdiv(($minutos % 60) * $centimosPorHora + 30, 60);
            $idsCreados = [];

            foreach ($ocurrencias as $ocurrencia) {
                $id = DB::table('reservas')->insertGetId([
                    'cancha_id' => 1,
                    'cliente_id' => $clienteId,
                    'inicio' => $ocurrencia['inicio'],
                    'fin' => $ocurrencia['fin'],
                    'tarifa' => $cancha->tarifa,
                    'total' => sprintf('%d.%02d', intdiv($centimos, 100), $centimos % 100),
                ]);
                $idsCreados[] = $id;

                if ($pago !== null) {
                    $montoCentavos = $pago['tipo'] === 'completo' ? $centimos : (int) round((float) $pago['monto'] * 100);
                    abort_if($montoCentavos <= 0 || $montoCentavos > $centimos, 422, 'El adelanto debe ser mayor a cero y no superar el total por fecha.');
                    DB::table('pagos')->insert([
                        'reserva_id' => $id,
                        'monto' => sprintf('%d.%02d', intdiv($montoCentavos, 100), $montoCentavos % 100),
                        'metodo' => $pago['metodo'],
                        'referencia' => null,
                    ]);
                }
            }

            return $idsCreados;
        }, 3);

        return response()->json($this->agenda()->whereIn('r.id', $ids)->orderBy('r.inicio')->get(), 201);
    }

    public function guardarReserva(Request $request, ?int $id = null): JsonResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id', 'prohibits:cliente_nuevo'],
            'cliente_nuevo' => ['nullable', 'array:dni,nombre,telefono'],
            'inicio' => ['required', 'date_format:'.self::HORARIO],
            'fin' => ['required', 'date_format:'.self::HORARIO, 'after:inicio'],
            'pago' => ['nullable', 'array:tipo,monto,metodo'],
        ], [
            'cliente_id.exists' => 'Selecciona un cliente registrado.',
            'cliente_id.prohibits' => 'Elige un cliente registrado o registra uno nuevo.',
            'fin.after' => 'La hora de fin debe ser posterior al inicio.',
        ]);
        $nuevoCliente = array_map(fn ($valor) => $valor ?? '',
            Validator::make($datos['cliente_nuevo'] ?? [], self::CAMPOS_CLIENTE)->validate());
        $pago = isset($datos['pago']) ? Validator::make($datos['pago'], [
            'tipo' => ['required', Rule::in(['completo', 'adelanto'])],
            'monto' => ['nullable', 'required_if:tipo,adelanto', 'numeric', 'gt:0', 'decimal:0,2'],
            'metodo' => ['required', Rule::in(['efectivo', 'transferencia', 'tarjeta', 'yape_plin', 'otro'])],
        ])->validate() : null;
        unset($datos['cliente_nuevo']);
        unset($datos['pago']);

        $id = DB::transaction(function () use ($datos, $nuevoCliente, $pago, $id) {
            $cancha = DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            abort_unless($cancha, 409, 'La cancha necesita configuración.');
            $anterior = $id === null ? null : DB::table('reservas')->where('id', $id)->lockForUpdate()->first();
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
                $id = DB::table('reservas')->insertGetId($valores + ['cancha_id' => 1]);
            } else {
                $pagadoCentavos = (int) round((float) DB::table('pagos')->where('reserva_id', $id)->sum('monto') * 100);
                abort_if($centimos < $pagadoCentavos, 409, 'El total de la reserva no puede ser menor a lo que ya se pagó.');
                DB::table('reservas')->where('id', $id)->update($valores);
            }

            if ($pago !== null) {
                $pagadoCentavos = (int) round((float) DB::table('pagos')->where('reserva_id', $id)->sum('monto') * 100);
                $montoCentavos = $pago['tipo'] === 'completo'
                    ? $centimos - $pagadoCentavos
                    : (int) round((float) $pago['monto'] * 100);
                abort_if($montoCentavos <= 0, 422, 'Esta reserva ya está pagada por completo.');
                abort_if($montoCentavos > $centimos - $pagadoCentavos, 422, 'El pago supera el saldo pendiente.');
                DB::table('pagos')->insert([
                    'reserva_id' => $id,
                    'monto' => sprintf('%d.%02d', intdiv($montoCentavos, 100), $montoCentavos % 100),
                    'metodo' => $pago['metodo'],
                    'referencia' => null,
                ]);
            }

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
            if ($estado === 'cancelada') {
                DB::table('lista_espera')->where('estado', 'pendiente')
                    ->where('inicio', '>=', $reserva->inicio)->where('fin', '<=', $reserva->fin)
                    ->where('inicio', '>', now()->format(self::HORARIO))
                    ->update(['estado' => 'disponible']);
            }
        }, 3);

        return response()->json($this->agenda()->where('r.id', $id)->first());
    }

    private function agenda(): Builder
    {
        $resumenPagos = DB::table('pagos')->select('reserva_id')->selectRaw('SUM(monto) AS pagado')->groupBy('reserva_id');

        return DB::table('reservas as r')->leftJoin('clientes as c', 'c.id', '=', 'r.cliente_id')
            ->leftJoinSub($resumenPagos, 'p', 'p.reserva_id', '=', 'r.id')
            ->select('r.*')
            ->addSelect('c.telefono as cliente_telefono')
            ->selectRaw('COALESCE(p.pagado, 0) AS pagado')
            ->selectRaw('GREATEST(r.total - COALESCE(p.pagado, 0), 0) AS saldo')
            ->selectRaw("COALESCE(NULLIF(c.nombre, ''), NULLIF(c.dni, ''), NULLIF(c.telefono, ''), CONCAT('Cliente #', c.id), 'Sin cliente') AS cliente");
    }

    private function insertarCliente(array $datos): int
    {
        return DB::table('clientes')->insertGetId($datos + ['dni' => '', 'nombre' => '', 'telefono' => '', 'correo' => '']);
    }
}

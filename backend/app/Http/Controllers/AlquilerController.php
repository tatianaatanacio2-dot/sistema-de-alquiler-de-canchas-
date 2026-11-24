<?php

namespace App\Http\Controllers;

use App\Support\Historial;
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
        $saldoPendiente = DB::query()->fromSub($this->agenda(), 'agenda')->where('estado', '<>', 'cancelada')
            ->selectRaw('COALESCE(SUM(CASE WHEN total > abonado THEN total - abonado ELSE 0 END), 0) AS saldo')->value('saldo');
        $caja = app(FinanzasController::class)->caja(Request::create('/api/caja', 'GET', ['fecha' => $hoy]))->getData(true);

        return response()->json([
            'fecha' => $hoy,
            'ahora' => $ahora->format(self::HORARIO),
            'zonaHoraria' => config('app.timezone'),
            'cancha' => DB::table('canchas')->find(1),
            'canchas' => DB::table('canchas')->orderBy('id')->get(),
            'clientes' => DB::table('clientes')->count(),
            'totalHoy' => (clone $reservasHoy)->count(),
            'totalProximas' => (clone $proximas)->count(),
            'saldoPendiente' => (string) $saldoPendiente,
            'cajaHoy' => $caja['totales'],
            'demostracion' => DB::table('pagos')->where('nota', 'like', 'DEMO %')->exists(),
            'hoy' => $reservasHoy->orderBy('r.inicio')->get(),
            'proximas' => $proximas->orderBy('r.inicio')->limit(5)->get(),
        ]);
    }

    public function cancha(Request $request): JsonResponse
    {
        $datos = $request->validate(['cancha_id' => ['nullable', 'integer', 'exists:canchas,id']]);

        return response()->json(DB::table('canchas')->find($datos['cancha_id'] ?? 1));
    }

    public function disponibilidad(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cancha_id' => ['nullable', 'integer', 'exists:canchas,id'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);
        $fecha = $datos['fecha'];
        $canchaId = $datos['cancha_id'] ?? 1;
        $dia = CarbonImmutable::parse($fecha);
        $finDia = $dia->addDay();
        $ocupados = DB::table('reservas')->where('cancha_id', $canchaId)->where('estado', '<>', 'cancelada')
            ->where('inicio', '<', $finDia->format(self::HORARIO))->where('fin', '>', $dia->format(self::HORARIO))
            ->orderBy('inicio')->get(['id', 'inicio', 'fin', 'estado']);
        $cursor = max($dia->format(self::HORARIO), now()->format(self::HORARIO));
        $hasta = $finDia->format(self::HORARIO);
        $libres = [];
        foreach ($ocupados as $reserva) {
            if ($cursor < $reserva->inicio && $cursor < $hasta) {
                $libres[] = ['inicio' => $cursor, 'fin' => min($reserva->inicio, $hasta)];
            }
            $cursor = max($cursor, $reserva->fin);
        }
        if ($cursor < $hasta) {
            $libres[] = ['inicio' => $cursor, 'fin' => $hasta];
        }
        $cancha = DB::table('canchas')->find($canchaId);

        return response()->json([
            'fecha' => $fecha, 'ocupados' => $ocupados,
            'libres' => $cancha && $cancha->activa && $cancha->tarifa ? $libres : [],
            'disponible' => (bool) ($cancha && $cancha->activa && $cancha->tarifa),
        ]);
    }

    public function guardarCancha(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cancha_id' => ['nullable', 'integer', 'exists:canchas,id'],
            'nombre' => ['required', 'string', 'max:100'],
            'tarifa' => ['required', 'integer', 'between:1,100000000'],
            'activa' => ['required', 'boolean'],
        ]);
        $canchaId = $datos['cancha_id'] ?? 1;
        unset($datos['cancha_id']);
        DB::transaction(function () use ($datos, $canchaId) {
            $antes = DB::table('canchas')->where('id', $canchaId)->lockForUpdate()->first();
            DB::table('canchas')->where('id', $canchaId)->update($datos);
            Historial::registrar('cancha', $canchaId, 'Editar cancha', $antes);
        });

        return response()->json(DB::table('canchas')->find($canchaId));
    }

    public function clientes(): JsonResponse
    {
        return response()->json(DB::table('clientes')->orderBy('nombre')->get());
    }

    public function historialCliente(int $id): JsonResponse
    {
        $cliente = DB::table('clientes')->find($id);
        abort_unless($cliente, 404, 'El cliente no existe.');
        $reservas = $this->agenda()->where('r.cliente_id', $id)
            ->orderByDesc('r.inicio')->orderByDesc('r.id')->get();

        return response()->json([
            'cliente' => $cliente,
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
        $id = DB::transaction(function () use ($datos, $id) {
            DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            if ($id === null) {
                return $this->insertarCliente($datos);
            }
            $antes = DB::table('clientes')->where('id', $id)->lockForUpdate()->first();
            abort_unless($antes, 404, 'El cliente no existe.');
            if ($datos !== []) {
                DB::table('clientes')->where('id', $id)->update($datos);
            }
            Historial::registrar('clientes', $id, 'Editar cliente', $antes);

            return $id;
        });

        return response()->json(DB::table('clientes')->find($id));
    }

    public function reservas(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'cancha_id' => ['nullable', 'integer', 'exists:canchas,id'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', Rule::in(self::ESTADOS)],
        ]);
        $reservas = $this->agenda();
        if ($canchaId = $filtros['cancha_id'] ?? null) {
            $reservas->where('r.cancha_id', $canchaId);
        }
        if ($fecha = $filtros['fecha'] ?? null) {
            $siguiente = CarbonImmutable::parse($fecha)->addDay()->format('Y-m-d');
            $reservas->where('r.inicio', '<', $siguiente.'T00:00')->where('r.fin', '>', $fecha.'T00:00');
        }
        if ($estado = $filtros['estado'] ?? null) {
            $reservas->where('r.estado', $estado);
        }

        return response()->json($reservas->orderByDesc('r.inicio')->orderByDesc('r.id')->get());
    }

    public function guardarReservasRepetidas(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cancha_id' => ['nullable', 'integer', 'exists:canchas,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id', 'prohibits:cliente_nuevo'],
            'cliente_nuevo' => ['nullable', 'array:dni,nombre,telefono'],
            'inicio' => ['required', 'date_format:'.self::HORARIO],
            'fin' => ['required', 'date_format:'.self::HORARIO, 'after:inicio'],
            'cantidad' => ['required', 'integer', 'min:2', 'max:52'],
            'pago_inicial' => ['nullable', 'array:operacion,monto,metodo,nota'],
        ]);
        $cantidad = (int) $datos['cantidad'];
        $canchaId = (int) ($datos['cancha_id'] ?? 1);
        unset($datos['cantidad'], $datos['cancha_id']);
        $nuevoCliente = array_map(fn ($valor) => $valor ?? '',
            Validator::make($datos['cliente_nuevo'] ?? [], self::CAMPOS_CLIENTE)->validate());
        $pagoInicial = $datos['pago_inicial'] ?? null;
        if ($pagoInicial !== null) {
            $pagoInicial = Validator::make($pagoInicial, [
                'operacion' => ['required', 'uuid'],
                'monto' => ['required', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
                'metodo' => ['required', Rule::in(FinanzasController::METODOS)],
                'nota' => ['nullable', 'string', 'max:250'],
            ])->validate();
        }
        unset($datos['cliente_nuevo'], $datos['pago_inicial']);

        $ids = DB::transaction(function () use ($datos, $nuevoCliente, $pagoInicial, $cantidad, $canchaId) {
            $cancha = $this->bloquearCanchaParaReserva($canchaId);
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
                $cruce = DB::table('reservas')->where('cancha_id', $canchaId)->where('estado', '<>', 'cancelada')
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
                    'cancha_id' => $canchaId,
                    'cliente_id' => $clienteId,
                    'inicio' => $ocurrencia['inicio'],
                    'fin' => $ocurrencia['fin'],
                    'tarifa' => $cancha->tarifa,
                    'total' => sprintf('%d.%02d', intdiv($centimos, 100), $centimos % 100),
                ]);
                $idsCreados[] = $id;
                Historial::registrar('reservas', $id, 'Crear reserva');

                if ($pagoInicial !== null) {
                    $montoCentavos = FinanzasController::centimos((string) $pagoInicial['monto']);
                    abort_if($montoCentavos <= 0 || $montoCentavos > $centimos, 422, 'El pago debe ser mayor a cero y no superar el total por fecha.');
                    $pagoId = DB::table('pagos')->insertGetId([
                        'operacion' => (string) \Illuminate\Support\Str::uuid(),
                        'reserva_id' => $id,
                        'tipo' => 'cobro',
                        'monto' => FinanzasController::soles($montoCentavos),
                        'metodo' => $pagoInicial['metodo'],
                        'nota' => $pagoInicial['nota'] ?? '',
                        'fecha' => now()->format('Y-m-d H:i:s'),
                    ]);
                    Historial::registrar('pagos', $pagoId, 'Registrar cobro');
                }
            }

            return $idsCreados;
        }, 3);

        return response()->json($this->agenda()->whereIn('r.id', $ids)->orderBy('r.inicio')->get(), 201);
    }

    public function guardarReserva(Request $request, ?int $id = null): JsonResponse
    {
        $datos = $request->validate([
            'cancha_id' => ['nullable', 'integer', 'exists:canchas,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id', 'prohibits:cliente_nuevo'],
            'cliente_nuevo' => ['nullable', 'array:dni,nombre,telefono'],
            'pago_inicial' => ['nullable', 'array:operacion,monto,metodo,nota'],
            'inicio' => ['required', 'date_format:'.self::HORARIO],
            'fin' => ['required', 'date_format:'.self::HORARIO, 'after:inicio'],
            'total' => ['prohibited'],
            'tarifa' => ['prohibited'],
        ], [
            'cliente_id.exists' => 'Selecciona un cliente registrado.',
            'cliente_id.prohibits' => 'Elige un cliente registrado o registra uno nuevo.',
            'fin.after' => 'La hora de fin debe ser posterior al inicio.',
        ]);
        $nuevoCliente = array_map(fn ($valor) => $valor ?? '',
            Validator::make($datos['cliente_nuevo'] ?? [], self::CAMPOS_CLIENTE)->validate());
        $pagoInicial = $datos['pago_inicial'] ?? null;
        abort_if($id !== null && $pagoInicial !== null, 422, 'Registra los pagos de una reserva existente desde Pagos.');
        unset($datos['cliente_nuevo'], $datos['pago_inicial']);
        $id = DB::transaction(function () use ($datos, $nuevoCliente, $pagoInicial, $id) {
            DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            $anterior = $id === null ? null : DB::table('reservas')->where('id', $id)->lockForUpdate()->first();
            abort_if($id !== null && ! $anterior, 404, 'La reserva no existe.');
            abort_if($anterior && $anterior->estado !== 'confirmada', 409, 'Solo se pueden editar reservas confirmadas.');
            $canchaId = (int) ($anterior->cancha_id ?? $datos['cancha_id'] ?? 1);
            abort_if(isset($datos['cancha_id']) && (int) $datos['cancha_id'] !== $canchaId, 409,
                'No se puede cambiar la cancha de una reserva. Cancélala y crea otra en la cancha elegida.');
            unset($datos['cancha_id']);
            $cancha = DB::table('canchas')->where('id', $canchaId)->lockForUpdate()->first();
            abort_unless($cancha, 409, 'La cancha necesita configuración.');
            $mismoHorario = $anterior && $anterior->inicio === $datos['inicio'] && $anterior->fin === $datos['fin'];
            if (! $mismoHorario) {
                abort_unless($cancha->activa && $cancha->tarifa, 409, 'Activa la cancha y configura su tarifa.');
                if ($datos['inicio'] < now()->format(self::HORARIO)) {
                    throw ValidationException::withMessages(['inicio' => 'El nuevo horario debe comenzar a partir de ahora.']);
                }
            }

            $cruces = DB::table('reservas')->where('cancha_id', $canchaId)->where('estado', '<>', 'cancelada')
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
            abort_if($minutos > 1440, 422, 'Una reserva puede durar como máximo 24 horas.');
            $centimosPorHora = $tarifa * 100;
            $centimos = intdiv($minutos, 60) * $centimosPorHora + intdiv(($minutos % 60) * $centimosPorHora + 30, 60);
            abort_if($id !== null && $centimos < FinanzasController::abonado($id), 409,
                'El nuevo total es menor al importe abonado. Registra primero la devolución correspondiente en Pagos.');
            $valores = $datos + ['tarifa' => $tarifa, 'total' => sprintf('%d.%02d', intdiv($centimos, 100), $centimos % 100)];
            if ($id === null) {
                $id = DB::table('reservas')->insertGetId($valores + ['cancha_id' => $canchaId]);
                Historial::registrar('reservas', $id, 'Crear reserva');
                if ($pagoInicial !== null) {
                    app(FinanzasController::class)->guardarPago(Request::create('/api/pagos', 'POST',
                        $pagoInicial + ['reserva_id' => $id, 'tipo' => 'cobro']));
                }

                return $id;
            }
            DB::table('reservas')->where('id', $id)->update($valores);
            Historial::registrar('reservas', $id, 'Editar reserva', $anterior);

            return $id;
        }, 3);

        return response()->json($this->agenda()->where('r.id', $id)->first());
    }

    public function detalleReserva(int $id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            $reserva = $this->agenda()->where('r.id', $id)->first();
            abort_unless($reserva, 404, 'La reserva no existe.');

            return response()->json([
                'reserva' => $reserva,
                'cancha' => DB::table('canchas')->find($reserva->cancha_id),
                'cliente' => $reserva->cliente_id ? DB::table('clientes')->find($reserva->cliente_id) : null,
                'pagos' => DB::table('pagos')->where('reserva_id', $id)->orderBy('id')->get(),
                'emitido' => now()->format(self::HORARIO),
            ]);
        });
    }

    public function reporteReservas(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'estado' => ['nullable', Rule::in(self::ESTADOS)],
        ]);
        $desde = CarbonImmutable::parse($filtros['desde']);
        $hasta = CarbonImmutable::parse($filtros['hasta'])->addDay();
        abort_if($desde->diffInDays($hasta) > 366, 422, 'Selecciona un período de hasta 366 días.');
        $consulta = $this->agenda()->where('r.inicio', '>=', $desde->format(self::HORARIO))
            ->where('r.inicio', '<', $hasta->format(self::HORARIO));
        if ($estado = $filtros['estado'] ?? null) {
            $consulta->where('r.estado', $estado);
        }
        $reservas = $consulta->orderBy('r.inicio')->orderBy('r.id')->get();
        $totales = ['programado' => 0, 'abonado' => 0, 'pendiente' => 0, 'porDevolver' => 0];
        foreach ($reservas as $reserva) {
            $total = FinanzasController::centimos((string) $reserva->total);
            $abonado = FinanzasController::centimos((string) $reserva->abonado);
            $totales['abonado'] += $abonado;
            if ($reserva->estado === 'cancelada') {
                $totales['porDevolver'] += $abonado;
            } else {
                $totales['programado'] += $total;
                $totales['pendiente'] += max(0, $total - $abonado);
            }
        }

        return response()->json([
            'desde' => $filtros['desde'], 'hasta' => $filtros['hasta'],
            'estado' => $estado, 'reservas' => $reservas,
            'totales' => array_map(FinanzasController::soles(...), $totales),
        ]);
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
            Historial::registrar('reservas', $id, $estado === 'cancelada' ? 'Cancelar reserva' : 'Completar reserva', $reserva);
            if ($estado === 'cancelada' && (int) $reserva->cancha_id === 1) {
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
            ->join('canchas as cancha', 'cancha.id', '=', 'r.cancha_id')
            ->leftJoinSub($resumenPagos, 'p', 'p.reserva_id', '=', 'r.id')
            ->select('r.*', 'cancha.nombre as cancha_nombre')
            ->selectSub(DB::table('pagos')->whereColumn('reserva_id', 'r.id')
                ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'cobro' THEN monto ELSE -monto END), 0)"), 'abonado')
            ->selectSub(DB::table('pagos')->whereColumn('reserva_id', 'r.id')->where('tipo', 'cobro')
                ->orderByDesc('id')->limit(1)->select('metodo'), 'metodo_pago')
            ->addSelect('c.telefono as cliente_telefono')
            ->selectRaw("COALESCE(NULLIF(c.nombre, ''), NULLIF(c.dni, ''), NULLIF(c.telefono, ''), CASE WHEN c.id IS NULL THEN 'Sin cliente' ELSE CONCAT('Cliente #', c.id) END) AS cliente");
    }

    private function bloquearCanchaParaReserva(int $canchaId): ?object
    {
        // Los cobros, devoluciones y cambios de reserva comparten el bloqueo de la cancha principal.
        DB::table('canchas')->where('id', 1)->lockForUpdate()->first();

        return DB::table('canchas')->where('id', $canchaId)->lockForUpdate()->first();
    }

    private function insertarCliente(array $datos): int
    {
        $id = DB::table('clientes')->insertGetId($datos + ['dni' => '', 'nombre' => '', 'telefono' => '', 'correo' => '']);
        Historial::registrar('clientes', $id, 'Crear cliente');

        return $id;
    }
}

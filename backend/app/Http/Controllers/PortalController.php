<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SesionCliente;
use App\Support\Historial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PortalController
{
    public function registrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'correo' => ['required', 'email', 'max:254'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'clave' => ['required', 'string', 'min:12', 'max:72'],
        ]);
        abort_if(strlen($datos['clave']) > 72 || str_contains($datos['clave'], "\0"), 422, 'Revisa la longitud de la contraseña.');
        $cuenta = DB::transaction(function () use ($datos) {
            DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            $correo = strtolower($datos['correo']);
            abort_if(DB::table('cuentas_clientes')->where('correo', $correo)->exists(), 409, 'No se puede registrar este correo.');
            // Nunca vincular por correo a un contacto previo: permitiría apropiarse de sus reservas.
            $cliente = DB::table('clientes')->insertGetId([
                'nombre' => $datos['nombre'], 'correo' => $correo, 'dni' => '', 'telefono' => $datos['telefono'] ?? '',
            ]);
            Historial::registrar('clientes', $cliente, 'Registro de cliente');
            $id = DB::table('cuentas_clientes')->insertGetId([
                'cliente_id' => $cliente, 'correo' => $correo, 'clave' => Hash::make($datos['clave']),
            ]);

            return DB::table('cuentas_clientes')->find($id);
        }, 3);

        return $this->emitirSesion($cuenta);
    }

    public function ingresar(Request $request): JsonResponse
    {
        $datos = $request->validate(['correo' => ['required', 'email', 'max:254'], 'clave' => ['required', 'string', 'max:72']]);
        $cuenta = DB::table('cuentas_clientes')->where('correo', strtolower($datos['correo']))->first();
        // Hash fijo válido para reducir diferencias de tiempo cuando no existe el correo.
        $valida = Hash::check($datos['clave'], $cuenta->clave ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        abort_unless($cuenta && $valida, 422, 'El correo o la contraseña son incorrectos.');

        return $this->emitirSesion($cuenta);
    }

    private function emitirSesion(object $cuenta): JsonResponse
    {
        $token = bin2hex(random_bytes(32));
        Cache::put(SesionCliente::clave($token), ['id' => $cuenta->id, 'huella' => hash('sha256', $cuenta->clave)], now()->addHours(12));
        $cliente = DB::table('clientes')->find($cuenta->cliente_id);

        return response()->json(['token' => $token, 'usuario' => ['nombre' => $cliente->nombre, 'correo' => $cuenta->correo, 'rol' => 'cliente']]);
    }

    public function salir(Request $request): JsonResponse
    {
        Cache::forget(SesionCliente::clave((string) $request->bearerToken()));

        return response()->json(['mensaje' => 'Sesión cerrada.']);
    }

    public function reservas(Request $request, AlquilerController $alquiler): JsonResponse
    {
        $historial = $alquiler->historialCliente((int) $request->attributes->get('cliente_id'))->getData(true);

        return response()->json($historial['reservas']);
    }

    public function reservar(Request $request, AlquilerController $alquiler): JsonResponse
    {
        $datos = $request->validate([
            'cancha_id' => ['nullable', 'integer', 'exists:canchas,id'],
            'inicio' => ['required', 'date_format:Y-m-d\TH:i'],
            'fin' => ['required', 'date_format:Y-m-d\TH:i'],
            'cliente_id' => ['prohibited'], 'cliente_nuevo' => ['prohibited'],
            'pago_inicial' => ['prohibited'], 'total' => ['prohibited'], 'tarifa' => ['prohibited'],
        ]);
        $datos['cliente_id'] = (int) $request->attributes->get('cliente_id');

        return $alquiler->guardarReserva(Request::create('/api/reservas', 'POST', $datos));
    }

    public function cancelar(Request $request, int $id, AlquilerController $alquiler): JsonResponse
    {
        return DB::transaction(function () use ($request, $id, $alquiler) {
            DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            $reserva = DB::table('reservas')->where('cliente_id', $request->attributes->get('cliente_id'))->find($id);
            abort_unless($reserva, 404, 'La reserva no existe.');
            abort_if($reserva->inicio <= now()->format('Y-m-d\TH:i'), 409, 'Contacta con administración para cancelar una reserva ya iniciada.');

            return $alquiler->cerrarReserva(Request::create('/api/reservas/'.$id.'/estado', 'PATCH', ['estado' => 'cancelada']), $id);
        }, 3);
    }
}

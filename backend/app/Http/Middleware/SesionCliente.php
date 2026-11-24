<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SesionCliente
{
    public static function clave(string $token): string
    {
        return 'cliente:'.hash('sha256', $token);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        abort_unless($token && preg_match('/^[a-f0-9]{64}$/D', $token), 401, 'Inicia sesión para continuar.');
        $sesion = Cache::get(self::clave($token));
        $cuenta = $sesion ? DB::table('cuentas_clientes')->find($sesion['id']) : null;
        abort_unless($cuenta && hash_equals($sesion['huella'], hash('sha256', $cuenta->clave)), 401, 'Tu sesión expiró.');
        $cliente = DB::table('clientes')->find($cuenta->cliente_id);
        abort_unless($cliente, 401);
        $request->attributes->set('cliente_id', $cliente->id);
        $request->attributes->set('admin', ['nombre' => $cliente->nombre, 'correo' => $cuenta->correo]);

        return $next($request);
    }
}

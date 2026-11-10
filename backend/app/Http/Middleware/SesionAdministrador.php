<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SesionAdministrador
{
    public static function clave(string $token): string
    {
        return 'sesion:'.hash('sha256', $token);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        abort_unless($token && strlen($token) === 64, 401, 'Inicia sesión para continuar.');
        $huella = Cache::get(self::clave($token));
        abort_unless($huella, 401, 'Tu sesión expiró. Vuelve a ingresar.');
        $admin = DB::table('administrador')->find(1);
        abort_unless($admin && hash_equals($huella, hash('sha256', $admin->clave)), 401, 'Inicia sesión para continuar.');
        $request->attributes->set('admin', ['nombre' => $admin->nombre, 'correo' => $admin->correo]);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SesionAdministrador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccesoController
{
    public function estado(Request $request): JsonResponse
    {
        return response()->json([
            'requiereCuenta' => ! DB::table('administrador')->where('id', 1)->exists(),
            'equipoLocal' => $this->permiteConfigurar($request),
        ])->header('Cache-Control', 'no-store');
    }

    public function configurar(Request $request): JsonResponse
    {
        abort_unless($request->isJson() && $this->permiteConfigurar($request), 403,
            'Crea la primera cuenta desde el navegador de la computadora que aloja el sistema.');
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'correo' => ['required', 'email', 'max:254'],
            'clave' => ['required', 'string', 'min:12', 'max:72'],
        ]);
        abort_if(strlen($datos['clave']) > 72 || str_contains($datos['clave'], "\0"), 422, 'Revisa la longitud de la contraseña.');
        $admin = DB::transaction(function () use ($datos) {
            DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            abort_if(DB::table('administrador')->where('id', 1)->exists(), 409, 'La cuenta ya está configurada.');
            $admin = (object) [
                'nombre' => $datos['nombre'],
                'correo' => strtolower($datos['correo']),
                'clave' => Hash::make($datos['clave']),
            ];
            DB::table('administrador')->insert(['id' => 1, ...(array) $admin]);

            return $admin;
        });

        return $this->emitirSesion($admin);
    }

    public function ingresar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'correo' => ['required', 'email', 'max:254'],
            'clave' => ['required', 'string', 'max:72'],
        ]);
        $admin = DB::table('administrador')->where('correo', strtolower($datos['correo']))->find(1);
        $valida = Hash::check($datos['clave'], $admin->clave ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        abort_unless($admin && $valida, 422, 'El correo o la contraseña son incorrectos.');

        return $this->emitirSesion($admin);
    }

    public function sesion(Request $request): JsonResponse
    {
        return response()->json($request->attributes->get('admin'));
    }

    public function salir(Request $request): JsonResponse
    {
        Cache::forget(SesionAdministrador::clave((string) $request->bearerToken()));

        return response()->json(['mensaje' => 'Sesión cerrada.']);
    }

    private function permiteConfigurar(Request $request): bool
    {
        return app()->environment('local') && in_array($request->ip(), ['127.0.0.1', '::1'], true);
    }

    private function emitirSesion(object $admin): JsonResponse
    {
        $token = bin2hex(random_bytes(32));
        Cache::put(SesionAdministrador::clave($token), hash('sha256', $admin->clave), now()->addHours(12));

        return response()->json([
            'token' => $token,
            'usuario' => ['nombre' => $admin->nombre, 'correo' => $admin->correo, 'rol' => 'administrador'],
        ])->header('Cache-Control', 'no-store');
    }
}

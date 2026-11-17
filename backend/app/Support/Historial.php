<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Historial
{
    public static function registrar(string $entidad, int $id, string $accion, ?object $antes = null): void
    {
        $tabla = $entidad === 'cancha' ? 'canchas' : $entidad;
        $despues = DB::table($tabla)->find($id);
        if ($antes == $despues) {
            return;
        }
        $admin = request()->attributes->get('admin');
        DB::table('historial')->insert([
            'fecha' => now()->format('Y-m-d H:i:s'),
            'usuario' => $admin['nombre'] ?? 'Sistema',
            'correo' => $admin['correo'] ?? null,
            'entidad' => $entidad,
            'registro_id' => $id,
            'accion' => $accion,
            'antes' => $antes ? json_encode($antes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'despues' => json_encode($despues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }
}

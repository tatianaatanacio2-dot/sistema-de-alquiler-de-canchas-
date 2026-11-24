<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ConfigurarAdministrador extends Command
{
    protected $signature = 'canchas:administrador {--restablecer : Cambiar la contraseña de la cuenta existente}';

    protected $description = 'Crea o recupera la cuenta administrativa desde una consola de confianza.';

    public function handle(): int
    {
        $existente = DB::table('administrador')->find(1);
        if ($existente && ! $this->option('restablecer')) {
            $this->error('La cuenta ya existe. Usa --restablecer para recuperar el acceso.');

            return self::FAILURE;
        }
        $datos = ['nombre' => $existente->nombre ?? $this->ask('Nombre'), 'correo' => $existente->correo ?? strtolower($this->ask('Correo')),
            'clave' => $this->secret('Contraseña (12 a 72 bytes)'), 'confirmacion' => $this->secret('Repite la contraseña')];
        $validacion = Validator::make($datos, ['nombre' => 'required|string|max:80', 'correo' => 'required|email|max:254', 'clave' => 'required|string|min:12|max:72', 'confirmacion' => 'same:clave']);
        if ($validacion->fails() || strlen($datos['clave'] ?? '') > 72 || str_contains($datos['clave'] ?? '', "\0")) {
            $this->error('Datos inválidos. Revisa nombre, correo y contraseña.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($datos, $existente) {
            DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
            if (! $existente && DB::table('administrador')->where('id', 1)->exists()) {
                throw new \RuntimeException('La cuenta ya fue creada por otro proceso.');
            }
            DB::table('administrador')->updateOrInsert(['id' => 1], ['nombre' => $datos['nombre'], 'correo' => $datos['correo'], 'clave' => Hash::make($datos['clave'])]);
        });
        $this->info('Cuenta guardada. Las sesiones anteriores dejan de ser válidas.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RestablecerCliente extends Command
{
    protected $signature = 'canchas:cliente-restablecer {correo}';

    protected $description = 'Recupera una cuenta cliente después de verificar su identidad por soporte.';

    public function handle(): int
    {
        $cuenta = DB::table('cuentas_clientes')->where('correo', strtolower($this->argument('correo')))->first();
        if (! $cuenta) {
            $this->error('No existe esa cuenta.');

            return self::FAILURE;
        }
        $clave = $this->secret('Nueva contraseña (12 a 72 bytes)');
        $confirmacion = $this->secret('Repite la contraseña');
        if (! is_string($clave) || strlen($clave) < 12 || strlen($clave) > 72 || str_contains($clave, "\0") || $clave !== $confirmacion) {
            $this->error('La contraseña no es válida o no coincide.');

            return self::FAILURE;
        }
        DB::table('cuentas_clientes')->where('id', $cuenta->id)->update(['clave' => Hash::make($clave)]);
        $this->info('Contraseña actualizada. Las sesiones anteriores dejan de ser válidas.');

        return self::SUCCESS;
    }
}

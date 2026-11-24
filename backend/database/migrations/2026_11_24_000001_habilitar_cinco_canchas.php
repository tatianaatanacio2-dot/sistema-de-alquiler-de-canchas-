<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('canchas')) {
            throw new RuntimeException('Primero importa canchas_alquiler.sql en la base de datos.');
        }

        // La instalación original permitía solamente el id 1. No se alteran
        // las restricciones de tarifa, estado ni las claves de las reservas.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $restricciones = DB::select("SELECT tc.CONSTRAINT_NAME AS nombre, cc.CHECK_CLAUSE AS clausula
                FROM information_schema.TABLE_CONSTRAINTS tc
                INNER JOIN information_schema.CHECK_CONSTRAINTS cc
                    ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
                WHERE tc.TABLE_SCHEMA = DATABASE() AND tc.TABLE_NAME = 'canchas' AND tc.CONSTRAINT_TYPE = 'CHECK'");
            $esMariaDb = str_contains(strtolower(DB::selectOne('SELECT VERSION() AS version')->version), 'mariadb');
            foreach ($restricciones as $restriccion) {
                $clausula = strtolower(preg_replace('/[\s`()]/', '', $restriccion->clausula));
                if (in_array($clausula, ['id=1', '1=id'], true)) {
                    $nombre = str_replace('`', '``', $restriccion->nombre);
                    $operacion = $esMariaDb ? 'DROP CONSTRAINT' : 'DROP CHECK';
                    DB::statement("ALTER TABLE `canchas` {$operacion} `{$nombre}`");
                }
            }
        }

        DB::transaction(function () {
            $tarifa = DB::table('canchas')->where('id', 1)->value('tarifa') ?: 30;
            foreach (range(1, 5) as $id) {
                DB::table('canchas')->insertOrIgnore([
                    'id' => $id, 'nombre' => 'Cancha '.$id, 'tarifa' => $tarifa, 'activa' => 1,
                ]);
            }
            DB::table('canchas')->where('id', 1)->where('nombre', 'Cancha de césped sintético')
                ->update(['nombre' => 'Cancha 1']);
        });
    }

    public function down(): void
    {
        // Conserva las cinco canchas: podrían tener reservas y pagos asociados.
        // Volver a ejecutar up() es seguro y mantiene sus nombres y tarifas.
    }
};

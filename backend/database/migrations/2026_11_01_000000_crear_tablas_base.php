<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admite tanto instalaciones nuevas como las que importaron el SQL original.
        if (! Schema::hasTable('administrador')) {
            Schema::create('administrador', function (Blueprint $t) {
                $t->unsignedTinyInteger('id')->primary();
                $t->string('nombre', 80);
                $t->string('correo', 254);
                $t->string('clave');
            });
        }
        if (! Schema::hasTable('canchas')) {
            Schema::create('canchas', function (Blueprint $t) {
                $t->unsignedInteger('id')->primary();
                $t->string('nombre', 100);
                $t->unsignedInteger('tarifa')->default(30);
                $t->boolean('activa')->default(true);
            });
            DB::table('canchas')->insert(['id' => 1, 'nombre' => 'Cancha de césped sintético']);
        }
        if (! Schema::hasTable('clientes')) {
            Schema::create('clientes', function (Blueprint $t) {
                $t->increments('id');
                $t->string('dni', 20)->default('');
                $t->string('nombre', 120)->default('');
                $t->string('telefono', 30)->default('');
                $t->string('correo', 254)->default('');
            });
        }
        if (! Schema::hasTable('reservas')) {
            Schema::create('reservas', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('cancha_id');
                $t->unsignedInteger('cliente_id')->nullable();
                $t->char('inicio', 16);
                $t->char('fin', 16);
                $t->unsignedInteger('tarifa');
                $t->decimal('total', 22, 2)->unsigned();
                $t->enum('estado', ['confirmada', 'completada', 'cancelada'])->default('confirmada');
                $t->foreign('cancha_id')->references('id')->on('canchas')->restrictOnDelete();
                $t->foreign('cliente_id')->references('id')->on('clientes')->restrictOnDelete();
                $t->index(['cancha_id', 'inicio', 'fin'], 'reservas_horario');
            });
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE reservas ADD CONSTRAINT reservas_intervalo CHECK (inicio < fin)');
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('La migración base conserva datos. Restaura una copia verificada para revertirla.');
    }
};

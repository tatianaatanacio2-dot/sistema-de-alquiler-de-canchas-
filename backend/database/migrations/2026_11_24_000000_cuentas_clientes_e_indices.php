<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_clientes', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('cliente_id')->unique();
            $t->foreign('cliente_id')->references('id')->on('clientes')->restrictOnDelete();
            $t->string('correo', 254)->unique();
            $t->string('clave');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::table('clientes', fn (Blueprint $t) => $t->index('nombre', 'clientes_nombre'));
        Schema::table('pagos', fn (Blueprint $t) => $t->index(['reserva_id', 'id'], 'pagos_reserva_orden'));
        Schema::table('reservas', fn (Blueprint $t) => $t->index(['inicio', 'estado'], 'reservas_fecha_estado'));
    }

    public function down(): void
    {
        throw new RuntimeException('No se eliminan cuentas automáticamente. Restaura una copia verificada.');
    }
};

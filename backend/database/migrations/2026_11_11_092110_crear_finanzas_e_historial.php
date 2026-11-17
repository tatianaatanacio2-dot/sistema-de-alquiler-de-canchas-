<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->uuid('operacion')->unique();
            $table->unsignedInteger('reserva_id');
            $table->foreign('reserva_id')->references('id')->on('reservas')->restrictOnDelete();
            $table->enum('tipo', ['cobro', 'devolucion']);
            $table->decimal('monto', 12, 2);
            $table->string('metodo', 20);
            $table->string('nota', 250)->default('');
            $table->dateTime('fecha')->index();
        });
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->uuid('operacion')->unique();
            $table->string('concepto', 150);
            $table->decimal('monto', 12, 2);
            $table->string('metodo', 20);
            $table->dateTime('fecha')->index();
            $table->boolean('anulado')->default(false);
        });
        Schema::create('historial', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fecha')->index();
            $table->string('usuario', 80);
            $table->string('correo', 254)->nullable();
            $table->string('entidad', 20)->index();
            $table->unsignedBigInteger('registro_id');
            $table->string('accion', 60);
            $table->json('antes')->nullable();
            $table->json('despues');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial');
        Schema::dropIfExists('gastos');
        Schema::dropIfExists('pagos');
    }
};

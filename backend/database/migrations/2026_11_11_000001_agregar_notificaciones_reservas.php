<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lista_espera')) {
            Schema::create('lista_espera', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('cliente_id');
                $table->char('inicio', 16);
                $table->char('fin', 16);
                $table->enum('estado', ['pendiente', 'disponible', 'avisado', 'atendida', 'cancelada'])->default('pendiente');
                $table->timestamp('creado_en')->useCurrent();
                $table->foreign('cliente_id')->references('id')->on('clientes');
                $table->index(['estado', 'inicio', 'fin'], 'lista_espera_horario');
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('lista_espera');
    }
};

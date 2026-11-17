<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pagos')) {
            Schema::create('pagos', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('reserva_id');
                $table->decimal('monto', 22, 2)->unsigned();
                $table->enum('metodo', ['efectivo', 'transferencia', 'tarjeta', 'yape_plin', 'otro']);
                $table->string('referencia', 120)->nullable();
                $table->timestamp('registrado_en')->useCurrent();
                $table->foreign('reserva_id')->references('id')->on('reservas')->cascadeOnDelete();
                $table->index(['reserva_id', 'registrado_en'], 'pagos_reserva_fecha');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};

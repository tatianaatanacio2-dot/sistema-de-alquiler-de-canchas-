<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('notificaciones_reserva');

        if (Schema::hasColumn('reservas', 'canal_notificacion')) {
            Schema::table('reservas', function (Blueprint $table): void {
                $table->dropColumn('canal_notificacion');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('reservas', 'canal_notificacion')) {
            Schema::table('reservas', function (Blueprint $table): void {
                $table->enum('canal_notificacion', ['ninguna', 'correo', 'whatsapp', 'ambas'])
                    ->default('ninguna')->after('estado');
            });
        }

        if (! Schema::hasTable('notificaciones_reserva')) {
            Schema::create('notificaciones_reserva', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('reserva_id');
                $table->enum('tipo', ['confirmacion', 'recordatorio']);
                $table->enum('canal', ['correo', 'whatsapp']);
                $table->enum('estado', ['enviado', 'fallido']);
                $table->timestamp('enviado_en')->nullable();
                $table->string('detalle', 500)->nullable();
                $table->unique(['reserva_id', 'tipo', 'canal'], 'notificaciones_unicas');
                $table->foreign('reserva_id')->references('id')->on('reservas')->cascadeOnDelete();
            });
        }
    }
};

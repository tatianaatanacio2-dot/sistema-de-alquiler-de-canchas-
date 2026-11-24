<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagos') && ! Schema::hasColumn('pagos', 'operacion')) {
            if (Schema::hasTable('pagos_legacy')) {
                throw new \RuntimeException('Ya existe pagos_legacy; revisa la conversión de pagos antes de continuar.');
            }
            Schema::rename('pagos', 'pagos_legacy');
        }

        if (! Schema::hasTable('pagos')) {
            Schema::create('pagos', function (Blueprint $table) {
                $table->id();
                $table->uuid('operacion')->unique();
                $table->unsignedInteger('reserva_id');
                $table->foreign('reserva_id', 'pagos_finanzas_reserva_foreign')->references('id')->on('reservas')->restrictOnDelete();
                $table->enum('tipo', ['cobro', 'devolucion']);
                $table->decimal('monto', 12, 2);
                $table->string('metodo', 20);
                $table->string('nota', 250)->default('');
                $table->dateTime('fecha')->index();
            });
        }

        if (Schema::hasTable('pagos_legacy')) {
            foreach (DB::table('pagos_legacy')->orderBy('id')->cursor() as $pago) {
                $operacion = $this->operacionLegada((int) $pago->id);
                if (DB::table('pagos')->where('operacion', $operacion)->exists()) {
                    continue;
                }

                DB::table('pagos')->insert([
                    'operacion' => $operacion,
                    'reserva_id' => $pago->reserva_id,
                    'tipo' => 'cobro',
                    'monto' => $pago->monto,
                    'metodo' => $pago->metodo === 'yape_plin' ? 'yape' : $pago->metodo,
                    'nota' => $pago->referencia ?? '',
                    'fecha' => $pago->registrado_en,
                ]);
            }
        }
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

        if (Schema::hasTable('pagos_legacy')) {
            Schema::rename('pagos_legacy', 'pagos');
        }
    }

    private function operacionLegada(int $id): string
    {
        $hash = hash('sha256', 'pago-legado-'.$id);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-5'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }
};

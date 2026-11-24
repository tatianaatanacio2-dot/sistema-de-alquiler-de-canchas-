<?php

namespace Tests\Feature;

use App\Http\Middleware\SesionAdministrador;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanzasTest extends TestCase
{
    private int $reservaId;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('administrador', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre');
            $table->string('correo');
            $table->string('clave');
        });
        Schema::create('canchas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre');
            $table->unsignedInteger('tarifa');
            $table->boolean('activa');
        });
        Schema::create('clientes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('dni')->default('');
            $table->string('nombre')->default('');
            $table->string('telefono')->default('');
            $table->string('correo')->default('');
        });
        Schema::create('reservas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cancha_id');
            $table->unsignedInteger('cliente_id')->nullable();
            $table->string('inicio');
            $table->string('fin');
            $table->unsignedInteger('tarifa');
            $table->decimal('total', 22, 2);
            $table->string('estado')->default('confirmada');
        });
        (require __DIR__.'/../../database/migrations/2026_11_11_092110_crear_finanzas_e_historial.php')->up();
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(10, 0));
        DB::table('administrador')->insert(['id' => 1, 'nombre' => 'Encargada de prueba', 'correo' => 'prueba@example.test', 'clave' => 'hash-de-prueba']);
        DB::table('canchas')->insert(['id' => 1, 'nombre' => 'Cancha sintética', 'tarifa' => 30, 'activa' => true]);
        $this->reservaId = DB::table('reservas')->insertGetId([
            'cancha_id' => 1, 'cliente_id' => null, 'inicio' => '2026-11-12T18:00',
            'fin' => '2026-11-12T20:00', 'tarifa' => 30, 'total' => '60.00',
        ]);
        $token = str_repeat('a', 64);
        Cache::put(SesionAdministrador::clave($token), hash('sha256', 'hash-de-prueba'), 3600);
        $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function cobro(string $monto, string $tipo = 'cobro'): array
    {
        return [
            'operacion' => (string) Str::uuid(), 'reserva_id' => $this->reservaId,
            'tipo' => $tipo, 'monto' => $monto, 'metodo' => 'efectivo', 'nota' => '',
        ];
    }

    public function test_adelantos_reintentos_y_saldo_de_reserva_preexistente(): void
    {
        $this->getJson('/api/reservas')->assertOk()->assertJsonPath('0.cliente', 'Sin cliente')->assertJsonPath('0.abonado', 0);
        $datos = $this->cobro('20.25');
        $id = $this->postJson('/api/pagos', $datos)->assertOk()->json('id');
        $this->postJson('/api/pagos', $datos)->assertOk()->assertJsonPath('id', $id);
        $this->postJson('/api/pagos', array_replace($datos, ['monto' => '21.25']))->assertStatus(409);
        $this->postJson('/api/pagos', $this->cobro('39.76'))->assertStatus(409);
        $this->postJson('/api/pagos', $this->cobro('39.75'))->assertOk();
        $this->assertDatabaseCount('pagos', 2);
        $this->assertDatabaseCount('historial', 2);
        $this->getJson('/api/reservas')->assertOk();
        $abonado = $this->getJson('/api/reservas')->json('0.abonado');
        $this->assertEquals(60, $abonado);
    }

    public function test_cancelacion_con_devolucion_y_caja_por_fecha_de_cobro(): void
    {
        $this->postJson('/api/pagos', $this->cobro('40.00'))->assertOk();
        $this->patchJson('/api/reservas/'.$this->reservaId.'/estado', ['estado' => 'cancelada'])->assertOk();
        $this->postJson('/api/pagos', $this->cobro('1.00'))->assertStatus(409);
        $this->postJson('/api/pagos', $this->cobro('40.01', 'devolucion'))->assertStatus(409);
        $devolucion = $this->cobro('15.50', 'devolucion');
        $this->postJson('/api/pagos', $devolucion)->assertOk();
        $this->postJson('/api/pagos', $devolucion)->assertOk();
        $this->getJson('/api/caja?fecha=2026-11-11')->assertOk()
            ->assertJsonPath('totales.ingresos', '40.00')->assertJsonPath('totales.devoluciones', '15.50')
            ->assertJsonPath('totales.neto', '24.50')->assertJsonPath('porMetodo.efectivo.neto', '24.50');
        $this->getJson('/api/caja?fecha=2026-11-12')->assertOk()->assertJsonPath('totales.ingresos', '0.00');
    }

    public function test_no_se_puede_reducir_total_por_debajo_de_lo_abonado(): void
    {
        $this->postJson('/api/pagos', $this->cobro('50.00'))->assertOk();
        $datos = ['inicio' => '2026-11-12T18:00', 'fin' => '2026-11-12T19:00', 'cliente_id' => null];
        $this->putJson('/api/reservas/'.$this->reservaId, $datos)->assertStatus(409);
        $this->assertDatabaseHas('reservas', ['id' => $this->reservaId, 'fin' => '2026-11-12T20:00']);
        $this->postJson('/api/pagos', $this->cobro('20.00', 'devolucion'))->assertOk();
        $this->putJson('/api/reservas/'.$this->reservaId, $datos)->assertOk();
    }

    public function test_gastos_anulados_y_metodos_de_pago(): void
    {
        $this->postJson('/api/pagos', array_replace($this->cobro('25.00'), ['metodo' => 'yape']))->assertOk();
        $datos = ['operacion' => (string) Str::uuid(), 'concepto' => 'Limpieza', 'monto' => '10.50', 'metodo' => 'efectivo'];
        $id = $this->postJson('/api/gastos', $datos)->assertOk()->json('id');
        $this->postJson('/api/gastos', $datos)->assertOk()->assertJsonPath('id', $id);
        $this->getJson('/api/caja')->assertOk()->assertJsonPath('totales.neto', '14.50')
            ->assertJsonPath('porMetodo.efectivo.neto', '-10.50')->assertJsonPath('porMetodo.yape.ingresos', '25.00');
        $this->patchJson('/api/gastos/'.$id.'/anular')->assertOk();
        $this->patchJson('/api/gastos/'.$id.'/anular')->assertOk();
        $this->assertDatabaseCount('gastos', 1);
        $this->assertDatabaseCount('historial', 3);
        $this->getJson('/api/caja')->assertOk()->assertJsonPath('totales.gastos', '0.00')->assertJsonPath('totales.neto', '25.00');
    }

    public function test_historial_guarda_usuario_y_valores_sin_aceptar_actor_del_cliente(): void
    {
        $cliente = $this->postJson('/api/clientes', ['nombre' => 'Ana', 'usuario' => 'Otra persona'])->assertOk()->json('id');
        $this->putJson('/api/clientes/'.$cliente, ['nombre' => 'Ana Ruiz'])->assertOk();
        $this->putJson('/api/cancha', ['nombre' => 'Mi cancha', 'tarifa' => 35, 'activa' => true])->assertOk();
        $nueva = $this->postJson('/api/reservas', [
            'inicio' => '2026-11-13T16:00', 'fin' => '2026-11-13T17:00',
            'cliente_nuevo' => ['dni' => '00123456'],
        ])->assertOk()->json('id');
        $this->getJson('/api/historial?entidad=clientes&fecha=2026-11-11')->assertOk()
            ->assertJsonPath('data.1.usuario', 'Encargada de prueba');
        $cambio = DB::table('historial')->where('entidad', 'clientes')->where('registro_id', $cliente)->where('accion', 'Editar cliente')->first();
        $this->assertSame('Ana', json_decode($cambio->antes)->nombre);
        $this->assertSame('Ana Ruiz', json_decode($cambio->despues)->nombre);
        $this->assertDatabaseHas('historial', ['entidad' => 'reservas', 'registro_id' => $nueva, 'accion' => 'Crear reserva']);
        $this->assertDatabaseHas('historial', ['entidad' => 'cancha', 'accion' => 'Editar cancha']);
    }

    public function test_importes_invalidos_y_autorizacion(): void
    {
        foreach (['0', '-1.00', '1.001', 'abc'] as $monto) {
            $this->postJson('/api/pagos', $this->cobro($monto))->assertStatus(422);
        }
        $this->postJson('/api/pagos', array_replace($this->cobro('1.00'), ['metodo' => 'otro']))->assertStatus(422);
        $this->flushHeaders();
        foreach (['/api/pagos?reserva_id='.$this->reservaId, '/api/caja', '/api/historial'] as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_reserva_con_adelanto_guarda_pago_caja_e_historial(): void
    {
        $datos = [
            'inicio' => '2026-11-13T16:00', 'fin' => '2026-11-13T17:30',
            'cliente_nuevo' => ['nombre' => 'Cliente con adelanto'],
            'pago_inicial' => [
                'operacion' => (string) Str::uuid(), 'monto' => '20.50',
                'metodo' => 'yape', 'nota' => 'Operación de ejemplo',
            ],
        ];
        $reserva = $this->postJson('/api/reservas', $datos)->assertOk()->assertJsonPath('metodo_pago', 'yape');
        $this->assertEquals(20.50, $reserva->json('abonado'));
        $this->assertEquals(45, $reserva->json('total'));
        $this->assertDatabaseHas('pagos', [
            'reserva_id' => $reserva->json('id'), 'tipo' => 'cobro', 'metodo' => 'yape', 'nota' => 'Operación de ejemplo',
        ]);
        $this->getJson('/api/caja')->assertOk()->assertJsonPath('porMetodo.yape.ingresos', '20.50');
        $this->assertDatabaseCount('historial', 3);
        $this->postJson('/api/reservas', $datos)->assertStatus(409);
        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_pago_inicial_invalido_revierte_toda_la_reserva(): void
    {
        $datos = [
            'inicio' => '2026-11-13T16:00', 'fin' => '2026-11-13T17:00',
            'cliente_nuevo' => ['nombre' => 'Cliente de prueba'],
            'pago_inicial' => ['operacion' => (string) Str::uuid(), 'monto' => '30.01', 'metodo' => 'efectivo'],
        ];
        $this->postJson('/api/reservas', $datos)->assertStatus(409);
        $datos['pago_inicial']['monto'] = '20.00';
        $datos['pago_inicial']['metodo'] = 'otro';
        $this->postJson('/api/reservas', $datos)->assertStatus(422);
        $this->assertDatabaseCount('reservas', 1);
        $this->assertDatabaseCount('clientes', 0);
        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseCount('historial', 0);
    }

    public function test_disponibilidad_excluye_canceladas_y_recorta_intervalos_pasados(): void
    {
        $this->getJson('/api/disponibilidad?fecha=2026-11-12')->assertOk()
            ->assertJsonPath('libres.0.inicio', '2026-11-12T00:00')->assertJsonPath('libres.0.fin', '2026-11-12T18:00')
            ->assertJsonPath('libres.1.inicio', '2026-11-12T20:00')->assertJsonPath('libres.1.fin', '2026-11-13T00:00');
        DB::table('reservas')->insert([
            ['cancha_id' => 1, 'inicio' => '2026-11-11T23:00', 'fin' => '2026-11-12T01:00', 'tarifa' => 30, 'total' => 60, 'estado' => 'confirmada'],
            ['cancha_id' => 1, 'inicio' => '2026-11-12T20:00', 'fin' => '2026-11-12T21:00', 'tarifa' => 30, 'total' => 30, 'estado' => 'cancelada'],
        ]);
        $this->getJson('/api/disponibilidad?fecha=2026-11-12')->assertOk()
            ->assertJsonCount(2, 'ocupados')->assertJsonPath('libres.0.inicio', '2026-11-12T01:00')
            ->assertJsonPath('libres.1.inicio', '2026-11-12T20:00');
        $this->getJson('/api/disponibilidad?fecha=2026-11-11')->assertOk()
            ->assertJsonPath('libres.0.inicio', '2026-11-11T10:00');
        $this->getJson('/api/disponibilidad?fecha=2026-11-10')->assertOk()->assertJsonCount(0, 'libres');
        DB::table('canchas')->where('id', 1)->update(['activa' => false]);
        $this->getJson('/api/disponibilidad?fecha=2026-11-12')->assertOk()->assertJsonCount(0, 'libres');
    }

    public function test_demostracion_es_reutilizable_y_actualiza_el_resumen(): void
    {
        $this->artisan('canchas:demostracion')->assertSuccessful();
        $tablas = ['clientes', 'reservas', 'pagos', 'gastos', 'historial'];
        $cantidades = [];
        foreach ($tablas as $tabla) {
            $cantidades[$tabla] = DB::table($tabla)->count();
        }
        $this->artisan('canchas:demostracion')->assertSuccessful();
        foreach ($cantidades as $tabla => $cantidad) {
            $this->assertDatabaseCount($tabla, $cantidad);
        }
        $this->getJson('/api/resumen')->assertOk()->assertJsonPath('demostracion', true)
            ->assertJsonPath('cajaHoy.gastos', '27.50');
        $this->assertDatabaseHas('reservas', ['estado' => 'completada']);
        $this->assertDatabaseHas('reservas', ['estado' => 'cancelada']);
        $this->assertDatabaseHas('historial', ['usuario' => 'Demostración']);
        $cruces = DB::table('reservas as a')->join('reservas as b', function ($join) {
            $join->on('a.id', '<', 'b.id')->on('a.inicio', '<', 'b.fin')->on('a.fin', '>', 'b.inicio');
        })->where('a.estado', '<>', 'cancelada')->where('b.estado', '<>', 'cancelada')->count();
        $this->assertSame(0, $cruces);
    }

    public function test_constancia_incluye_datos_y_pagos_de_la_reserva(): void
    {
        $this->postJson('/api/pagos', $this->cobro('20.00'))->assertOk();
        $detalle = $this->getJson('/api/reservas/'.$this->reservaId)->assertOk()
            ->assertJsonPath('reserva.id', $this->reservaId)->assertJsonPath('cliente', null)
            ->assertJsonPath('cancha.nombre', 'Cancha sintética')->assertJsonCount(1, 'pagos');
        $this->assertEquals(20, $detalle->json('reserva.abonado'));
        $this->getJson('/api/reservas/999999')->assertNotFound();
        $this->flushHeaders();
        $this->getJson('/api/reservas/'.$this->reservaId)->assertUnauthorized();
    }

    public function test_historial_cliente_agrupa_solo_sus_reservas(): void
    {
        $ana = $this->postJson('/api/clientes', ['nombre' => 'Ana', 'dni' => '00123456'])->assertOk()->json('id');
        $bea = $this->postJson('/api/clientes', ['nombre' => 'Bea'])->assertOk()->json('id');
        $reserva = $this->postJson('/api/reservas', [
            'cliente_id' => $ana, 'inicio' => '2026-11-13T10:00', 'fin' => '2026-11-13T11:00',
        ])->assertOk()->json('id');
        $this->postJson('/api/reservas', [
            'cliente_id' => $bea, 'inicio' => '2026-11-13T11:00', 'fin' => '2026-11-13T12:00',
        ])->assertOk();
        $this->getJson('/api/clientes/'.$ana.'/historial')->assertOk()
            ->assertJsonPath('cliente.dni', '00123456')->assertJsonCount(1, 'reservas')->assertJsonPath('reservas.0.id', $reserva);
        $this->getJson('/api/clientes/999999/historial')->assertNotFound();
    }

    public function test_reporte_filtra_por_inicio_y_distingue_saldo_de_devolucion(): void
    {
        $this->postJson('/api/pagos', $this->cobro('20.00'))->assertOk();
        $cancelada = $this->postJson('/api/reservas', [
            'inicio' => '2026-11-13T10:00', 'fin' => '2026-11-13T11:00',
            'pago_inicial' => ['operacion' => (string) Str::uuid(), 'monto' => '10.00', 'metodo' => 'plin'],
        ])->assertOk()->json('id');
        $this->patchJson('/api/reservas/'.$cancelada.'/estado', ['estado' => 'cancelada'])->assertOk();
        $this->postJson('/api/reservas', ['inicio' => '2026-11-14T00:00', 'fin' => '2026-11-14T01:00'])->assertOk();
        $this->getJson('/api/reportes/reservas?desde=2026-11-12&hasta=2026-11-13')->assertOk()
            ->assertJsonCount(2, 'reservas')->assertJsonPath('totales.programado', '60.00')
            ->assertJsonPath('totales.abonado', '30.00')->assertJsonPath('totales.pendiente', '40.00')
            ->assertJsonPath('totales.porDevolver', '10.00');
        $this->getJson('/api/reportes/reservas?desde=2026-11-12&hasta=2026-11-13&estado=confirmada')->assertOk()
            ->assertJsonCount(1, 'reservas')->assertJsonPath('totales.porDevolver', '0.00');
        $this->getJson('/api/reportes/reservas?desde=2026-11-13&hasta=2026-11-12')->assertStatus(422);
        $this->getJson('/api/reportes/reservas?desde=2024-01-01&hasta=2026-11-12')->assertStatus(422);
        $this->flushHeaders();
        $this->getJson('/api/reportes/reservas?desde=2026-11-12&hasta=2026-11-13')->assertUnauthorized();
    }

    public function test_editar_pago_actualiza_caja_saldo_y_auditoria_sin_duplicar(): void
    {
        $pago = $this->postJson('/api/pagos', $this->cobro('20.00'))->assertOk()->json();
        $datos = ['monto' => '30.50', 'metodo' => 'yape', 'nota' => 'Referencia corregida', 'version' => $pago['version']];
        $this->travelTo(now()->addDay());
        Cache::put(SesionAdministrador::clave(str_repeat('a', 64)), hash('sha256', 'hash-de-prueba'), 3600);
        $editado = $this->putJson('/api/pagos/'.$pago['id'], $datos)->assertOk()->assertJsonPath('fecha', $pago['fecha']);
        $this->assertNotSame($pago['version'], $editado->json('version'));
        $this->putJson('/api/pagos/'.$pago['id'], $datos)->assertOk();
        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseCount('historial', 2);
        $this->getJson('/api/caja?fecha=2026-11-11')->assertOk()
            ->assertJsonPath('totales.ingresos', '30.50')->assertJsonPath('porMetodo.yape.ingresos', '30.50')
            ->assertJsonPath('porMetodo.efectivo.ingresos', '0.00');
        $this->getJson('/api/caja?fecha=2026-11-12')->assertOk()->assertJsonPath('totales.ingresos', '0.00');
        $detalle = $this->getJson('/api/reservas/'.$this->reservaId)->assertOk();
        $this->assertEquals(30.50, $detalle->json('reserva.abonado'));
        $historial = DB::table('historial')->where('accion', 'Editar pago')->first();
        $this->assertEquals(20, json_decode($historial->antes)->monto);
        $this->assertEquals(30.50, json_decode($historial->despues)->monto);
        $this->assertSame('Encargada de prueba', $historial->usuario);
    }

    public function test_editar_pago_rechaza_sobrepago_y_ediciones_desactualizadas(): void
    {
        $pago = $this->postJson('/api/pagos', $this->cobro('20.00'))->assertOk()->json();
        $datos = ['monto' => '25.00', 'metodo' => 'plin', 'nota' => '', 'version' => $pago['version']];
        $this->putJson('/api/pagos/'.$pago['id'], $datos)->assertOk();
        $this->putJson('/api/pagos/'.$pago['id'], array_replace($datos, ['monto' => '30.00']))->assertStatus(409);
        $actual = $this->getJson('/api/pagos?reserva_id='.$this->reservaId)->assertOk()->json('0');
        $this->postJson('/api/pagos', $this->cobro('35.00'))->assertOk();
        $this->putJson('/api/pagos/'.$pago['id'], array_replace($datos, ['version' => $actual['version'], 'monto' => '25.01']))->assertStatus(409);
        $this->assertDatabaseCount('pagos', 2);
        $this->assertDatabaseCount('historial', 3);
        $this->assertDatabaseHas('pagos', ['id' => $pago['id'], 'metodo' => 'plin', 'monto' => 25]);
    }

    public function test_editar_cobro_no_invalida_una_devolucion_anterior(): void
    {
        $primero = $this->postJson('/api/pagos', $this->cobro('30.00'))->assertOk()->json();
        $devolucion = $this->postJson('/api/pagos', $this->cobro('30.00', 'devolucion'))->assertOk()->json();
        $this->postJson('/api/pagos', $this->cobro('30.00'))->assertOk();
        $this->putJson('/api/pagos/'.$primero['id'], [
            'monto' => '20.00', 'metodo' => 'efectivo', 'nota' => '', 'version' => $primero['version'],
        ])->assertStatus(409);
        $this->putJson('/api/pagos/'.$devolucion['id'], [
            'monto' => '20.00', 'metodo' => 'efectivo', 'nota' => 'Corrección', 'version' => $devolucion['version'],
        ])->assertOk();
        $this->assertEquals(40, $this->getJson('/api/reservas/'.$this->reservaId)->json('reserva.abonado'));
    }

    public function test_editar_pago_de_reserva_cancelada_y_validaciones(): void
    {
        $pago = $this->postJson('/api/pagos', $this->cobro('30.00'))->assertOk()->json();
        $this->patchJson('/api/reservas/'.$this->reservaId.'/estado', ['estado' => 'cancelada'])->assertOk();
        $datos = ['monto' => '25.00', 'metodo' => 'tarjeta', 'nota' => '', 'version' => $pago['version']];
        $this->putJson('/api/pagos/'.$pago['id'], $datos)->assertOk();
        foreach (['0', '-1.00', '1.001'] as $monto) {
            $this->putJson('/api/pagos/'.$pago['id'], array_replace($datos, ['monto' => $monto]))->assertStatus(422);
        }
        $this->putJson('/api/pagos/999999', $datos)->assertNotFound();
        $this->flushHeaders();
        $this->putJson('/api/pagos/'.$pago['id'], $datos)->assertUnauthorized();
    }
}

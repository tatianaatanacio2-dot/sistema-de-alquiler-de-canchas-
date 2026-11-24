<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeguridadTest extends TestCase
{
    public function test_recuperar_administrador_invalida_sesiones_previas(): void
    {
        DB::table('administrador')->insert(['id' => 1, 'nombre' => 'Admin', 'correo' => 'admin@example.test', 'clave' => Hash::make('Anterior-segura-2026')]);
        $token = $this->postJson('/api/ingresar', ['correo' => 'admin@example.test', 'clave' => 'Anterior-segura-2026'])->assertOk()->json('token');
        $this->artisan('canchas:administrador --restablecer')
            ->expectsQuestion('Contraseña (12 a 72 bytes)', 'Nueva-segura-2026')
            ->expectsQuestion('Repite la contraseña', 'Nueva-segura-2026')->assertSuccessful();
        $this->withToken($token)->getJson('/api/sesion')->assertUnauthorized();
        $this->postJson('/api/ingresar', ['correo' => 'admin@example.test', 'clave' => 'Nueva-segura-2026'])->assertOk();
    }

    public function test_recuperar_cliente_invalida_sesiones_previas(): void
    {
        $token = $this->cliente();
        $this->artisan('canchas:cliente-restablecer ana@example.test')
            ->expectsQuestion('Nueva contraseña (12 a 72 bytes)', 'Nueva-segura-2026')
            ->expectsQuestion('Repite la contraseña', 'Nueva-segura-2026')->assertSuccessful();
        $this->withToken($token)->getJson('/api/portal/reservas')->assertUnauthorized();
        $this->postJson('/api/portal/ingresar', ['correo' => 'ana@example.test', 'clave' => 'Nueva-segura-2026'])->assertOk();
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach (glob(database_path('migrations/*.php')) as $archivo) {
            (require $archivo)->up();
        }
        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(10, 0));
    }

    private function cliente(string $correo = 'ana@example.test'): string
    {
        return $this->postJson('/api/portal/registrar', ['nombre' => 'Ana', 'correo' => $correo, 'clave' => 'Una-clave-segura-2026'])->assertOk()->assertJsonPath('usuario.rol', 'cliente')->json('token');
    }

    public function test_migraciones_nuevas_y_compatibilidad_con_base_preexistente(): void
    {
        DB::table('clientes')->insert(['nombre' => 'Cliente conservado']);
        (require database_path('migrations/2026_11_01_000000_crear_tablas_base.php'))->up();
        $this->assertDatabaseHas('clientes', ['nombre' => 'Cliente conservado']);
        $this->assertDatabaseCount('canchas', 1);
        $this->assertSame('America/Lima', config('app.timezone'));
    }

    public function test_registro_inicio_y_cierre_de_sesion_y_separacion_de_roles(): void
    {
        $token = $this->cliente();
        $cuenta = DB::table('cuentas_clientes')->first();
        $this->assertTrue(Hash::check('Una-clave-segura-2026', $cuenta->clave));
        $this->withToken($token)->getJson('/api/clientes')->assertUnauthorized();
        $this->getJson('/api/portal/reservas')->assertOk()->assertExactJson([])->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/api/portal/salir')->assertOk();
        $this->getJson('/api/portal/reservas')->assertUnauthorized();
        $this->postJson('/api/portal/ingresar', ['correo' => 'ana@example.test', 'clave' => 'incorrecta'])->assertStatus(422);
        $this->postJson('/api/portal/ingresar', ['correo' => 'ANA@example.test', 'clave' => 'Una-clave-segura-2026'])->assertOk();
        $this->postJson('/api/portal/registrar', ['nombre' => 'Duplicada', 'correo' => 'ana@example.test', 'clave' => 'Una-clave-segura-2026'])->assertStatus(409);
        $this->assertDatabaseCount('cuentas_clientes', 1);
    }

    public function test_cliente_solo_puede_ver_y_cancelar_sus_reservas(): void
    {
        $ana = $this->cliente();
        $this->withToken($ana);
        $reserva = $this->postJson('/api/portal/reservas', ['inicio' => '2026-11-25T18:00', 'fin' => '2026-11-25T19:30'])->assertOk()->json('id');
        $bea = $this->cliente('bea@example.test');
        $this->withToken($bea)->getJson('/api/portal/reservas')->assertExactJson([]);
        $this->patchJson('/api/portal/reservas/'.$reserva.'/cancelar')->assertNotFound();
        $this->postJson('/api/portal/reservas', ['inicio' => '2026-11-25T18:30', 'fin' => '2026-11-25T20:00'])->assertStatus(409);
        $this->withToken($ana)->getJson('/api/portal/reservas')->assertJsonCount(1)->assertJsonPath('0.id', $reserva);
        $this->patchJson('/api/portal/reservas/'.$reserva.'/cancelar')->assertOk()->assertJsonPath('estado', 'cancelada');
        $this->withToken($bea)->postJson('/api/portal/reservas', ['inicio' => '2026-11-25T18:30', 'fin' => '2026-11-25T20:00'])->assertOk();
    }

    public function test_backend_calcula_importes_y_rechaza_suplantacion_y_cobros_del_cliente(): void
    {
        $this->withToken($this->cliente());
        $base = ['inicio' => '2026-11-25T18:00', 'fin' => '2026-11-25T19:30'];
        foreach (['cliente_id' => 1, 'total' => '0.01', 'tarifa' => 1, 'pago_inicial' => ['monto' => '45.00']] as $campo => $valor) {
            $this->postJson('/api/portal/reservas', $base + [$campo => $valor])->assertStatus(422);
        }
        $this->assertEquals(45, $this->postJson('/api/portal/reservas', $base)->assertOk()->json('total'));
        $this->postJson('/api/pagos', [])->assertUnauthorized();
        $this->postJson('/api/portal/reservas', ['inicio' => '2026-11-25T21:00', 'fin' => '2026-11-27T21:00'])->assertStatus(422);
    }

    public function test_intervalos_adyacentes_validacion_y_no_apropiacion_de_contactos(): void
    {
        $contacto = DB::table('clientes')->insertGetId(['nombre' => 'Previo', 'correo' => 'ana@example.test']);
        $this->withToken($this->cliente());
        $this->assertNotEquals($contacto, DB::table('cuentas_clientes')->value('cliente_id'));
        $this->postJson('/api/portal/reservas', ['inicio' => '2026-11-25T18:00', 'fin' => '2026-11-25T19:00'])->assertOk();
        $this->postJson('/api/portal/reservas', ['inicio' => '2026-11-25T19:00', 'fin' => '2026-11-25T20:00'])->assertOk();
        $this->postJson('/api/portal/reservas', ['inicio' => '2026-11-25T19:00', 'fin' => '2026-11-25T19:00'])->assertStatus(422);
        $this->postJson('/api/portal/reservas', ['inicio' => '2026-11-23T19:00', 'fin' => '2026-11-23T20:00'])->assertStatus(422);
    }

    public function test_produccion_bloquea_http_configuracion_web_y_datos_demo(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->getJson('/api/acceso')->assertStatus(400);
        $this->getJson('https://localhost/api/acceso')->assertOk()->assertJsonPath('equipoLocal', false);
        $this->postJson('https://localhost/api/configurar', [])->assertForbidden();
        $this->artisan('canchas:demostracion')->assertFailed();
    }

    public function test_limite_de_intentos_y_cors_restringido(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/ingresar', [])->assertStatus(422);
        }
        $this->postJson('/api/ingresar', [])->assertStatus(429);
        $r = $this->withHeader('Origin', 'https://atacante.example')->getJson('/api/acceso');
        $this->assertNotSame('https://atacante.example', $r->headers->get('Access-Control-Allow-Origin'));
    }
}

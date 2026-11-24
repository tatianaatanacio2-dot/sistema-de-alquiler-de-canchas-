<?php

// Ejecutar con PHP CLI. Crea una base canchas_qa_* que se conserva para inspección.
// Nunca cambia ni elimina canchas_alquiler. Requiere permiso CREATE DATABASE local.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Http\Controllers\AlquilerController;
use App\Http\Controllers\FinanzasController;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$worker = ($argv[1] ?? '') === '--worker';
$database = $worker ? ($argv[2] ?? '') : 'canchas_qa_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
if (! preg_match('/^canchas_qa_[a-zA-Z0-9_]+$/D', $database)) {
    throw new RuntimeException('Base de prueba inválida.');
}
$connection = config('database.connections.'.config('database.default'));
if (! in_array($connection['driver'], ['mysql', 'mariadb'], true)) {
    throw new RuntimeException('Esta prueba requiere MariaDB/MySQL.');
}
if (! $worker) {
    DB::statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}
$connection['database'] = $database;
$connection['url'] = null;
config(['database.connections.qa' => $connection, 'database.default' => 'qa']);
$app->detectEnvironment(fn () => 'testing');

if ($worker) {
    echo "READY\n";
    flush();
    try {
        $tipo = $argv[3];
        if ($tipo === 'reserva') {
            app(AlquilerController::class)->guardarReserva(Request::create('/api/reservas', 'POST', ['inicio' => '2030-12-01T18:00', 'fin' => '2030-12-01T19:00']));
        } else {
            app(FinanzasController::class)->guardarPago(Request::create('/api/pagos', 'POST', [
                'operacion' => (string) Str::uuid(), 'reserva_id' => (int) $argv[4],
                'monto' => '20.00', 'metodo' => 'efectivo', 'tipo' => $tipo,
            ]));
        }
        echo "200\n";
    } catch (HttpExceptionInterface $e) {
        echo $e->getStatusCode()."\n";
    }
    exit;
}

foreach (glob(__DIR__.'/../database/migrations/*.php') as $migration) {
    (require $migration)->up();
}
foreach (DB::select('SHOW TABLE STATUS') as $table) {
    if ($table->Engine !== 'InnoDB') {
        throw new RuntimeException('Se requiere InnoDB.');
    }
}

function competir(string $database, string $tipo, int $reserva = 0): void
{
    DB::beginTransaction();
    DB::table('canchas')->where('id', 1)->lockForUpdate()->first();
    $children = [];
    try {
        for ($i = 0; $i < 2; $i++) {
            $proc = proc_open([PHP_BINARY, __FILE__, '--worker', $database, $tipo, (string) $reserva], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (! is_resource($proc)) {
                throw new RuntimeException('No se pudo iniciar el proceso.');
            }
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 20);
            $children[] = [$proc, $pipes];
        }
        foreach ($children as [$proc, $pipes]) {
            if (trim(fgets($pipes[1]) ?: '') !== 'READY') {
                throw new RuntimeException('El proceso no alcanzó la barrera.');
            }
        }
        usleep(200000);
        DB::commit();
        $codes = [];
        foreach ($children as [$proc, $pipes]) {
            $codes[] = (int) trim(stream_get_contents($pipes[1]));
            $error = stream_get_contents($pipes[2]);
            if (proc_close($proc) !== 0) {
                throw new RuntimeException($error);
            }
        }
        sort($codes);
        if ($codes !== [200, 409]) {
            throw new RuntimeException('Resultado inesperado: '.json_encode($codes));
        }
        echo "$tipo simultáneo: 200 + 409\n";
    } finally {
        if (DB::transactionLevel()) {
            DB::rollBack();
        }
    }
}

competir($database, 'reserva');
if (DB::table('reservas')->count() !== 1) {
    throw new RuntimeException('Reserva duplicada.');
}
$id = DB::table('reservas')->value('id');
competir($database, 'cobro', $id);
if (FinanzasController::abonado($id) !== 2000) {
    throw new RuntimeException('Saldo de cobro incorrecto.');
}
competir($database, 'devolucion', $id);
if (FinanzasController::abonado($id) !== 0) {
    throw new RuntimeException('Saldo de devolución incorrecto.');
}
echo "OK: tres carreras, sin cruces ni sobrecobros. Base conservada: $database\n";

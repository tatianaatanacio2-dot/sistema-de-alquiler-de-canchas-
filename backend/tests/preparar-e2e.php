<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = 'canchas_qa_e2e_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
$c = config('database.connections.'.config('database.default'));
if (! in_array($c['driver'], ['mysql', 'mariadb'], true)) {
    throw new RuntimeException('Se requiere MySQL/MariaDB local.');
}
DB::statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$c['database'] = $database;
$c['url'] = null;
config(['database.connections.qa' => $c, 'database.default' => 'qa']);
foreach (glob(__DIR__.'/../database/migrations/*.php') as $file) {
    (require $file)->up();
}
DB::table('administrador')->insert(['id' => 1, 'nombre' => 'Admin de pruebas', 'correo' => 'admin@example.test', 'clave' => Hash::make('Clave-e2e-segura-2026')]);
echo $database;

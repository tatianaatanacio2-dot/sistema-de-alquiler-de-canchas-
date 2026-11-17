<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\AlquilerController;
use App\Http\Controllers\FinanzasController;
use App\Http\Middleware\SesionAdministrador;
use Illuminate\Support\Facades\Route;

Route::get('acceso', [AccesoController::class, 'estado']);
Route::middleware('throttle:10,1')->group(function () {
    Route::post('configurar', [AccesoController::class, 'configurar']);
    Route::post('ingresar', [AccesoController::class, 'ingresar']);
});

Route::middleware(SesionAdministrador::class)->group(function () {
    Route::get('sesion', [AccesoController::class, 'sesion']);
    Route::post('salir', [AccesoController::class, 'salir']);
    Route::get('resumen', [AlquilerController::class, 'resumen']);
    Route::get('cancha', [AlquilerController::class, 'cancha']);
    Route::put('cancha', [AlquilerController::class, 'guardarCancha']);
    Route::get('clientes', [AlquilerController::class, 'clientes']);
    Route::post('clientes', [AlquilerController::class, 'guardarCliente']);
    Route::put('clientes/{id}', [AlquilerController::class, 'guardarCliente'])->whereNumber('id');
    Route::get('reservas', [AlquilerController::class, 'reservas']);
    Route::post('reservas', [AlquilerController::class, 'guardarReserva']);
    Route::put('reservas/{id}', [AlquilerController::class, 'guardarReserva'])->whereNumber('id');
    Route::patch('reservas/{id}/estado', [AlquilerController::class, 'cerrarReserva'])->whereNumber('id');
    Route::get('pagos', [FinanzasController::class, 'pagos']);
    Route::post('pagos', [FinanzasController::class, 'guardarPago']);
    Route::get('caja', [FinanzasController::class, 'caja']);
    Route::post('gastos', [FinanzasController::class, 'guardarGasto']);
    Route::patch('gastos/{id}/anular', [FinanzasController::class, 'anularGasto'])->whereNumber('id');
    Route::get('historial', [FinanzasController::class, 'historial']);
});

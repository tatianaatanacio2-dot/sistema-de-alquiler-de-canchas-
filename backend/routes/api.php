<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\AlquilerController;
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
    Route::get('clientes/{id}/historial', [AlquilerController::class, 'historialCliente'])->whereNumber('id');
    Route::get('lista-espera', [AlquilerController::class, 'listaEspera']);
    Route::post('lista-espera', [AlquilerController::class, 'guardarEnEspera']);
    Route::patch('lista-espera/{id}/estado', [AlquilerController::class, 'cambiarEstadoEspera'])->whereNumber('id');
    Route::get('reservas', [AlquilerController::class, 'reservas']);
    Route::post('reservas', [AlquilerController::class, 'guardarReserva']);
    Route::post('reservas/repetir', [AlquilerController::class, 'guardarReservasRepetidas']);
    Route::put('reservas/{id}', [AlquilerController::class, 'guardarReserva'])->whereNumber('id');
    Route::get('reservas/{id}/pagos', [AlquilerController::class, 'movimientosPago'])->whereNumber('id');
    Route::post('reservas/{id}/pagos', [AlquilerController::class, 'registrarPago'])->whereNumber('id');
    Route::patch('reservas/{id}/estado', [AlquilerController::class, 'cerrarReserva'])->whereNumber('id');
});

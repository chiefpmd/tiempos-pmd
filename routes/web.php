<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\MuebleController;
use App\Http\Controllers\TiempoController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\DiaFestivoController;
use App\Http\Controllers\ProyectoMaterialController;
use App\Http\Controllers\NominaController;
use App\Http\Controllers\CategoriaNominaController;
use App\Http\Controllers\EquipoDiarioController;

Route::get('/', fn() => redirect('/login'));

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [TiempoController::class, 'dashboard'])->name('dashboard');
    Route::get('/general', [TiempoController::class, 'vistaGeneral'])->name('general');
    Route::get('/proyecto/{proyecto}/captura', [TiempoController::class, 'captura'])->name('captura');

    Route::get('/proyectos', [ProyectoController::class, 'index'])->name('proyectos.index');
    Route::get('/personal', [PersonalController::class, 'index'])->name('personal.index');

    Route::get('/export/proyecto/{proyecto}', [ExportController::class, 'exportarProyecto'])->name('export.proyecto');
    Route::get('/export/general', [ExportController::class, 'exportarGeneral'])->name('export.general');

    // Admin-only routes
    Route::middleware('admin')->group(function () {
        Route::post('/tiempos/guardar', [TiempoController::class, 'guardar'])->name('tiempos.guardar');
        Route::post('/tiempos/reasignar-equipo', [TiempoController::class, 'reasignarEquipo'])->name('tiempos.reasignarEquipo');
        Route::post('/tiempos/guardar-rango', [TiempoController::class, 'guardarRango'])->name('tiempos.guardarRango');
        Route::post('/tiempos/borrar-rango', [TiempoController::class, 'borrarRango'])->name('tiempos.borrarRango');
        Route::post('/tiempos/recorrer/{proyecto}', [TiempoController::class, 'recorrerFechas'])->name('tiempos.recorrer');
        Route::post('/tiempos/revertir/{shift}', [TiempoController::class, 'revertirRecorrido'])->name('tiempos.revertir');

        Route::get('/proyectos/crear', [ProyectoController::class, 'create'])->name('proyectos.create');
        Route::post('/proyectos', [ProyectoController::class, 'store'])->name('proyectos.store');
        Route::get('/proyectos/{proyecto}/editar', [ProyectoController::class, 'edit'])->name('proyectos.edit');
        Route::put('/proyectos/{proyecto}', [ProyectoController::class, 'update'])->name('proyectos.update');
        Route::delete('/proyectos/{proyecto}', [ProyectoController::class, 'destroy'])->name('proyectos.destroy');

        Route::get('/personal/crear', [PersonalController::class, 'create'])->name('personal.create');
        Route::post('/personal', [PersonalController::class, 'store'])->name('personal.store');
        Route::get('/personal/{personal}/editar', [PersonalController::class, 'edit'])->name('personal.edit');
        Route::put('/personal/{personal}', [PersonalController::class, 'update'])->name('personal.update');
        Route::delete('/personal/{personal}', [PersonalController::class, 'destroy'])->name('personal.destroy');

        Route::post('/proyecto/{proyecto}/muebles', [MuebleController::class, 'store'])->name('muebles.store');
        Route::delete('/muebles/{mueble}', [MuebleController::class, 'destroy'])->name('muebles.destroy');

        Route::post('/proyecto/{proyecto}/materiales', [ProyectoMaterialController::class, 'guardar'])->name('materiales.guardar');

        Route::get('/festivos', [DiaFestivoController::class, 'index'])->name('festivos.index');
        Route::post('/festivos', [DiaFestivoController::class, 'store'])->name('festivos.store');
        Route::delete('/festivos/{festivo}', [DiaFestivoController::class, 'destroy'])->name('festivos.destroy');

        // Nómina - guardar y prellenar (admin only)
        Route::post('/nomina/guardar', [NominaController::class, 'guardar'])->name('nomina.guardar');
        Route::post('/nomina/prellenar', [NominaController::class, 'prellenar'])->name('nomina.prellenar');

        // Equipos del día (disabled - using lider_id from personal instead)
        // Route::get('/nomina/equipos', [EquipoDiarioController::class, 'index'])->name('nomina.equipos');
        // Route::post('/nomina/equipos/guardar', [EquipoDiarioController::class, 'guardar'])->name('nomina.equipos.guardar');
        // Route::post('/nomina/equipos/copiar', [EquipoDiarioController::class, 'copiarDia'])->name('nomina.equipos.copiar');

        // Categorías nómina (admin only)
        Route::get('/nomina/categorias', [CategoriaNominaController::class, 'index'])->name('nomina.categorias');
        Route::post('/nomina/categorias', [CategoriaNominaController::class, 'store'])->name('nomina.categorias.store');
        Route::put('/nomina/categorias/{categoria}', [CategoriaNominaController::class, 'update'])->name('nomina.categorias.update');
        Route::delete('/nomina/categorias/{categoria}', [CategoriaNominaController::class, 'destroy'])->name('nomina.categorias.destroy');
    });

    // Nómina - vistas (all authenticated users)
    Route::get('/nomina', [NominaController::class, 'semanal'])->name('nomina.semanal');
    Route::get('/nomina/reporte', [NominaController::class, 'reporte'])->name('nomina.reporte');
    Route::get('/nomina/reporte/exportar', [NominaController::class, 'exportarReporte'])->name('nomina.exportar');
});

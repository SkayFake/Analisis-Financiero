<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CreditoController;
use App\Http\Controllers\CobroController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Clientes
    Route::resource('clientes', \App\Http\Controllers\ClienteController::class);

    // Activo Fijo
    Route::get('/activos-fijos', \App\Livewire\ActivoFijoDashboard::class)->name('activos-fijos.dashboard');

    // Créditos Comerciales
    Route::get('/creditos', [\App\Http\Controllers\CreditoController::class, 'index'])->name('creditos.index');
    Route::get('/creditos/create', [\App\Http\Controllers\CreditoController::class, 'create'])->name('creditos.create');
    Route::post('/creditos', [\App\Http\Controllers\CreditoController::class, 'store'])->name('creditos.store');
    Route::get('/creditos/{credito}', [\App\Http\Controllers\CreditoController::class, 'show'])->name('creditos.show');
    Route::post('/creditos/{credito}/pagos', [\App\Http\Controllers\CreditoController::class, 'storePago'])->name('creditos.pagos.store');
    Route::post('/creditos/{credito}/aprobar', [\App\Http\Controllers\CreditoController::class, 'aprobar'])->name('creditos.aprobar');
    Route::post('/creditos/{credito}/rechazar', [\App\Http\Controllers\CreditoController::class, 'rechazar'])->name('creditos.rechazar');

    // Inventario
    Route::get('/inventario', \App\Livewire\InventarioDashboard::class)->name('inventario.dashboard');

    // Facturación DTE
    Route::get('/facturacion/pos', \App\Livewire\PuntoVenta::class)->name('facturacion.pos');
    Route::get('/facturacion/{venta}/pdf', function (\App\Models\Venta $venta) {
        $venta->load(['cliente', 'detalles.producto']);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('facturacion.pdf', compact('venta'));
        // Si es ticket, papel continuo de 80mm
        if ($venta->tipo_documento == '11') {
            $pdf->setPaper([0, 0, 226.77, 800], 'portrait');
        }
        return $pdf->stream('factura_' . $venta->numero_control . '.pdf');
    })->name('facturacion.pdf');

    // Cobros
    Route::get('/cobros', [CobroController::class, 'dashboard'])->name('cobros.dashboard');
    Route::post('/cobros/reclasificacion-masiva', [CobroController::class, 'reclasificacionMasiva'])->name('cobros.reclasificacion');

    // Configuraciones Generales
    Route::get('/configuracion/catalogos', \App\Livewire\Configuracion\CatalogosDashboard::class)->name('configuracion.catalogos');
    Route::get('/vendedores', \App\Livewire\Vendedores\VendedoresDashboard::class)->name('vendedores.index');
});

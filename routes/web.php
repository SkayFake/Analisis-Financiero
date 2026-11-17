<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CobroController;
use App\Http\Controllers\CreditoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\IncobrableController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\VendedorController;
use App\Livewire\ActivoFijoDashboard;
use App\Livewire\Configuracion\CatalogosDashboard;
use App\Livewire\InventarioDashboard;
use App\Livewire\PuntoVenta;
use App\Livewire\Vendedores\VendedoresDashboard;
use App\Models\Venta;
use App\Services\SimulacionDocumentoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

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

    // Expedientes privados: ninguna ruta de archivos se publica en /storage.
    Route::get('/clientes/{cliente}/expediente', [ExpedienteController::class, 'cliente'])->name('expedientes.cliente');
    Route::get('/creditos/{credito}/expediente', [ExpedienteController::class, 'credito'])->name('expedientes.credito');
    Route::post('/clientes/{cliente}/documentos', [ExpedienteController::class, 'subirCliente'])->name('expedientes.cliente.subir');
    Route::post('/creditos/{credito}/documentos', [ExpedienteController::class, 'subirCredito'])->name('expedientes.credito.subir');
    Route::post('/creditos/{credito}/fiadores/{fiador}/documentos', [ExpedienteController::class, 'subirFiador'])->name('expedientes.fiador.subir');
    Route::get('/clientes/{cliente}/documentos/{documento}/descargar', [ExpedienteController::class, 'descargar'])->name('expedientes.descargar');
    Route::post('/clientes/{cliente}/documentos/{documento}/revision', [ExpedienteController::class, 'revisar'])->name('expedientes.revisar');
    Route::post('/creditos/{credito}/fiadores', [ExpedienteController::class, 'fiador'])->name('expedientes.fiador.crear');
    Route::put('/creditos/{credito}/fiadores/{fiador}', [ExpedienteController::class, 'fiador'])->name('expedientes.fiador.actualizar');

    // Clientes
    Route::resource('clientes', ClienteController::class);

    // Activo Fijo
    Route::get('/activos-fijos', ActivoFijoDashboard::class)->name('activos-fijos.dashboard');

    // Créditos Comerciales
    Route::get('/creditos', [CreditoController::class, 'index'])->name('creditos.index');
    Route::get('/creditos/create', [CreditoController::class, 'create'])->name('creditos.create');
    Route::post('/creditos', [CreditoController::class, 'store'])->name('creditos.store');
    Route::get('/creditos/{credito}', [CreditoController::class, 'show'])->name('creditos.show');
    Route::post('/creditos/{credito}/pagos', [CreditoController::class, 'storePago'])->name('creditos.pagos.store');
    Route::get('/creditos/{credito}/pagos/{pago}/recibo', [PagoController::class, 'recibo'])->name('creditos.pagos.recibo');
    Route::post('/creditos/{credito}/aprobar', [CreditoController::class, 'aprobar'])->name('creditos.aprobar');
    Route::post('/creditos/{credito}/rechazar', [CreditoController::class, 'rechazar'])->name('creditos.rechazar');

    Route::get('/incobrables', [IncobrableController::class, 'index'])->name('incobrables.index');
    Route::post('/creditos/{credito}/incobrable', [IncobrableController::class, 'store'])->name('creditos.incobrable');
    Route::post('/creditos/{credito}/reactivar', [IncobrableController::class, 'reactivar'])->name('creditos.reactivar');

    // Inventario
    Route::get('/inventario', InventarioDashboard::class)->name('inventario.dashboard');

    // Facturación DTE
    Route::get('/facturacion/pos', PuntoVenta::class)->middleware('can:viewAny,App\Models\Venta')->name('facturacion.pos');
    Route::get('/facturacion/{venta}/pdf', function (Venta $venta) {
        Gate::authorize('view', $venta);
        $venta->load(['cliente', 'detalles.producto']);
        $pdf = Pdf::loadView('facturacion.pdf', compact('venta'));
        // Si es ticket, papel continuo de 80mm
        if ($venta->tipo_documento == '11') {
            $pdf->setPaper([0, 0, 226.77, 800], 'portrait');
        }

        return $pdf->stream('factura_'.$venta->numero_control.'.pdf');
    })->name('facturacion.pdf');

    Route::get('/facturacion/{venta}/json-academico', function (Venta $venta, SimulacionDocumentoService $simulador) {
        Gate::authorize('view', $venta);

        return response()->json($simulador->generar($venta))->header('Cache-Control', 'private, no-store');
    })->name('facturacion.json-academico');

    // Cobros
    Route::get('/cobros', [CobroController::class, 'dashboard'])->name('cobros.dashboard');
    Route::post('/cobros/reclasificacion-masiva', [CobroController::class, 'reclasificacionMasiva'])->name('cobros.reclasificacion');

    // Configuraciones Generales
    Route::get('/configuracion/catalogos', CatalogosDashboard::class)->name('configuracion.catalogos');
    Route::get('/vendedores', VendedoresDashboard::class)->middleware('can:viewAny,App\Models\Vendedor')->name('vendedores.index');
    Route::get('/vendedores/exportar', [VendedorController::class, 'exportar'])->name('vendedores.exportar');
    Route::get('/vendedores/pendientes', [VendedorController::class, 'pendientes'])->name('vendedores.pendientes');
    Route::get('/vendedores/{vendedor}', [VendedorController::class, 'show'])->name('vendedores.show');
    Route::get('/vendedores/{vendedor}/exportar', [VendedorController::class, 'exportar'])->name('vendedores.reporte');
    Route::post('/vendedores/{vendedor}/metas', [VendedorController::class, 'meta'])->name('vendedores.metas');
    Route::post('/creditos/{credito}/responsable', [VendedorController::class, 'reasignar'])->name('creditos.responsable');
    Route::post('/creditos/{credito}/gestiones', [VendedorController::class, 'gestion'])->name('creditos.gestiones');
    Route::post('/ventas/{venta}/vendedor', [VendedorController::class, 'regularizarVenta'])->name('ventas.vendedor');
    Route::post('/pagos/{pago}/vendedor', [VendedorController::class, 'regularizarPago'])->name('pagos.vendedor');
});

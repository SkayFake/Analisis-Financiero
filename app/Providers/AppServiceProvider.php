<?php

namespace App\Providers;

use App\Livewire\ActivoFijoDashboard;
use App\Livewire\Configuracion\CatalogosDashboard;
use App\Livewire\InventarioDashboard;
use App\Livewire\MovimientoInventarioModal;
use App\Livewire\PuntoVenta;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::component('configuracion.catalogos-dashboard', CatalogosDashboard::class);
        Livewire::component('activo-fijo-dashboard', ActivoFijoDashboard::class);
        Livewire::component('inventario-dashboard', InventarioDashboard::class);
        Livewire::component('movimiento-inventario-modal', MovimientoInventarioModal::class);
        Livewire::component('punto-venta', PuntoVenta::class);
        Paginator::defaultView('vendor.pagination.default');
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Proveedor;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\UnidadMedida;
use App\Models\Bodega;
use App\Models\Producto;

class InventarioSeeder extends Seeder
{
    public function run()
    {
        // 1. Proveedores
        $prov1 = Proveedor::create([
            'nombre' => 'Distribuidora Central S.A.',
            'nit' => '0614-123456-101-1',
            'dias_credito' => 30,
        ]);
        $prov2 = Proveedor::create([
            'nombre' => 'Importaciones Globales S.A.',
            'nit' => '0614-654321-102-2',
            'dias_credito' => 60,
        ]);

        // 2. Bodegas
        $bodegaPrincipal = Bodega::create([
            'nombre' => 'Bodega Central',
            'direccion' => 'San Salvador, Centro',
        ]);
        $bodegaSecundaria = Bodega::create([
            'nombre' => 'Bodega Sucursal Norte',
            'direccion' => 'Apopa, Zona Industrial',
        ]);

        // 3. Catálogos
        $catElectro = Categoria::create(['nombre' => 'Electrodomésticos']);
        $catMuebles = Categoria::create(['nombre' => 'Muebles']);
        
        $marcaLG = Marca::create(['nombre' => 'LG']);
        $marcaSamsung = Marca::create(['nombre' => 'Samsung']);
        $marcaMuebles = Marca::create(['nombre' => 'MueblesDurex']);

        $umUnidad = UnidadMedida::create(['nombre' => 'Unidad', 'abreviatura' => 'u']);

        // 4. Productos
        $p1 = Producto::create([
            'codigo' => 'ELEC-001',
            'nombre' => 'Smart TV 55" 4K',
            'categoria_id' => $catElectro->id,
            'marca_id' => $marcaLG->id,
            'unidad_medida_id' => $umUnidad->id,
            'proveedor_id' => $prov1->id,
            'metodo_costeo' => 'PROMEDIO',
            'costo_unitario' => 350.00, // Costo base (se recalcula con movimientos)
            'precio_venta' => 499.99,
            'stock_minimo' => 5,
            'stock_maximo' => 50,
            'tiempo_espera_dias' => 15,
            'perecedero' => false,
        ]);

        $p2 = Producto::create([
            'codigo' => 'MUE-001',
            'nombre' => 'Juego de Sala 3 Piezas',
            'categoria_id' => $catMuebles->id,
            'marca_id' => $marcaMuebles->id,
            'unidad_medida_id' => $umUnidad->id,
            'proveedor_id' => $prov2->id,
            'metodo_costeo' => 'ULTIMA_COMPRA',
            'costo_unitario' => 280.00,
            'precio_venta' => 450.00,
            'stock_minimo' => 2,
            'tiempo_espera_dias' => 30,
            'perecedero' => false,
        ]);

        // Simularemos algunos ingresos iniciales usando el Servicio
        $service = app(\App\Services\InventarioService::class);
        
        // Ingreso inicial
        $service->registrarEntrada([
            'bodega_id' => $bodegaPrincipal->id,
            'proveedor_id' => $prov1->id,
            'fecha' => now()->subDays(10)->toDateString(),
            'referencia' => 'Apertura de Inventario',
            'detalles' => [
                [
                    'producto_id' => $p1->id,
                    'cantidad' => 10,
                    'costo_unitario' => 340.00,
                ],
                [
                    'producto_id' => $p2->id,
                    'cantidad' => 5,
                    'costo_unitario' => 280.00,
                ]
            ]
        ], 1); // 1 = user_id Admin

        // Segunda compra de TV (Costo subió)
        $service->registrarEntrada([
            'bodega_id' => $bodegaPrincipal->id,
            'proveedor_id' => $prov1->id,
            'fecha' => now()->subDays(2)->toDateString(),
            'referencia' => 'Reabastecimiento',
            'detalles' => [
                [
                    'producto_id' => $p1->id,
                    'cantidad' => 5,
                    'costo_unitario' => 360.00, // Ahora el promedio debería ser (3400 + 1800) / 15 = 346.66
                ]
            ]
        ], 1);
        
        // Transferencia
        $service->transferirBodega([
            'bodega_origen_id' => $bodegaPrincipal->id,
            'bodega_destino_id' => $bodegaSecundaria->id,
            'fecha' => now()->toDateString(),
            'referencia' => 'Traslado a sucursal',
            'detalles' => [
                [
                    'producto_id' => $p1->id,
                    'cantidad' => 2,
                ]
            ]
        ], 1);
    }
}

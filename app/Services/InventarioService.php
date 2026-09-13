<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Existencia;
use App\Models\MovimientoInventario;
use App\Models\DetalleMovimiento;
use Illuminate\Support\Facades\DB;
use Exception;

class InventarioService
{
    /**
     * Registra un movimiento de entrada (ej. Compras)
     */
    public function registrarEntrada(array $datos, $usuario_id)
    {
        return DB::transaction(function () use ($datos, $usuario_id) {
            $movimiento = MovimientoInventario::create([
                'tipo' => 'entrada',
                'numero_documento' => MovimientoInventario::generarNumeroDocumento('entrada'),
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'bodega_destino_id' => $datos['bodega_id'],
                'proveedor_id' => $datos['proveedor_id'] ?? null,
                'usuario_id' => $usuario_id,
                'referencia' => $datos['referencia'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'costo_total' => 0, // Se calcula después
            ]);

            $costoTotalMovimiento = 0;

            foreach ($datos['detalles'] as $detalle) {
                $producto = Producto::findOrFail($detalle['producto_id']);
                $cantidad = floatval($detalle['cantidad']);
                $costoUnitario = floatval($detalle['costo_unitario']);
                $costoTotalLinea = $cantidad * $costoUnitario;

                // 1. Guardar detalle del movimiento
                DetalleMovimiento::create([
                    'movimiento_id' => $movimiento->id,
                    'producto_id' => $producto->id,
                    'lote' => $detalle['lote'] ?? null,
                    'fecha_vencimiento' => $detalle['fecha_vencimiento'] ?? null,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costoUnitario,
                    'costo_total' => $costoTotalLinea,
                ]);

                // 2. Actualizar o crear existencia en bodega
                $existencia = Existencia::firstOrNew([
                    'producto_id' => $producto->id,
                    'bodega_id' => $datos['bodega_id'],
                    'lote' => $detalle['lote'] ?? null,
                ]);

                $existencia->cantidad += $cantidad;
                if (isset($detalle['fecha_vencimiento'])) {
                    $existencia->fecha_vencimiento = $detalle['fecha_vencimiento'];
                }
                $existencia->save();

                // 3. Recalcular costo del producto según método
                $this->aplicarMetodoCosteoEntrada($producto, $cantidad, $costoUnitario, $costoTotalLinea);

                $costoTotalMovimiento += $costoTotalLinea;
            }

            $movimiento->update(['costo_total' => $costoTotalMovimiento]);

            return $movimiento;
        });
    }

    /**
     * Aplica la política de costeo legal (Art. 143) al recibir una entrada.
     */
    private function aplicarMetodoCosteoEntrada(Producto $producto, $cantidadEntrada, $costoUnitarioEntrada, $costoTotalEntrada)
    {
        $stockActual = $producto->stock_total - $cantidadEntrada; // Stock que había antes de esta entrada
        $costoUnitarioActual = $producto->costo_unitario;

        switch ($producto->metodo_costeo) {
            case 'ULTIMA_COMPRA':
                $producto->costo_unitario = $costoUnitarioEntrada;
                break;

            case 'PROMEDIO': // Costo Promedio Ponderado
                if ($stockActual + $cantidadEntrada > 0) {
                    $valorInventarioPrevio = $stockActual * $costoUnitarioActual;
                    $nuevoValorInventario = $valorInventarioPrevio + $costoTotalEntrada;
                    $producto->costo_unitario = $nuevoValorInventario / ($stockActual + $cantidadEntrada);
                } else {
                    $producto->costo_unitario = $costoUnitarioEntrada;
                }
                break;

            case 'PROMEDIO_ALIGACION':
                // Promedio simple (aligación simple) entre el costo anterior y el nuevo
                if ($costoUnitarioActual > 0) {
                    $producto->costo_unitario = ($costoUnitarioActual + $costoUnitarioEntrada) / 2;
                } else {
                    $producto->costo_unitario = $costoUnitarioEntrada;
                }
                break;

            case 'PEPS':
                // Para PEPS (Primeras Entradas Primeras Salidas), el costo unitario general
                // suele reflejar el lote más antiguo o simplemente mantenemos el historial en DetalleMovimiento.
                // Como indicador general mantenemos el costo de la última compra o no lo tocamos.
                // Aquí solo actualizamos el costo unitario como referencia de última entrada.
                $producto->costo_unitario = $costoUnitarioEntrada;
                break;
        }

        $producto->save();
    }

    /**
     * Registra un movimiento de salida (ej. Ventas, Mermas)
     */
    public function registrarSalida(array $datos, $usuario_id)
    {
        return DB::transaction(function () use ($datos, $usuario_id) {
            $movimiento = MovimientoInventario::create([
                'tipo' => 'salida',
                'numero_documento' => MovimientoInventario::generarNumeroDocumento('salida'),
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'bodega_origen_id' => $datos['bodega_id'],
                'usuario_id' => $usuario_id,
                'referencia' => $datos['referencia'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'costo_total' => 0,
            ]);

            $costoTotalMovimiento = 0;

            foreach ($datos['detalles'] as $detalle) {
                $producto = Producto::findOrFail($detalle['producto_id']);
                $cantidadSolicitada = floatval($detalle['cantidad']);

                // Costeo de Salida: El costo se define por el método de costeo o por el lote específico si es PEPS/Perecedero
                // Para simplificar, descargamos del lote específico solicitado si viene, o del más antiguo (PEPS lógico)
                
                $queryExistencias = Existencia::where('producto_id', $producto->id)
                                              ->where('bodega_id', $datos['bodega_id'])
                                              ->where('cantidad', '>', 0);
                
                if (!empty($detalle['lote'])) {
                    $queryExistencias->where('lote', $detalle['lote']);
                } else {
                    // Si no especifica lote, usamos ordenamiento para PEPS (por fecha de vencimiento o id de creación)
                    $queryExistencias->orderBy('fecha_vencimiento', 'asc')->orderBy('id', 'asc');
                }

                $existenciasDisponibles = $queryExistencias->get();

                $cantidadPorDescargar = $cantidadSolicitada;
                $costoTotalLinea = 0;

                foreach ($existenciasDisponibles as $existencia) {
                    if ($cantidadPorDescargar <= 0) break;

                    $descarga = min($existencia->cantidad, $cantidadPorDescargar);
                    $existencia->cantidad -= $descarga;
                    $existencia->save();

                    $costoUnitarioAplicar = $producto->costo_unitario; // Por defecto PROMEDIO / ULTIMA COMPRA
                    
                    // Si es PEPS estricto por lote, se podría usar el costo de cuando entró ese lote.
                    // Para este sistema usamos el costo unitario global calculado por el método del producto.

                    DetalleMovimiento::create([
                        'movimiento_id' => $movimiento->id,
                        'producto_id' => $producto->id,
                        'lote' => $existencia->lote,
                        'fecha_vencimiento' => $existencia->fecha_vencimiento,
                        'cantidad' => $descarga,
                        'costo_unitario' => $costoUnitarioAplicar,
                        'costo_total' => $descarga * $costoUnitarioAplicar,
                    ]);

                    $costoTotalLinea += ($descarga * $costoUnitarioAplicar);
                    $cantidadPorDescargar -= $descarga;
                }

                if ($cantidadPorDescargar > 0) {
                    throw new Exception("Stock insuficiente para el producto {$producto->nombre} en la bodega seleccionada.");
                }

                $costoTotalMovimiento += $costoTotalLinea;
            }

            $movimiento->update(['costo_total' => $costoTotalMovimiento]);

            return $movimiento;
        });
    }

    /**
     * Transferencia entre bodegas
     */
    public function transferirBodega(array $datos, $usuario_id)
    {
        return DB::transaction(function () use ($datos, $usuario_id) {
            $movimiento = MovimientoInventario::create([
                'tipo' => 'transferencia',
                'numero_documento' => MovimientoInventario::generarNumeroDocumento('transferencia'),
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'bodega_origen_id' => $datos['bodega_origen_id'],
                'bodega_destino_id' => $datos['bodega_destino_id'],
                'usuario_id' => $usuario_id,
                'referencia' => $datos['referencia'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'costo_total' => 0,
            ]);

            $costoTotalMovimiento = 0;

            foreach ($datos['detalles'] as $detalle) {
                $producto = Producto::findOrFail($detalle['producto_id']);
                $cantidad = floatval($detalle['cantidad']);

                // 1. Descargar de Origen
                $existenciaOrigen = Existencia::where('producto_id', $producto->id)
                    ->where('bodega_id', $datos['bodega_origen_id'])
                    ->where('lote', $detalle['lote'] ?? null)
                    ->first();

                if (!$existenciaOrigen || $existenciaOrigen->cantidad < $cantidad) {
                    throw new Exception("Stock insuficiente en bodega origen para producto {$producto->nombre}");
                }

                $existenciaOrigen->cantidad -= $cantidad;
                $existenciaOrigen->save();

                // 2. Cargar en Destino
                $existenciaDestino = Existencia::firstOrNew([
                    'producto_id' => $producto->id,
                    'bodega_id' => $datos['bodega_destino_id'],
                    'lote' => $detalle['lote'] ?? null,
                ]);

                $existenciaDestino->cantidad += $cantidad;
                $existenciaDestino->fecha_vencimiento = $existenciaOrigen->fecha_vencimiento;
                $existenciaDestino->save();

                // 3. Registrar Detalle
                $costoUnitario = $producto->costo_unitario;
                $costoTotalLinea = $cantidad * $costoUnitario;

                DetalleMovimiento::create([
                    'movimiento_id' => $movimiento->id,
                    'producto_id' => $producto->id,
                    'lote' => $detalle['lote'] ?? null,
                    'fecha_vencimiento' => $existenciaOrigen->fecha_vencimiento,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costoUnitario,
                    'costo_total' => $costoTotalLinea,
                ]);

                $costoTotalMovimiento += $costoTotalLinea;
            }

            $movimiento->update(['costo_total' => $costoTotalMovimiento]);

            return $movimiento;
        });
    }
}

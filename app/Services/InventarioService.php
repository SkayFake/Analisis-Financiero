<?php

namespace App\Services;

use App\Models\DetalleMovimiento;
use App\Models\Existencia;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventarioService
{
    private const COSTO_TOTAL_MAXIMO = 9999999999.9999;

    private function validar(array $datos, string $tipo): void
    {
        $reglas = [
            'fecha' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'detalles' => 'required|array|min:1|max:100',
            'detalles.*.producto_id' => 'required|integer|exists:productos,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.0001|max:999999.9999|decimal:0,4',
            'detalles.*.lote' => 'nullable|string|max:50',
            'detalles.*.fecha_vencimiento' => 'nullable|date_format:Y-m-d',
        ];
        foreach ($tipo === 'transferencia' ? ['bodega_origen_id', 'bodega_destino_id'] : ['bodega_id'] as $campo) {
            $reglas[$campo] = ['required', 'integer', Rule::exists('bodegas', 'id')->where('activa', true)];
        }
        if ($tipo === 'transferencia') {
            $reglas['bodega_destino_id'][] = 'different:bodega_origen_id';
        }
        if ($tipo === 'entrada') {
            $reglas['detalles.*.costo_unitario'] = 'required|numeric|min:0|max:99999999.9999|decimal:0,4';
        }
        Validator::make($datos, $reglas)->validate();
    }

    private function bloquear(array $datos)
    {
        // El producto es el bloqueo común incluso si la existencia todavía no existe.
        $ids = array_unique(array_column($datos['detalles'], 'producto_id'));
        $productos = Producto::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($ids as $id) {
            $producto = $productos->get($id);
            if (! $producto?->activo) {
                throw ValidationException::withMessages(['producto_id' => 'Seleccione un producto activo.']);
            }

            if ($producto->metodo_costeo === 'PEPS' && abs((float) $producto->stock_total - (float) DB::table('capas_inventario')->where('producto_id', $producto->id)->sum('cantidad')) > 0.00001) {
                throw ValidationException::withMessages(['costeo' => 'Concilie las capas PEPS históricas antes de registrar entradas, salidas o transferencias.']);
            }
        }

        return $productos;
    }

    public function registrarEntrada(array $datos, $usuario_id)
    {
        $this->validar($datos, 'entrada');

        return DB::transaction(function () use ($datos, $usuario_id) {
            $productos = $this->bloquear($datos);
            $mov = $this->movimiento('entrada', $datos, $usuario_id);
            foreach ($datos['detalles'] as $det) {
                $p = $productos[$det['producto_id']];
                $cantidad = (float) $det['cantidad'];
                $costo = (float) $det['costo_unitario'];
                $stockAntes = (float) $p->stock_total;
                $this->detalle($mov, $p, $det, $cantidad, $costo);
                $e = $this->existencia($p->id, $datos['bodega_id'], $det['lote'] ?? null);
                $e->cantidad = (float) $e->cantidad + $cantidad;
                $e->fecha_vencimiento = $det['fecha_vencimiento'] ?? $e->fecha_vencimiento;
                $e->save();
                if ($p->metodo_costeo === 'PEPS') {
                    $this->capa($p->id, $datos['bodega_id'], $det, $cantidad, $costo, $mov->fecha->toDateString());
                }
                $p->costo_unitario = match ($p->metodo_costeo) {
                    'PROMEDIO' => ($stockAntes * (float) $p->costo_unitario + $cantidad * $costo) / ($stockAntes + $cantidad),
                    'PROMEDIO_ALIGACION' => $this->aligacion($p),
                    'PEPS' => $this->valorPeps($p),
                    'ULTIMA_COMPRA' => $costo,
                    default => throw ValidationException::withMessages(['metodo_costeo' => 'Método de costeo no reconocido.']),
                };
                $p->save();
            }

            return $this->totalizar($mov);
        });
    }

    public function registrarSalida(array $datos, $usuario_id)
    {
        $this->validar($datos, 'salida');

        return DB::transaction(function () use ($datos, $usuario_id) {
            $productos = $this->bloquear($datos);
            $mov = $this->movimiento('salida', $datos, $usuario_id);
            foreach ($datos['detalles'] as $det) {
                $this->descargar($mov, $productos[$det['producto_id']], $datos['bodega_id'], $det);
            }

            return $this->totalizar($mov);
        });
    }

    public function transferirBodega(array $datos, $usuario_id)
    {
        $this->validar($datos, 'transferencia');

        return DB::transaction(function () use ($datos, $usuario_id) {
            $productos = $this->bloquear($datos);
            $mov = $this->movimiento('transferencia', $datos, $usuario_id);
            foreach ($datos['detalles'] as $det) {
                $p = $productos[$det['producto_id']];
                foreach ($this->descargar($mov, $p, $datos['bodega_origen_id'], $det) as $parte) {
                    $dest = $this->existencia($p->id, $datos['bodega_destino_id'], $parte['lote']);
                    $dest->cantidad = (float) $dest->cantidad + $parte['cantidad'];
                    $dest->fecha_vencimiento = $parte['fecha_vencimiento'];
                    $dest->save();
                    if ($p->metodo_costeo === 'PEPS') {
                        $this->capa($p->id, $datos['bodega_destino_id'], $parte, $parte['cantidad'], $parte['costo'], $parte['fecha_ingreso']);
                    }
                }
                if ($p->metodo_costeo === 'PEPS') {
                    $p->update(['costo_unitario' => $this->valorPeps($p)]);
                }
            }

            return $this->totalizar($mov);
        });
    }

    private function descargar($mov, Producto $p, int $bodega, array $det): array
    {
        $restante = (float) $det['cantidad'];
        $partes = [];
        $query = Existencia::where('producto_id', $p->id)->where('bodega_id', $bodega)->where('cantidad', '>', 0);
        if (! empty($det['lote'])) {
            $query->where('lote', $det['lote']);
        }
        $existencias = $query->orderBy('id')->lockForUpdate()->get();
        if ($existencias->sum('cantidad') + 0.00001 < $restante) {
            throw ValidationException::withMessages(['cantidad' => 'Stock insuficiente para '.$p->nombre]);
        }
        if ($p->metodo_costeo === 'PEPS') {
            $capas = DB::table('capas_inventario')->where('producto_id', $p->id)->where('bodega_id', $bodega)->where('cantidad', '>', 0);
            if (! empty($det['lote'])) {
                $capas->where('lote', $det['lote']);
            }
            $capas = $capas->orderBy('fecha_ingreso')->orderBy('id')->lockForUpdate()->get();
            if (abs((float) $capas->sum('cantidad') - (float) $existencias->sum('cantidad')) > 0.00001) {
                throw ValidationException::withMessages(['costeo' => 'El inventario PEPS histórico requiere conciliar sus capas de costo antes de moverlo.']);
            }
            foreach ($capas as $capa) {
                if ($restante <= 0.00001) {
                    break;
                }
                $q = min($restante, (float) $capa->cantidad);
                DB::table('capas_inventario')->where('id', $capa->id)->update(['cantidad' => round($capa->cantidad - $q, 4)]);
                $e = $existencias->first(fn ($e) => $e->lote === $capa->lote);
                if (! $e || (float) $e->cantidad + 0.00001 < $q) {
                    throw ValidationException::withMessages(['costeo' => 'Las capas no coinciden con las existencias por lote.']);
                }
                $e->cantidad = round((float) $e->cantidad - $q, 4);
                $e->save();
                $partes[] = ['lote' => $capa->lote, 'fecha_vencimiento' => $capa->fecha_vencimiento, 'fecha_ingreso' => $capa->fecha_ingreso, 'cantidad' => $q, 'costo' => (float) $capa->costo_unitario];
                $restante = round($restante - $q, 4);
            }
        } else {
            foreach ($existencias as $e) {
                if ($restante <= 0.00001) {
                    break;
                }
                $q = min($restante, (float) $e->cantidad);
                $e->cantidad = round((float) $e->cantidad - $q, 4);
                $e->save();
                $partes[] = ['lote' => $e->lote, 'fecha_vencimiento' => $e->fecha_vencimiento?->toDateString(), 'fecha_ingreso' => $mov->fecha->toDateString(), 'cantidad' => $q, 'costo' => (float) $p->costo_unitario];
                $restante = round($restante - $q, 4);
            }
        }
        foreach ($partes as $parte) {
            $this->detalle($mov, $p, $parte, $parte['cantidad'], $parte['costo']);
        }
        if ($p->metodo_costeo === 'PEPS' && $mov->tipo !== 'transferencia') {
            $p->update(['costo_unitario' => $this->valorPeps($p)]);
        }

        return $partes;
    }

    private function existencia(int $producto, int $bodega, ?string $lote): Existencia
    {
        $filas = Existencia::where('producto_id', $producto)->where('bodega_id', $bodega)->where('lote', $lote)->lockForUpdate()->get();
        if ($filas->count() > 1) {
            throw ValidationException::withMessages(['existencia' => 'Hay existencias duplicadas: concilie el lote antes de moverlo.']);
        }

        return $filas->first() ?? new Existencia(['producto_id' => $producto, 'bodega_id' => $bodega, 'lote' => $lote, 'cantidad' => 0]);
    }

    private function capa(int $p, int $b, array $det, float $q, float $c, string $fecha): void
    {
        DB::table('capas_inventario')->insert(['producto_id' => $p, 'bodega_id' => $b, 'lote' => $det['lote'] ?? null, 'fecha_vencimiento' => $det['fecha_vencimiento'] ?? null, 'fecha_ingreso' => $fecha, 'cantidad' => $q, 'costo_unitario' => $c]);
    }

    private function valorPeps(Producto $p): float
    {
        $c = DB::table('capas_inventario')->where('producto_id', $p->id)->where('cantidad', '>', 0)->selectRaw('SUM(cantidad) AS cantidad, SUM(cantidad*costo_unitario) AS valor')->first();

        return $c->cantidad > 0 ? $c->valor / $c->cantidad : 0;
    }

    private function aligacion(Producto $p): float
    {
        $compras = DB::table('detalle_movimientos as d')->join('movimientos_inventario as m', 'm.id', '=', 'd.movimiento_id')
            ->where('m.tipo', 'entrada')->where('d.producto_id', $p->id)->select('m.id', 'm.fecha')
            ->selectRaw('SUM(d.cantidad) AS cantidad, SUM(d.costo_total) AS valor')->groupBy('m.id', 'm.fecha')->orderByDesc('m.fecha')->orderByDesc('m.id')->limit(5)->get();

        return $compras->sum('valor') / $compras->sum('cantidad');
    }

    private function movimiento(string $tipo, array $datos, int $usuario): MovimientoInventario
    {
        return MovimientoInventario::create(['tipo' => $tipo, 'numero_documento' => MovimientoInventario::generarNumeroDocumento($tipo),
            'fecha' => $datos['fecha'] ?? today()->toDateString(), 'usuario_id' => $usuario, 'costo_total' => 0,
            'bodega_origen_id' => $tipo === 'entrada' ? null : ($datos['bodega_origen_id'] ?? $datos['bodega_id']),
            'bodega_destino_id' => $tipo === 'salida' ? null : ($datos['bodega_destino_id'] ?? $datos['bodega_id']),
            'proveedor_id' => $datos['proveedor_id'] ?? null, 'referencia' => $datos['referencia'] ?? null, 'observaciones' => $datos['observaciones'] ?? null]);
    }

    private function detalle($mov, $p, array $det, float $q, float $c): void
    {
        $costoTotal = round($q * $c, 4);
        if ($costoTotal > self::COSTO_TOTAL_MAXIMO) {
            throw ValidationException::withMessages(['cantidad' => 'El costo total del detalle excede el máximo permitido.']);
        }

        DetalleMovimiento::create(['movimiento_id' => $mov->id, 'producto_id' => $p->id, 'lote' => $det['lote'] ?? null,
            'fecha_vencimiento' => $det['fecha_vencimiento'] ?? null, 'cantidad' => $q, 'costo_unitario' => $c, 'costo_total' => $costoTotal]);
    }

    private function totalizar($mov): MovimientoInventario
    {
        $costoTotal = $mov->detalles()->sum('costo_total');
        if ($costoTotal > self::COSTO_TOTAL_MAXIMO) {
            throw ValidationException::withMessages(['cantidad' => 'El costo total del movimiento excede el máximo permitido.']);
        }

        $mov->update(['costo_total' => $costoTotal]);

        return $mov;
    }
}

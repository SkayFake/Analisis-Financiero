<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleMovimiento extends Model
{
    protected $table = 'detalle_movimientos';

    protected $fillable = [
        'movimiento_id', 'producto_id', 'lote', 'fecha_vencimiento',
        'cantidad', 'costo_unitario', 'costo_total',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'cantidad' => 'decimal:4',
        'costo_unitario' => 'decimal:4',
        'costo_total' => 'decimal:4',
    ];

    public function movimiento()
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleVenta extends Model
{
    protected $fillable = [
        'venta_id', 'producto_id', 'num_item', 'cantidad', 'precio_unitario',
        'monto_descuento', 'venta_nosujeta', 'venta_exenta', 'venta_gravada'
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'precio_unitario' => 'decimal:4',
        'monto_descuento' => 'decimal:4',
        'venta_nosujeta' => 'decimal:4',
        'venta_exenta' => 'decimal:4',
        'venta_gravada' => 'decimal:4',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}

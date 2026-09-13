<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Existencia extends Model
{
    protected $fillable = [
        'producto_id', 'bodega_id', 'lote', 'fecha_vencimiento', 'cantidad',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'cantidad' => 'decimal:4',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function bodega()
    {
        return $this->belongsTo(Bodega::class);
    }

    /**
     * Verifica si el lote está vencido o próximo a vencer (30 días).
     */
    public function getEstadoCaducidadAttribute()
    {
        if (!$this->fecha_vencimiento) {
            return 'no_aplica';
        }

        $dias = now()->diffInDays($this->fecha_vencimiento, false);

        if ($dias < 0) {
            return 'vencido';
        } elseif ($dias <= 30) {
            return 'proximo';
        }

        return 'vigente';
    }
}

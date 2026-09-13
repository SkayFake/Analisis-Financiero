<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoCredito extends Model
{
    protected $table = 'productos_credito';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'tipo', 'tasa_interes',
        'comision', 'monto_minimo', 'monto_maximo', 'plazo_min_dias',
        'plazo_max_dias', 'dias_mora_incobrable', 'interes_moratorio',
        'requiere_fiador', 'activo',
    ];

    protected $casts = [
        'tasa_interes' => 'decimal:4',
        'comision' => 'decimal:4',
        'monto_minimo' => 'decimal:2',
        'monto_maximo' => 'decimal:2',
        'interes_moratorio' => 'decimal:4',
        'requiere_fiador' => 'boolean',
        'activo' => 'boolean',
    ];

    public function creditos()
    {
        return $this->hasMany(Credito::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }
}

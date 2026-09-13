<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'categoria_id', 'marca_id',
        'unidad_medida_id', 'proveedor_id', 'metodo_costeo', 'costo_unitario',
        'precio_venta', 'stock_minimo', 'stock_maximo', 'tiempo_espera_dias',
        'perecedero', 'activo',
    ];

    protected $casts = [
        'costo_unitario' => 'decimal:4',
        'precio_venta' => 'decimal:2',
        'stock_minimo' => 'decimal:2',
        'stock_maximo' => 'decimal:2',
        'tiempo_espera_dias' => 'integer',
        'perecedero' => 'boolean',
        'activo' => 'boolean',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function existencias()
    {
        return $this->hasMany(Existencia::class);
    }

    public function getStockTotalAttribute()
    {
        return $this->existencias()->sum('cantidad');
    }
}

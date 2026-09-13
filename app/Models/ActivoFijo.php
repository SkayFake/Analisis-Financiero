<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivoFijo extends Model
{
    protected $table = 'activos_fijos';

    protected $fillable = [
        'unidad_id', 'categoria_id', 'correlativo', 'codigo_inventario',
        'nombre', 'descripcion', 'fecha_adquisicion', 'valor_adquisicion',
        'valor_residual', 'es_usado', 'anios_uso_previo',
        'maquinaria_importada_exenta', 'valor_sujeto_depreciacion',
        'depreciacion_acumulada', 'estado', 'fecha_baja', 'motivo_baja'
    ];

    protected $casts = [
        'fecha_adquisicion' => 'date',
        'fecha_baja' => 'date',
        'valor_adquisicion' => 'decimal:4',
        'valor_residual' => 'decimal:4',
        'valor_sujeto_depreciacion' => 'decimal:4',
        'depreciacion_acumulada' => 'decimal:4',
        'es_usado' => 'boolean',
        'maquinaria_importada_exenta' => 'boolean',
    ];

    public function unidad()
    {
        return $this->belongsTo(Unidad::class);
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaActivo::class);
    }

    public function depreciaciones()
    {
        return $this->hasMany(Depreciacion::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Depreciacion extends Model
{
    protected $table = 'depreciaciones';

    protected $fillable = [
        'activo_fijo_id', 'fecha_calculo', 'anio', 'mes',
        'dias_depreciados', 'monto_depreciado',
        'depreciacion_acumulada_historica', 'valor_en_libros'
    ];

    protected $casts = [
        'fecha_calculo' => 'date',
        'monto_depreciado' => 'decimal:4',
        'depreciacion_acumulada_historica' => 'decimal:4',
        'valor_en_libros' => 'decimal:4',
    ];

    public function activoFijo()
    {
        return $this->belongsTo(ActivoFijo::class, 'activo_fijo_id');
    }
}

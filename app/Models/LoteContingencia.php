<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoteContingencia extends Model
{
    protected $table = 'lotes_contingencia';

    protected $fillable = [
        'codigo_lote', 'fecha_transmision', 'hora_transmision',
        'estado', 'sello_recepcion', 'motivo_contingencia'
    ];

    protected $casts = [
        'fecha_transmision' => 'date',
        'hora_transmision' => 'datetime:H:i',
    ];

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'lote_contingencia_id');
    }
}

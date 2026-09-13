<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoInvalidacion extends Model
{
    protected $table = 'eventos_invalidacion';

    protected $fillable = [
        'venta_id', 'codigo_generacion', 'sello_recepcion',
        'tipo_documento_anulado', 'motivo_invalidacion', 'responsable_nit',
        'estado', 'sello_invalidacion', 'json_firmado'
    ];

    protected $casts = [
        'json_firmado' => 'array',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }
}

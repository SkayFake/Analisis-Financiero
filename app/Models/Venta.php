<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $fillable = [
        'cliente_id', 'vendedor_id', 'tipo_documento', 'codigo_generacion',
        'numero_control', 'fecha_emision', 'hora_emision', 'condicion_operacion',
        'total_nosujeto', 'total_exento', 'total_gravado', 'iva_retenido',
        'iva_percibido', 'total_iva', 'monto_total_operacion', 'total_pagar',
        'estado_dte', 'sello_recepcion', 'json_firmado', 'mensaje_mh',
        'lote_contingencia_id'
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'hora_emision' => 'datetime:H:i',
        'json_firmado' => 'array',
        'total_nosujeto' => 'decimal:4',
        'total_exento' => 'decimal:4',
        'total_gravado' => 'decimal:4',
        'iva_retenido' => 'decimal:4',
        'iva_percibido' => 'decimal:4',
        'total_iva' => 'decimal:4',
        'monto_total_operacion' => 'decimal:4',
        'total_pagar' => 'decimal:4',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor()
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function loteContingencia()
    {
        return $this->belongsTo(LoteContingencia::class);
    }

    public function eventoInvalidacion()
    {
        return $this->hasOne(EventoInvalidacion::class);
    }
}

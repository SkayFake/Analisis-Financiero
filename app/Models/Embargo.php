<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Embargo extends Model
{
    protected $fillable = [
        'credito_id', 'fecha_embargo', 'descripcion_bienes',
        'valor_estimado', 'estado', 'fecha_resolucion',
        'monto_recuperado', 'observaciones', 'registrado_por',
    ];

    protected $casts = [
        'valor_estimado' => 'decimal:2',
        'monto_recuperado' => 'decimal:2',
        'fecha_embargo' => 'date',
        'fecha_resolucion' => 'date',
    ];

    public function credito()
    {
        return $this->belongsTo(Credito::class);
    }

    public function registrador()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}

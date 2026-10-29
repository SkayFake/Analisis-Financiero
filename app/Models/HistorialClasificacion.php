<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialClasificacion extends Model
{
    protected $table = 'historial_clasificaciones';

    protected $fillable = [
        'cliente_id', 'credito_id', 'clasificacion_anterior',
        'clasificacion_nueva', 'motivo', 'dias_mora',
        'reactivacion', 'realizado_por',
        'fecha', 'saldo_momento', 'registrado_por', 'observaciones',
    ];

    protected $casts = [
        'reactivacion' => 'boolean',
        'fecha' => 'date',
        'saldo_momento' => 'decimal:2',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function credito()
    {
        return $this->belongsTo(Credito::class);
    }

    public function realizadoPor()
    {
        return $this->belongsTo(User::class, 'realizado_por');
    }
}

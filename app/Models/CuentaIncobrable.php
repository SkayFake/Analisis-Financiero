<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaIncobrable extends Model
{
    protected $table = 'cuentas_incobrables';

    protected $fillable = [
        'credito_id', 'abierta', 'estado_anterior', 'saldo_capital', 'saldo_total', 'dias_mora',
        'motivo', 'declarado_at', 'declarado_por', 'reactivado_at', 'reactivado_por', 'motivo_reactivacion',
    ];

    protected $casts = [
        'abierta' => 'boolean', 'saldo_capital' => 'decimal:2', 'saldo_total' => 'decimal:2',
        'declarado_at' => 'datetime', 'reactivado_at' => 'datetime',
    ];

    public function credito()
    {
        return $this->belongsTo(Credito::class)->withTrashed();
    }

    public function declaradoPor()
    {
        return $this->belongsTo(User::class, 'declarado_por');
    }

    public function reactivadoPor()
    {
        return $this->belongsTo(User::class, 'reactivado_por');
    }
}

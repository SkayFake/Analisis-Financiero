<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refinanciamiento extends Model
{
    protected $fillable = [
        'credito_original_id', 'credito_nuevo_id', 'saldo_anterior',
        'nuevo_monto', 'intereses_pendientes', 'comisiones_pendientes',
        'motivo', 'fecha', 'aprobado_por',
    ];

    protected $casts = [
        'saldo_anterior' => 'decimal:2',
        'nuevo_monto' => 'decimal:2',
        'intereses_pendientes' => 'decimal:2',
        'comisiones_pendientes' => 'decimal:2',
        'fecha' => 'date',
    ];

    public function creditoOriginal()
    {
        return $this->belongsTo(Credito::class, 'credito_original_id');
    }

    public function creditoNuevo()
    {
        return $this->belongsTo(Credito::class, 'credito_nuevo_id');
    }

    public function aprobador()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}

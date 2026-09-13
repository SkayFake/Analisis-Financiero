<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuota extends Model
{
    protected $fillable = [
        'credito_id', 'numero_cuota', 'fecha_vencimiento', 'capital',
        'interes', 'comision', 'total', 'saldo_pendiente', 'monto_pagado',
        'estado', 'fecha_pago', 'dias_mora',
    ];

    protected $casts = [
        'capital' => 'decimal:2',
        'interes' => 'decimal:2',
        'comision' => 'decimal:2',
        'total' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
        'monto_pagado' => 'decimal:2',
        'fecha_vencimiento' => 'date',
        'fecha_pago' => 'date',
    ];

    public function credito()
    {
        return $this->belongsTo(Credito::class);
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopePendientes($query)
    {
        return $query->whereIn('estado', ['pendiente', 'vencida', 'parcial']);
    }

    public function scopeVencidas($query)
    {
        return $query->where('estado', 'vencida');
    }

    // ─── Atributos ───────────────────────────────────────────────

    public function getRestanteAttribute()
    {
        return $this->total - $this->monto_pagado;
    }

    public function getEstaVencidaAttribute()
    {
        return $this->fecha_vencimiento->isPast()
            && in_array($this->estado, ['pendiente', 'parcial']);
    }

    /**
     * Actualiza los días de mora de esta cuota.
     */
    public function actualizarDiasMora(): int
    {
        if (!$this->esta_vencida) {
            return 0;
        }

        $dias = (int) floor($this->fecha_vencimiento->diffInDays(now()));
        $this->update(['dias_mora' => $dias]);

        return $dias;
    }
}

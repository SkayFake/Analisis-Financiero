<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuota extends Model
{
    protected $fillable = [
        'credito_id',
        'numero_cuota',
        'fecha_vencimiento',
        'capital',
        'interes',
        'total',
        'mora',
        'saldo_pendiente',
        'monto_pagado',
        'estado',
        'fecha_pago',
        'dias_mora',
    ];

    protected $casts = [
        'capital'          => 'decimal:2',
        'interes'          => 'decimal:2',
        'total'            => 'decimal:2',
        'mora'             => 'decimal:2',
        'saldo_pendiente'  => 'decimal:2',
        'monto_pagado'     => 'decimal:2',
        'fecha_vencimiento' => 'date',
        'fecha_pago'       => 'date',
    ];

    public function credito()
    {
        return $this->belongsTo(Credito::class);
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopePendientes($query)
    {
        return $query->whereIn('estado', ['pendiente', 'vencida', 'parcial']);
    }

    public function scopeVencidas($query)
    {
        return $query->where('estado', 'vencida');
    }

    // ─── Atributos ───────────────────────────────────────────────────────

    /** Monto restante a pagar en esta cuota */
    public function getRestanteAttribute(): float
    {
        return (float) $this->total - (float) $this->monto_pagado;
    }

    /** Total incluyendo mora acumulada */
    public function getTotalConMoraAttribute(): float
    {
        return (float) $this->total + (float) $this->mora;
    }

    public function getEstaVencidaAttribute(): bool
    {
        if (!$this->fecha_vencimiento) return false;
        return $this->fecha_vencimiento->isPast()
            && in_array($this->estado, ['pendiente', 'parcial']);
    }

    /**
     * Calcula y actualiza los días de mora de esta cuota.
     * Se considera mora solo después de los días de gracia del crédito.
     */
    public function actualizarDiasMora(): int
    {
        if (!$this->esta_vencida) {
            return 0;
        }

        $diasGracia = $this->credito?->dias_gracia ?? 3;
        $diasVencida = (int) floor($this->fecha_vencimiento->diffInDays(now()));

        // Solo mora después de días de gracia
        $diasMora = max(0, $diasVencida - $diasGracia);
        $this->update(['dias_mora' => $diasMora]);

        return $diasMora;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Credito extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'numero', 'cliente_id', 'producto_credito_id', 'vendedor_id',
        'cartera_id', 'politica_cobro_id', 'monto_original', 'saldo_actual',
        'tasa_interes', 'comision', 'plazo_dias', 'numero_cuotas',
        'fecha_solicitud', 'fecha_aprobacion', 'fecha_desembolso',
        'fecha_vencimiento', 'fecha_cancelacion', 'estado', 'tipo_venta',
        'interes_moratorio', 'dias_mora', 'observaciones', 'aprobado_por',
    ];

    protected $casts = [
        'monto_original' => 'decimal:2',
        'saldo_actual' => 'decimal:2',
        'tasa_interes' => 'decimal:4',
        'comision' => 'decimal:4',
        'interes_moratorio' => 'decimal:4',
        'fecha_solicitud' => 'date',
        'fecha_aprobacion' => 'date',
        'fecha_desembolso' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_cancelacion' => 'date',
    ];

    // ─── Relaciones ──────────────────────────────────────────────

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function productoCredito()
    {
        return $this->belongsTo(ProductoCredito::class);
    }

    public function vendedor()
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function cartera()
    {
        return $this->belongsTo(Cartera::class);
    }

    public function politicaCobro()
    {
        return $this->belongsTo(PoliticaCobro::class);
    }

    public function cuotas()
    {
        return $this->hasMany(Cuota::class)->orderBy('numero_cuota');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class)->orderBy('fecha_pago');
    }

    public function fiadores()
    {
        return $this->hasMany(Fiador::class);
    }

    public function refinanciamientoOriginal()
    {
        return $this->hasOne(Refinanciamiento::class, 'credito_original_id');
    }

    public function refinanciamientoNuevo()
    {
        return $this->hasOne(Refinanciamiento::class, 'credito_nuevo_id');
    }

    public function embargos()
    {
        return $this->hasMany(Embargo::class);
    }

    public function aprobador()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeVigentes($query)
    {
        return $query->where('estado', 'vigente');
    }

    public function scopeVencidos($query)
    {
        return $query->where('estado', 'vencido');
    }

    public function scopeIncobrables($query)
    {
        return $query->where('estado', 'incobrable');
    }

    public function scopeActivos($query)
    {
        return $query->whereIn('estado', ['vigente', 'vencido']);
    }

    public function scopePorEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopeProximosAVencer($query, int $dias = 7)
    {
        return $query->vigentes()
            ->whereBetween('fecha_vencimiento', [now(), now()->addDays($dias)]);
    }

    // ─── Atributos computados ────────────────────────────────────

    public function getTotalPagadoAttribute()
    {
        return $this->pagos()->sum('monto_total');
    }

    public function getPorcentajePagadoAttribute()
    {
        if ($this->monto_original <= 0) return 0;
        return round(($this->total_pagado / $this->monto_original) * 100, 2);
    }

    public function getEstaVencidoAttribute()
    {
        return $this->fecha_vencimiento && $this->fecha_vencimiento->isPast()
            && in_array($this->estado, ['vigente', 'vencido']);
    }

    public function getCuotaPendienteAttribute()
    {
        return $this->cuotas()
            ->whereIn('estado', ['pendiente', 'vencida', 'parcial'])
            ->orderBy('numero_cuota')
            ->first();
    }

    /**
     * Genera un número único de crédito.
     */
    public static function generarNumero(): string
    {
        $año = date('Y');
        $ultimo = static::where('numero', 'like', "CR-{$año}-%")
            ->orderByRaw("CAST(SUBSTRING(numero FROM '[0-9]+$') AS INTEGER) DESC")
            ->value('numero');

        if ($ultimo) {
            $seq = (int) substr($ultimo, strrpos($ultimo, '-') + 1) + 1;
        } else {
            $seq = 1;
        }

        return sprintf('CR-%s-%06d', $año, $seq);
    }
}

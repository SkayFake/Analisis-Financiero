<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Crédito Comercial.
 *
 * Un crédito comercial nace cuando un cliente compra mercadería a crédito.
 * El monto del crédito = valor de la factura (venta).
 * NO es un préstamo de dinero; es la postergación de pago de mercadería.
 *
 * Flujo:
 * Venta al crédito → CreditoComercial → Cuotas → Pagos
 *
 * Estados de aprobación (Ley LPC El Salvador):
 * - 'pendiente_aprobacion': Cliente nuevo o monto > límite auto-aprobado
 * - 'aprobado': Crédito pre-aprobado, en espera de activarse
 * - 'vigente': Crédito activo, cuotas al día
 * - 'vencido': Tiene cuotas vencidas (> dias_gracia)
 * - 'incobrable': > 180 días en mora (estándar El Salvador para provisiones)
 * - 'refinanciado': Reestructurado
 * - 'cancelado': Pagado en su totalidad
 * - 'rechazado': No aprobado por la institución
 */
class Credito extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'numero',
        'cliente_id',
        'vendedor_id',
        'cartera_id',
        'politica_cobro_id',
        'venta_id',
        'condicion_credito_id',
        'monto_original',
        'saldo_actual',
        'interes_acumulado',
        'mora_acumulada',
        'tasa_interes_anual',
        'tasa_mora_mensual',
        'dias_gracia',
        'numero_cuotas',
        'frecuencia_pago',
        'plazo_dias',
        'fecha_solicitud',
        'fecha_aprobacion',
        'fecha_primera_cuota',
        'fecha_vencimiento',
        'fecha_cancelacion',
        'estado',
        'dias_mora',
        'observaciones',
        'aprobado_por',
        'motivo_aprobacion',
        'dui_verificado',
        'referencia_verificada',
    ];

    protected $casts = [
        'monto_original'      => 'decimal:2',
        'saldo_actual'        => 'decimal:2',
        'interes_acumulado'   => 'decimal:2',
        'mora_acumulada'      => 'decimal:2',
        'tasa_interes_anual'  => 'decimal:4',
        'tasa_mora_mensual'   => 'decimal:4',
        'fecha_solicitud'     => 'date',
        'fecha_aprobacion'    => 'date',
        'fecha_primera_cuota' => 'date',
        'fecha_vencimiento'   => 'date',
        'fecha_cancelacion'   => 'date',
        'dui_verificado'      => 'boolean',
        'referencia_verificada' => 'boolean',
    ];

    // ─── Relaciones ──────────────────────────────────────────────────────

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    /** La factura de origen del crédito comercial */
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    /** Alias semántico: la "factura" del crédito */
    public function factura()
    {
        return $this->venta();
    }

    public function condicionCredito()
    {
        return $this->belongsTo(CondicionCredito::class);
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

    // ─── Scopes ──────────────────────────────────────────────────────────

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

    public function scopePendienteAprobacion($query)
    {
        return $query->where('estado', 'pendiente_aprobacion');
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

    // ─── Atributos computados ────────────────────────────────────────────

    public function getTotalPagadoAttribute(): float
    {
        return (float) $this->pagos()->sum('monto_total');
    }

    public function getPorcentajePagadoAttribute(): float
    {
        if ((float) $this->monto_original <= 0) return 0;
        return round(($this->total_pagado / (float) $this->monto_original) * 100, 2);
    }

    public function getEstaVencidoAttribute(): bool
    {
        return $this->fecha_vencimiento
            && $this->fecha_vencimiento->isPast()
            && in_array($this->estado, ['vigente', 'vencido']);
    }

    public function getCuotaPendienteAttribute(): ?Cuota
    {
        return $this->cuotas()
            ->whereIn('estado', ['pendiente', 'vencida', 'parcial'])
            ->orderBy('numero_cuota')
            ->first();
    }

    /** Indica si tiene interés comercial o es crédito a precio de lista */
    public function getTieneInteresAttribute(): bool
    {
        return (float) $this->tasa_interes_anual > 0;
    }

    /** Etiqueta del tipo de crédito para la UI */
    public function getTipoCreditoLabelAttribute(): string
    {
        if ((float) $this->tasa_interes_anual <= 0) {
            return 'Crédito comercial sin interés';
        }
        return 'Crédito comercial con interés (' . number_format($this->tasa_interes_anual, 2) . '% anual)';
    }

    /** Etiqueta de frecuencia de pago */
    public function getFrecuenciaLabelAttribute(): string
    {
        return match ($this->frecuencia_pago) {
            'semanal'   => 'Semanal',
            'quincenal' => 'Quincenal',
            'mensual'   => 'Mensual',
            default     => $this->frecuencia_pago,
        };
    }

    /**
     * Genera un número único de crédito comercial.
     * Formato: CC-YYYY-NNNNNN
     */
    public static function generarNumero(): string
    {
        $año = date('Y');
        $ultimo = static::where('numero', 'like', "CC-{$año}-%")
            ->orderByDesc('id')
            ->value('numero');

        if ($ultimo) {
            $seq = (int) substr($ultimo, strrpos($ultimo, '-') + 1) + 1;
        } else {
            $seq = 1;
        }

        return sprintf('CC-%s-%06d', $año, $seq);
    }
}

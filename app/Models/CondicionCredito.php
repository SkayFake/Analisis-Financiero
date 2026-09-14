<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Condiciones de crédito comercial configurables por la institución.
 *
 * Marco legal El Salvador:
 * - Ley de Protección al Consumidor (D.776) Art. 18-A, 18-B: transparencia de tasas y mora.
 * - Ley de Usura (D.720): la tasa de interés no puede superar el doble de la
 *   tasa activa promedio bancaria publicada por el BCR (~18-22% anual en 2025-2026).
 * - Recomendación: configurar tasa_interes_anual = 0 para crédito a precio de lista
 *   y tasa_mora_mensual = 3% (estándar comercio salvadoreño).
 */
class CondicionCredito extends Model
{
    use SoftDeletes;

    protected $table = 'condiciones_credito';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activa',
        'tasa_interes_anual',
        'tasa_mora_mensual',
        'dias_gracia',
        'frecuencia_pago',
        'plazo_maximo_cuotas',
        'monto_auto_aprobado',
        'compras_minimas_cliente_frecuente',
        'requiere_fiador',
        'requiere_dui',
    ];

    protected $casts = [
        'activa'                              => 'boolean',
        'requiere_fiador'                     => 'boolean',
        'requiere_dui'                        => 'boolean',
        'tasa_interes_anual'                  => 'decimal:4',
        'tasa_mora_mensual'                   => 'decimal:4',
        'monto_auto_aprobado'                 => 'decimal:2',
    ];

    // ─── Relaciones ──────────────────────────────────────────────────────

    public function creditos()
    {
        return $this->hasMany(Credito::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /**
     * Calcula los días de una frecuencia para n cuotas.
     */
    public function calcularPlazoDias(int $numeroCuotas): int
    {
        return match ($this->frecuencia_pago) {
            'semanal'    => $numeroCuotas * 7,
            'quincenal'  => $numeroCuotas * 15,
            'mensual'    => $numeroCuotas * 30,
            default      => $numeroCuotas * 30,
        };
    }

    /**
     * Retorna la tasa de interés mensual equivalente.
     */
    public function tasaMensual(): float
    {
        return ((float) $this->tasa_interes_anual / 100) / 12;
    }

    /**
     * Descripción de la frecuencia en español.
     */
    public function getFrecuenciaLabelAttribute(): string
    {
        return match ($this->frecuencia_pago) {
            'semanal'   => 'Semanal (cada 7 días)',
            'quincenal' => 'Quincenal (cada 15 días)',
            'mensual'   => 'Mensual (cada 30 días)',
            default     => $this->frecuencia_pago,
        };
    }
}

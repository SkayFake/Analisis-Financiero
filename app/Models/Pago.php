<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $fillable = [
        'credito_id', 'cuota_id', 'numero_recibo', 'fecha_pago',
        'monto_total', 'abono_capital', 'pago_interes', 'pago_comision',
        'pago_mora', 'saldo_despues', 'forma_pago', 'referencia',
        'observaciones', 'recibido_por', 'vendedor_id', 'vendedor_reconstruido', 'vendedor_revisado_at',
    ];

    protected $casts = [
        'monto_total' => 'decimal:2',
        'abono_capital' => 'decimal:2',
        'pago_interes' => 'decimal:2',
        'pago_comision' => 'decimal:2',
        'pago_mora' => 'decimal:2',
        'saldo_despues' => 'decimal:2',
        'fecha_pago' => 'date',
        'vendedor_reconstruido' => 'boolean',
        'vendedor_revisado_at' => 'datetime',
    ];

    public function credito()
    {
        return $this->belongsTo(Credito::class);
    }

    public function vendedor() { return $this->belongsTo(Vendedor::class); }

    public function cuota()
    {
        return $this->belongsTo(Cuota::class);
    }

    public function aplicaciones()
    {
        return $this->hasMany(PagoCuota::class);
    }

    public function cuotasAfectadas()
    {
        return $this->belongsToMany(Cuota::class, 'pago_cuotas')
            ->withPivot(['monto_total', 'abono_capital', 'pago_interes', 'pago_mora'])
            ->withTimestamps();
    }

    public function recibidoPor()
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    /**
     * Genera un número único de recibo.
     */
    public static function generarNumeroRecibo(): string
    {
        $año = date('Y');
        $mes = date('m');
        $ultimo = static::where('numero_recibo', 'like', "REC-{$año}{$mes}-%")
            ->orderByRaw("CAST(SUBSTRING(numero_recibo FROM '[0-9]+$') AS INTEGER) DESC")
            ->value('numero_recibo');

        if ($ultimo) {
            $seq = (int) substr($ultimo, strrpos($ultimo, '-') + 1) + 1;
        } else {
            $seq = 1;
        }

        return sprintf('REC-%s%s-%06d', $año, $mes, $seq);
    }
}

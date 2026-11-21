<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'codigo', 'tipo', 'nombre', 'direccion', 'departamento', 'municipio',
        'telefono', 'celular', 'email', 'dui', 'nit', 'nrc',
        'estado_civil', 'lugar_trabajo', 'ingresos', 'egresos',
        'clasificacion_cobro', 'zona_id', 'cartera_id', 'vendedor_id',
        'activo', 'observaciones',
    ];

    protected $casts = [
        'ingresos' => 'decimal:2',
        'egresos' => 'decimal:2',
        'activo' => 'boolean',
    ];

    // ─── Relaciones ──────────────────────────────────────────────

    public function datosJuridicos()
    {
        return $this->hasOne(ClienteJuridico::class);
    }

    public function fiadores()
    {
        return $this->hasManyThrough(Fiador::class, Credito::class, 'cliente_id', 'credito_id');
    }

    public function creditos()
    {
        return $this->hasMany(Credito::class);
    }

    public function zona()
    {
        return $this->belongsTo(Zona::class);
    }

    public function cartera()
    {
        return $this->belongsTo(Cartera::class);
    }

    public function vendedor()
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function historialClasificaciones()
    {
        return $this->hasMany(HistorialClasificacion::class);
    }

    public function notificacionesCobro()
    {
        return $this->hasMany(CobranzaNotificacion::class)->orderByDesc('fecha_envio');
    }

    public function secuenciasCobranza()
    {
        return $this->hasMany(CobranzaSecuencia::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeNaturales($query)
    {
        return $query->where('tipo', 'natural');
    }

    public function scopeJuridicas($query)
    {
        return $query->where('tipo', 'juridica');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeClasificacion($query, string $clasificacion)
    {
        return $query->where('clasificacion_cobro', $clasificacion);
    }

    public function scopePorZona($query, $zonaId)
    {
        return $query->where('zona_id', $zonaId);
    }

    public function scopeBuscar($query, string $termino)
    {
        return $query->where(function ($q) use ($termino) {
            $q->where('nombre', 'ilike', "%{$termino}%")
              ->orWhere('codigo', 'ilike', "%{$termino}%")
              ->orWhere('dui', 'ilike', "%{$termino}%")
              ->orWhere('nit', 'ilike', "%{$termino}%");
        });
    }

    // ─── Atributos computados ─────────────────────────────────────

    public function getCapacidadPagoAttribute()
    {
        return $this->ingresos - $this->egresos;
    }

    public function getSaldoTotalAttribute()
    {
        return $this->creditos()
            ->whereIn('estado', ['vigente', 'vencido'])
            ->sum('saldo_actual');
    }

    public function getCreditosActivosCountAttribute()
    {
        return $this->creditos()
            ->whereIn('estado', ['vigente', 'vencido'])
            ->count();
    }

    public function getEsIncobrableAttribute()
    {
        return $this->clasificacion_cobro === 'D';
    }

    /**
     * Genera un código único para un nuevo cliente.
     */
    public static function generarCodigo(string $tipo): string
    {
        $prefijo = $tipo === 'natural' ? 'CN' : 'CJ';
        $ultimo = static::where('codigo', 'like', "{$prefijo}-%")
            ->orderByRaw("CAST(SUBSTRING(codigo FROM '[0-9]+$') AS INTEGER) DESC")
            ->value('codigo');

        if ($ultimo) {
            $numero = (int) preg_replace('/[^0-9]/', '', substr($ultimo, 3)) + 1;
        } else {
            $numero = 1;
        }

        return sprintf('%s-%06d', $prefijo, $numero);
    }

    /**
     * Auxiliar contable de ventas y pagos por cliente.
     * Registra cronológicamente ventas al crédito, cuotas, intereses y pagos con saldo acumulado.
     */
    public function obtenerAuxiliar(): \Illuminate\Support\Collection
    {
        $movimientos = collect();

        // 1. Créditos / Ventas a crédito (Cargos)
        $creditos = $this->creditos()->with(['venta', 'condicionCredito'])->get();
        foreach ($creditos as $c) {
            $fecha = $c->fecha_solicitud ?? $c->created_at->toDateString();
            $movimientos->push([
                'fecha' => $fecha,
                'tipo' => 'VENTA_CREDITO',
                'concepto' => "Venta al crédito {$c->numero}" . ($c->venta ? " (Factura {$c->venta->numero_control})" : ''),
                'referencia' => $c->numero,
                'cargo' => (float) $c->monto_original,
                'abono' => 0.00,
                'credito_id' => $c->id,
                'created_at' => $c->created_at,
            ]);

            // Intereses pactados/devengados si aplican
            if ((float) $c->interes_acumulado > 0) {
                $movimientos->push([
                    'fecha' => $fecha,
                    'tipo' => 'INTERES_CARGO',
                    'concepto' => "Intereses pactados crédito {$c->numero}",
                    'referencia' => $c->numero,
                    'cargo' => (float) $c->interes_acumulado,
                    'abono' => 0.00,
                    'credito_id' => $c->id,
                    'created_at' => $c->created_at->copy()->addSecond(),
                ]);
            }
        }

        // 2. Pagos recibidos (Abonos)
        $pagos = Pago::whereIn('credito_id', $creditos->pluck('id'))
            ->with('credito')
            ->orderBy('fecha_pago')
            ->get();

        foreach ($pagos as $p) {
            $movimientos->push([
                'fecha' => $p->fecha_pago->toDateString(),
                'tipo' => 'PAGO_RECIBO',
                'concepto' => "Abono a crédito {$p->credito?->numero} (Recibo {$p->numero_recibo})",
                'referencia' => $p->numero_recibo,
                'cargo' => 0.00,
                'abono' => (float) $p->monto_total,
                'detalle' => [
                    'capital' => (float) $p->abono_capital,
                    'interes' => (float) $p->pago_interes,
                    'mora' => (float) $p->pago_mora,
                ],
                'credito_id' => $p->credito_id,
                'created_at' => $p->created_at,
            ]);
        }

        // Ordenar cronológicamente y calcular saldo progresivo
        $ordenados = $movimientos->sortBy([
            ['fecha', 'asc'],
            ['created_at', 'asc'],
        ])->values();

        $saldoAcumulado = 0.0;
        return $ordenados->map(function ($item) use (&$saldoAcumulado) {
            $saldoAcumulado = round($saldoAcumulado + $item['cargo'] - $item['abono'], 2);
            $item['saldo_acumulado'] = $saldoAcumulado;
            return $item;
        });
    }
}

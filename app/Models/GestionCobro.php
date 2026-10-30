<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class GestionCobro extends Model
{
    protected $table = 'gestiones_cobro';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['fecha_compromiso' => 'date', 'monto_compromiso' => 'decimal:2', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Registre otra gestión para ampliar o corregir la anterior.'));
        static::deleting(fn () => throw new LogicException('Las gestiones no se eliminan.'));
    }

    public function credito()
    {
        return $this->belongsTo(Credito::class)->withTrashed();
    }

    public function getEstadoCompromisoAttribute(): string
    {
        if ($this->resultado !== 'promesa_pago') {
            return 'Sin compromiso';
        }
        $pagos = Pago::where('credito_id', $this->credito_id)->where('id', '>', $this->pago_base_id)
            ->where('fecha_pago', '>=', $this->created_at->toDateString());
        $aTiempo = (clone $pagos)->where('fecha_pago', '<=', $this->fecha_compromiso->toDateString())->sum('monto_total');
        if (round((float) $aTiempo, 2) >= (float) $this->monto_compromiso) {
            return 'Cumplido';
        }
        if (round((float) $pagos->sum('monto_total'), 2) >= (float) $this->monto_compromiso) {
            return 'Cumplido fuera de plazo';
        }

        return $this->fecha_compromiso->lt(today()) ? 'Incumplido' : 'Pendiente';
    }
}

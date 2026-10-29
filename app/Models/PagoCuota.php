<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoCuota extends Model
{
    protected $table = 'pago_cuotas';

    protected $fillable = [
        'pago_id',
        'cuota_id',
        'monto_total',
        'abono_capital',
        'pago_interes',
        'pago_mora',
    ];

    protected $casts = [
        'monto_total'   => 'decimal:2',
        'abono_capital' => 'decimal:2',
        'pago_interes'  => 'decimal:2',
        'pago_mora'     => 'decimal:2',
    ];

    public function pago()
    {
        return $this->belongsTo(Pago::class);
    }

    public function cuota()
    {
        return $this->belongsTo(Cuota::class);
    }
}


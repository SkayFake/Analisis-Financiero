<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fiador extends Model
{
    protected $table = 'fiadores';

    protected $fillable = [
        'cliente_id', 'credito_id', 'nombre', 'dui', 'nit',
        'direccion', 'telefono', 'lugar_trabajo', 'ingresos',
        'egresos', 'observaciones',
    ];

    protected $casts = [
        'ingresos' => 'decimal:2',
        'egresos' => 'decimal:2',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function credito()
    {
        return $this->belongsTo(Credito::class);
    }

    public function getCapacidadPagoAttribute()
    {
        return $this->ingresos - $this->egresos;
    }
}

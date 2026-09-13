<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoliticaCobro extends Model
{
    protected $table = 'politicas_cobro';

    protected $fillable = [
        'nombre', 'descripcion', 'dias_gracia', 'dias_cobro_30',
        'dias_cobro_60', 'interes_moratorio', 'dias_incobrable', 'activa',
    ];

    protected $casts = [
        'interes_moratorio' => 'decimal:4',
        'activa' => 'boolean',
    ];

    public function creditos()
    {
        return $this->hasMany(Credito::class);
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }
}

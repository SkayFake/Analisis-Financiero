<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cartera extends Model
{
    protected $fillable = ['codigo', 'nombre', 'descripcion', 'vendedor_id', 'activa'];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function vendedor()
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class);
    }

    public function creditos()
    {
        return $this->hasMany(Credito::class);
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    /**
     * Saldo total de la cartera.
     */
    public function getSaldoTotalAttribute()
    {
        return $this->creditos()
            ->whereIn('estado', ['vigente', 'vencido'])
            ->sum('saldo_actual');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zona extends Model
{
    protected $fillable = ['codigo', 'nombre', 'descripcion', 'activa'];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function vendedores()
    {
        return $this->hasMany(Vendedor::class);
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class);
    }

    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }
}

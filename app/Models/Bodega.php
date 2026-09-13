<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bodega extends Model
{
    protected $fillable = ['nombre', 'direccion', 'encargado_id', 'activa'];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function encargado()
    {
        return $this->belongsTo(User::class, 'encargado_id');
    }

    public function existencias()
    {
        return $this->hasMany(Existencia::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unidad extends Model
{
    protected $table = 'unidades';

    protected $fillable = ['institucion_id', 'codigo', 'nombre'];

    public function institucion()
    {
        return $this->belongsTo(Institucion::class);
    }

    public function activos()
    {
        return $this->hasMany(ActivoFijo::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Institucion extends Model
{
    protected $table = 'instituciones';

    protected $fillable = ['codigo', 'nombre'];

    public function unidades()
    {
        return $this->hasMany(Unidad::class);
    }
}

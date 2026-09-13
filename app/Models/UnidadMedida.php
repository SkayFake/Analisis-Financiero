<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    protected $table = 'unidades_medida';

    protected $fillable = ['nombre', 'abreviatura'];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}

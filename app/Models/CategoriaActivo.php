<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaActivo extends Model
{
    protected $table = 'categoria_activos';

    protected $fillable = ['codigo', 'nombre', 'porcentaje_depreciacion'];
    
    protected $casts = [
        'porcentaje_depreciacion' => 'decimal:2',
    ];

    public function activos()
    {
        return $this->hasMany(ActivoFijo::class, 'categoria_id');
    }
}

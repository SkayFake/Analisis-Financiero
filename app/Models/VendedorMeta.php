<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendedorMeta extends Model
{
    protected $table = 'vendedor_metas';

    protected $fillable = ['vendedor_id', 'periodo', 'meta_ventas', 'meta_cobros'];

    protected $casts = ['periodo' => 'date', 'meta_ventas' => 'decimal:2', 'meta_cobros' => 'decimal:2'];
}

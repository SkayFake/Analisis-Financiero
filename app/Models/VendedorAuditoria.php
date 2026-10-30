<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class VendedorAuditoria extends Model
{
    protected $table = 'vendedor_auditorias';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['antes' => 'array', 'despues' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('La bitácora es de solo inserción.'));
        static::deleting(fn () => throw new LogicException('La bitácora no se elimina.'));
    }
}

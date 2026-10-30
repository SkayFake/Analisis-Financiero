<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ExpedienteEvento extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['antes' => 'array', 'despues' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial documental es de solo inserción.'));
        static::deleting(fn () => throw new LogicException('El historial documental no se elimina.'));
    }
}

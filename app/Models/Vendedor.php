<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vendedor extends Model
{
    protected $table = 'vendedores';

    protected $fillable = [
        'codigo', 'user_id', 'nombre', 'telefono', 'email',
        'zona_id', 'meta_mensual', 'activo',
    ];

    protected $casts = [
        'meta_mensual' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function zona()
    {
        return $this->belongsTo(Zona::class);
    }

    public function carteras()
    {
        return $this->hasMany(Cartera::class);
    }

    public function clientes()
    {
        return $this->hasMany(Cliente::class);
    }

    public function creditos()
    {
        return $this->hasMany(Credito::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Calcula el total de cobros del vendedor en un rango de fechas.
     */
    public function totalCobros($fechaInicio, $fechaFin)
    {
        return $this->creditos()
            ->join('pagos', 'creditos.id', '=', 'pagos.credito_id')
            ->whereBetween('pagos.fecha_pago', [$fechaInicio, $fechaFin])
            ->sum('pagos.monto_total');
    }
}

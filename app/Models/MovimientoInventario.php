<?php

namespace App\Models;

use App\Services\CorrelativoService;
use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'tipo', 'numero_documento', 'fecha', 'bodega_origen_id',
        'bodega_destino_id', 'proveedor_id', 'usuario_id', 'referencia',
        'observaciones', 'costo_total',
    ];

    protected $casts = [
        'fecha' => 'date',
        'costo_total' => 'decimal:4',
    ];

    public function bodegaOrigen()
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    public function bodegaDestino()
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleMovimiento::class, 'movimiento_id');
    }

    public static function generarNumeroDocumento($tipo)
    {
        $prefijo = match ($tipo) {
            'entrada' => 'ENT',
            'salida' => 'SAL',
            'transferencia' => 'TRA',
            'ajuste' => 'AJU',
            default => 'MOV'
        };

        $max = (int) static::where('tipo', $tipo)->selectRaw("COALESCE(MAX(CAST(SUBSTRING(numero_documento FROM '[0-9]+$') AS BIGINT)),0) AS n")->value('n');
        $numero = app(CorrelativoService::class)->siguiente('inventario:'.$tipo, $max);

        return $prefijo.'-'.str_pad($numero, 6, '0', STR_PAD_LEFT);
    }
}

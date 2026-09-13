<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'codigo', 'tipo', 'nombre', 'direccion', 'departamento', 'municipio',
        'telefono', 'celular', 'email', 'dui', 'nit', 'nrc',
        'estado_civil', 'lugar_trabajo', 'ingresos', 'egresos',
        'clasificacion_cobro', 'zona_id', 'cartera_id', 'vendedor_id',
        'activo', 'observaciones',
    ];

    protected $casts = [
        'ingresos' => 'decimal:2',
        'egresos' => 'decimal:2',
        'activo' => 'boolean',
    ];

    // ─── Relaciones ──────────────────────────────────────────────

    public function datosJuridicos()
    {
        return $this->hasOne(ClienteJuridico::class);
    }

    public function fiadores()
    {
        return $this->hasMany(Fiador::class);
    }

    public function creditos()
    {
        return $this->hasMany(Credito::class);
    }

    public function zona()
    {
        return $this->belongsTo(Zona::class);
    }

    public function cartera()
    {
        return $this->belongsTo(Cartera::class);
    }

    public function vendedor()
    {
        return $this->belongsTo(Vendedor::class);
    }

    public function historialClasificaciones()
    {
        return $this->hasMany(HistorialClasificacion::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────

    public function scopeNaturales($query)
    {
        return $query->where('tipo', 'natural');
    }

    public function scopeJuridicas($query)
    {
        return $query->where('tipo', 'juridica');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeClasificacion($query, string $clasificacion)
    {
        return $query->where('clasificacion_cobro', $clasificacion);
    }

    public function scopePorZona($query, $zonaId)
    {
        return $query->where('zona_id', $zonaId);
    }

    public function scopeBuscar($query, string $termino)
    {
        return $query->where(function ($q) use ($termino) {
            $q->where('nombre', 'ilike', "%{$termino}%")
              ->orWhere('codigo', 'ilike', "%{$termino}%")
              ->orWhere('dui', 'ilike', "%{$termino}%")
              ->orWhere('nit', 'ilike', "%{$termino}%");
        });
    }

    // ─── Atributos computados ─────────────────────────────────────

    public function getCapacidadPagoAttribute()
    {
        return $this->ingresos - $this->egresos;
    }

    public function getSaldoTotalAttribute()
    {
        return $this->creditos()
            ->whereIn('estado', ['vigente', 'vencido'])
            ->sum('saldo_actual');
    }

    public function getCreditosActivosCountAttribute()
    {
        return $this->creditos()
            ->whereIn('estado', ['vigente', 'vencido'])
            ->count();
    }

    public function getEsIncobrableAttribute()
    {
        return $this->clasificacion_cobro === 'D';
    }

    /**
     * Genera un código único para un nuevo cliente.
     */
    public static function generarCodigo(string $tipo): string
    {
        $prefijo = $tipo === 'natural' ? 'CN' : 'CJ';
        $ultimo = static::where('codigo', 'like', "{$prefijo}-%")
            ->orderByRaw("CAST(SUBSTRING(codigo FROM '[0-9]+$') AS INTEGER) DESC")
            ->value('codigo');

        if ($ultimo) {
            $numero = (int) preg_replace('/[^0-9]/', '', substr($ultimo, 3)) + 1;
        } else {
            $numero = 1;
        }

        return sprintf('%s-%06d', $prefijo, $numero);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpedienteDocumento extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['vigente' => 'boolean', 'fecha_emision' => 'date', 'fecha_vencimiento' => 'date', 'revisado_at' => 'datetime'];

    protected $hidden = ['ruta'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function credito()
    {
        return $this->belongsTo(Credito::class);
    }

    public function fiador()
    {
        return $this->belongsTo(Fiador::class);
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function revisor()
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}

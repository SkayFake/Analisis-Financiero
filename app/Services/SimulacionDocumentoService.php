<?php

namespace App\Services;

use App\Models\Venta;

class SimulacionDocumentoService
{
    public function generar(Venta $venta): array
    {
        abort_unless(config('erp.modo_academico'), 404);
        $venta->loadMissing('cliente', 'detalles.producto');

        return [
            'es_simulacion' => true, 'validez_fiscal' => false,
            'aviso' => 'Documento didáctico con datos ficticios. No es un DTE autorizado ni está firmado o transmitido a Hacienda.',
            'identificacion' => ['tipo_documento_local' => $venta->tipo_documento, 'numero_local' => $venta->numero_control,
                'codigo_interno' => $venta->codigo_generacion, 'fecha' => $venta->fecha_emision->toDateString(), 'moneda' => 'USD'],
            'emisor' => ['nombre' => 'Comercial ficticia del proyecto universitario'],
            'receptor' => ['nombre' => $venta->cliente?->nombre ?? 'Consumidor ficticio'],
            'cuerpo_documento' => $venta->detalles->map(fn ($d) => ['item' => $d->num_item, 'descripcion' => $d->producto?->nombre,
                'cantidad' => (float) $d->cantidad, 'precio_unitario' => (float) $d->precio_unitario, 'importe' => (float) $d->venta_gravada])->all(),
            'resumen' => ['importe_sin_iva' => round((float) $venta->total_pagar - (float) $venta->total_iva, 2),
                'iva' => (float) $venta->total_iva, 'total' => (float) $venta->total_pagar, 'condicion_operacion' => $venta->condicion_operacion],
            'firma_electronica' => null, 'sello_hacienda' => null, 'transmitido' => false,
        ];
    }
}

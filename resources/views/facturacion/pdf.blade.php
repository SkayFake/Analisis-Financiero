<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Documento Tributario - {{ $venta->numero_control }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 4px; vertical-align: top; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        .items-table th { background-color: #f5f5f5; }
        .totals { width: 50%; float: right; border-collapse: collapse; }
        .totals td { padding: 4px; border-bottom: 1px solid #ddd; }
        .totals .bold { font-weight: bold; }
        .footer { clear: both; text-align: center; margin-top: 40px; font-size: 10px; color: #555; }
        
        /* Modificadores para Ticket */
        @if(in_array($venta->tipo_documento, ['11']))
        body { width: 80mm; padding: 0; margin: 0 auto; font-family: monospace; font-size: 10px; }
        .items-table th, .items-table td { border: none; border-bottom: 1px dashed #ccc; padding: 2px 0; }
        .totals { width: 100%; }
        @endif
    </style>
</head>
<body>

    <div class="header">
        <h1>Mi Empresa S.A. de C.V.</h1>
        <p>
            @if($venta->tipo_documento == '01') FACTURA DE CONSUMIDOR FINAL 
            @elseif($venta->tipo_documento == '03') COMPROBANTE DE CRÉDITO FISCAL
            @elseif($venta->tipo_documento == '05') NOTA DE CRÉDITO
            @elseif($venta->tipo_documento == '06') NOTA DE DÉBITO
            @else TICKET DE VENTA @endif
            <br>
            <strong>{{ $venta->numero_control }}</strong><br>
            UUID: {{ $venta->codigo_generacion }}<br>
            Fecha: {{ $venta->fecha_emision->format('d/m/Y') }} {{ $venta->hora_emision->format('H:i') }}
        </p>
    </div>

    @if($venta->cliente)
    <table class="info-table">
        <tr>
            <td width="15%"><strong>Cliente:</strong></td>
            <td width="35%">{{ $venta->cliente->nombre }}</td>
            <td width="15%"><strong>NIT / NRC:</strong></td>
            <td width="35%">{{ $venta->cliente->nit ?? $venta->cliente->nrc ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>Dirección:</strong></td>
            <td colspan="3">{{ $venta->cliente->direccion ?? 'N/A' }}</td>
        </tr>
    </table>
    @else
    <p><strong>Cliente:</strong> Público General</p>
    @endif

    <table class="items-table">
        <thead>
            <tr>
                <th width="10%">Cant.</th>
                <th width="50%">Descripción</th>
                <th width="20%">P. Unit</th>
                <th width="20%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $det)
            <tr>
                <td>{{ $det->cantidad }}</td>
                <td>{{ $det->producto ? $det->producto->nombre : 'Item' }}</td>
                <td>${{ number_format($det->precio_unitario, 2) }}</td>
                <td>${{ number_format($det->venta_gravada, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Sumas:</td>
            <td align="right">${{ number_format($venta->total_gravado, 2) }}</td>
        </tr>
        @if($venta->tipo_documento == '03')
        <tr>
            <td>13% IVA:</td>
            <td align="right">${{ number_format($venta->total_gravado * 0.13, 2) }}</td>
        </tr>
        @endif
        <tr>
            <td class="bold">TOTAL A PAGAR:</td>
            <td align="right" class="bold">${{ number_format($venta->total_pagar, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        <p>Sello de Recepción MH: {{ $venta->sello_recepcion ?? 'PENDIENTE (Contingencia/Interno)' }}</p>
        <p>¡Gracias por su compra!</p>
    </div>

</body>
</html>

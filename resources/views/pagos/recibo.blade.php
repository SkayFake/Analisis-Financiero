<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $pago->numero_recibo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #243247; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .header { text-align: center; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { padding: 8px; border-bottom: 1px solid #dce2e9; text-align: left; }
        th { background: #f2f5f9; }
        .money { text-align: right; }
        .total { font-size: 15px; font-weight: bold; }
        .note { margin-top: 24px; font-size: 10px; }
        .text { white-space: pre-wrap; overflow-wrap: break-word; }
    </style>
</head>
<body>
    <div class="header">
        <div>Análisis Financiero</div>
        <h1>RECIBO DE PAGO</h1>
        <strong>{{ $pago->numero_recibo }}</strong>
        <p>COMPROBANTE INTERNO — Sin autorización fiscal.</p>
    </div>
    <table>
        <tr><td><strong>Cliente:</strong> {{ $credito->cliente?->nombre ?? 'Cliente no disponible' }}</td><td><strong>Código:</strong> {{ $credito->cliente?->codigo }}</td></tr>
        <tr><td><strong>Crédito:</strong> {{ $credito->numero }}</td><td><strong>Factura de origen:</strong> {{ $credito->venta?->numero_control ?? 'No disponible' }}</td></tr>
        <tr><td><strong>Fecha del pago:</strong> {{ $pago->fecha_pago?->format('d/m/Y') }}</td><td><strong>Forma de pago:</strong> {{ ucfirst($pago->forma_pago) }}</td></tr>
        <tr><td><strong>Referencia:</strong> {{ $pago->referencia ?? 'Sin referencia' }}</td><td><strong>Recibido por:</strong> {{ $pago->recibidoPor?->name ?? 'No registrado' }}</td></tr>
    </table>
    <table>
        <thead><tr><th>Concepto</th><th class="money">Importe USD</th></tr></thead>
        <tbody>
            <tr><td>Abono a capital</td><td class="money">${{ number_format($pago->abono_capital, 2) }}</td></tr>
            <tr><td>Interés</td><td class="money">${{ number_format($pago->pago_interes, 2) }}</td></tr>
            <tr><td>Mora</td><td class="money">${{ number_format($pago->pago_mora, 2) }}</td></tr>
            <tr class="total"><td>Total recibido</td><td class="money">${{ number_format($pago->monto_total, 2) }}</td></tr>
            <tr><td>Saldo de capital después de este pago</td><td class="money">${{ number_format($pago->saldo_despues, 2) }}</td></tr>
        </tbody>
    </table>
    <h2 style="font-size: 14px;">Aplicación a cuotas</h2>
    @if($pago->aplicaciones->isNotEmpty())
        <table>
            <thead><tr><th>Cuota</th><th class="money">Capital</th><th class="money">Interés</th><th class="money">Mora</th><th class="money">Total aplicado</th></tr></thead>
            <tbody>
            @foreach($pago->aplicaciones as $aplicacion)
                <tr><td>#{{ $aplicacion->cuota?->numero_cuota ?? 'No disponible' }}</td>
                    <td class="money">${{ number_format($aplicacion->abono_capital, 2) }}</td>
                    <td class="money">${{ number_format($aplicacion->pago_interes, 2) }}</td>
                    <td class="money">${{ number_format($aplicacion->pago_mora, 2) }}</td>
                    <td class="money">${{ number_format($aplicacion->monto_total, 2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p>Pago histórico sin detalle conciliado por cuota.
            @if($pago->cuota) Cuota asociada en el registro original: #{{ $pago->cuota->numero_cuota }}. @endif
        </p>
    @endif
    @if($pago->observaciones)
        <p><strong>Observaciones:</strong></p><div class="text">{{ $pago->observaciones }}</div>
    @endif
    <p class="note">Este recibo acredita el pago registrado y puede reimprimirse. El saldo corresponde al capital después de este pago; los intereses y la mora pendientes se consultan en el plan de cuotas. Documento interno del proyecto académico; no se transmite a Hacienda.</p>
</body>
</html>

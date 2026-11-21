<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 24px;
            color: #1e293b;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #0f172a;
            color: #ffffff;
            padding: 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: #94a3b8;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .body {
            padding: 24px;
            line-height: 1.6;
        }
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            margin: 20px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .info-row:last-child {
            margin-bottom: 0;
        }
        .info-label {
            color: #64748b;
        }
        .info-value {
            font-weight: 600;
            color: #0f172a;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 16px 24px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>El Tablazo — Notificación Oficial de Crédito</h1>
            <p>Departamento de Cobranzas y Recuperación de Cartera</p>
            @php
                $badgeClass = match($tipoNotificacion) {
                    'pago_realizado' => 'badge-success',
                    'cobro_pendiente' => 'badge-info',
                    'cobro_vencido' => 'badge-warning',
                    'aviso_secuencia' => 'badge-danger',
                    default => 'badge-info',
                };
            @endphp
            <div class="badge {{ $badgeClass }}">
                {{ strtoupper(str_replace('_', ' ', $tipoNotificacion)) }}
            </div>
        </div>

        <div class="body">
            <p>Estimado(a) <strong>{{ $cliente->nombre }}</strong> (Código: {{ $cliente->codigo }}):</p>

            <div style="white-space: pre-line; margin: 16px 0;">
                {{ $cuerpoMensaje }}
            </div>

            @if($credito)
            <div class="info-box">
                <div class="info-row">
                    <span class="info-label">Crédito Comercial:</span>
                    <span class="info-value">{{ $credito->numero }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Saldo Pendiente:</span>
                    <span class="info-value">${{ number_format($credito->saldo_actual, 2) }}</span>
                </div>
                @if($credito->dias_mora > 0)
                <div class="info-row">
                    <span class="info-label">Días de Atraso (Mora):</span>
                    <span class="info-value" style="color: #b91c1c;">{{ $credito->dias_mora }} días</span>
                </div>
                @endif
                @if($credito->fecha_vencimiento)
                <div class="info-row">
                    <span class="info-label">Fecha de Vencimiento:</span>
                    <span class="info-value">{{ $credito->fecha_vencimiento->format('d/m/Y') }}</span>
                </div>
                @endif
                @if($etapaSecuencia)
                <div class="info-row">
                    <span class="info-label">Etapa de Secuencia:</span>
                    <span class="info-value" style="color: #b91c1c;">{{ strtoupper(str_replace('_', ' ', $etapaSecuencia)) }}</span>
                </div>
                @endif
            </div>
            @endif

            @if($pago)
            <div class="info-box" style="border-left: 4px solid #10b981;">
                <div class="info-row">
                    <span class="info-label">Número de Recibo:</span>
                    <span class="info-value">{{ $pago->numero_recibo }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha de Pago:</span>
                    <span class="info-value">{{ $pago->fecha_pago->format('d/m/Y') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Monto Recibido:</span>
                    <span class="info-value" style="color: #10b981;">${{ number_format($pago->monto_total, 2) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Abono a Capital:</span>
                    <span class="info-value">${{ number_format($pago->abono_capital, 2) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Intereses / Mora Cancelada:</span>
                    <span class="info-value">${{ number_format($pago->pago_interes + $pago->pago_mora, 2) }}</span>
                </div>
            </div>
            @endif

            <p style="font-size: 13px; color: #64748b;">
                Para cualquier consulta, aclaración o para coordinar sus modalidades de pago, por favor comuníquese a nuestras líneas de atención o visítenos en nuestras oficinas.
            </p>
        </div>

        <div class="footer">
            Este es un comunicado generado por el sistema de cobranzas de El Tablazo.<br>
            San Salvador, El Salvador.
        </div>
    </div>
</body>
</html>

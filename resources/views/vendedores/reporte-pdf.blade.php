<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Control de vendedores {{ $periodo->format('Y-m') }}</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#243247}h1{font-size:18px}h2{font-size:13px}table{width:100%;border-collapse:collapse;margin:12px 0}th,td{padding:6px;border-bottom:1px solid #ddd;text-align:left}th{background:#edf2f8}.money{text-align:right}</style></head><body>
<h1>Análisis Financiero · Control de vendedores</h1><p>Mes de ventas y cobros: <strong>{{ $periodo->format('m/Y') }}</strong>. Corte de cartera: <strong>{{ $corte->format('d/m/Y H:i:s') }}</strong> ({{ config('app.timezone') }}). Generado por {{ auth()->user()->name }}.</p>
<table><thead><tr><th>Vendedor</th>
<th>Ventas sin IVA</th>
<th>Meta ventas / avance</th>
<th>Cobros crédito</th>
<th>Meta cobros / avance</th>
<th>Capital cartera actual</th>
<th>Cuotas vencidas</th>
<th>Capital incobrable</th>
</tr>
</thead>
<tbody>
@foreach($resumenes as $r)<tr><td>{{ $r['vendedor']->codigo }}<br>{{ $r['vendedor']->nombre }}</td>
<td class="money">${{ number_format($r['ventas'],2) }}</td>
<td>${{ number_format($r['meta_ventas'],2) }}<br>{{ $r['avance_ventas'] !== null ? number_format($r['avance_ventas'],1).'%' : 'Sin objetivo' }}</td>
<td class="money">${{ number_format($r['cobros'],2) }}</td>
<td>${{ number_format($r['meta_cobros'],2) }}<br>{{ $r['avance_cobros'] !== null ? number_format($r['avance_cobros'],1).'%' : 'Sin objetivo' }}</td>
<td class="money">${{ number_format($r['cartera_capital'],2) }}</td>
<td class="money">${{ number_format($r['vencido'],2) }}</td>
<td class="money">${{ number_format($r['incobrable'],2) }}</td>
</tr>@endforeach
</tbody>
</table>
@if($cartera->isNotEmpty())<h2>Cartera actual a cargo</h2><table><thead><tr><th>Crédito / cliente</th>
<th>Estado</th>
<th>Origen</th>
<th>Capital</th>
<th>Deuda total</th>
<th>Cuotas vencidas</th>
<th>Primer vencimiento impago</th>
</tr>
</thead>
<tbody>@foreach($cartera as $c)<tr><td>{{ $c->numero }}<br>{{ $c->cliente?->nombre }}</td>
<td>{{ $c->estado }}</td>
<td>{{ $c->vendedorOrigen?->nombre ?? 'No documentado' }}</td>
<td>${{ number_format($c->saldo_actual,2) }}</td>
<td>${{ number_format($c->deuda_total,2) }}</td>
<td>${{ number_format($c->deuda_vencida,2) }}</td>
<td>{{ $c->primer_vencimiento }}</td>
</tr>@endforeach</tbody>
</table>@endif
<p>Informe interno de control. Los recibos de crédito se atribuyen al responsable registrado al recibir el pago; la persona que recibe el dinero consta en el recibo. Las ventas excluyen documentos sin emisión válida y solicitudes de crédito sin activar. La cartera es actual y no representa el saldo histórico al cierre del mes seleccionado. Las atribuciones anteriores a este módulo pudieron reconstruirse desde la asignación existente; deben revisarse en la bitácora y el CSV de recibos.</p>
</body></html>

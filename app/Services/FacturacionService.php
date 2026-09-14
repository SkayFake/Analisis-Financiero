<?php

namespace App\Services;

use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\LoteContingencia;
use App\Jobs\TransmitirDteContingencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class FacturacionService
{
    protected $inventarioService;
    protected $firmadorService;
    protected $dteApiService;
    protected $creditoService;

    public function __construct(
        InventarioService $inventarioService,
        FirmadorService   $firmadorService,
        DteApiService     $dteApiService,
        CreditoService    $creditoService
    ) {
        $this->inventarioService = $inventarioService;
        $this->firmadorService   = $firmadorService;
        $this->dteApiService     = $dteApiService;
        $this->creditoService    = $creditoService;
    }

    /**
     * Procesa la venta, rebaja inventario, firma y transmite al MH.
     */
    public function procesarVenta(array $datosVenta, array $detalles, $usuarioId)
    {
        return DB::transaction(function () use ($datosVenta, $detalles, $usuarioId) {
            
            // 1. Crear el registro de Venta
            $venta = Venta::create([
                'cliente_id' => $datosVenta['cliente_id'] ?? null,
                'vendedor_id' => $datosVenta['vendedor_id'] ?? null,
                'tipo_documento' => $datosVenta['tipo_documento'], // 01 FCF, 03 CCF, 11 Ticket
                'codigo_generacion' => strtoupper((string) Str::uuid()),
                'numero_control' => 'DTE-'. $datosVenta['tipo_documento'] . '-' . str_pad(Venta::where('tipo_documento', $datosVenta['tipo_documento'])->count() + 1, 15, '0', STR_PAD_LEFT),
                'fecha_emision' => now()->toDateString(),
                'hora_emision' => now()->toTimeString(),
                'condicion_operacion' => $datosVenta['condicion_operacion'] ?? '1', // 1 Contado, 2 Crédito
                'estado_dte' => 'pendiente',
            ]);

            $totales = [
                'gravado' => 0, 'exento' => 0, 'nosujeto' => 0, 'descuento' => 0
            ];

            // 2. Procesar Detalles y Rebajar Inventario
            $detallesParaInventario = [];
            $bodegaId = $datosVenta['bodega_id'] ?? 1; // Asumimos bodega 1 si no viene

            foreach ($detalles as $index => $det) {
                $montoGravado = $det['cantidad'] * $det['precio_unitario'];
                // TODO: lógica fina de si el producto es exento o no sujeto. Asumimos gravado.
                $totales['gravado'] += $montoGravado;

                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $det['producto_id'] ?? null,
                    'num_item' => $index + 1,
                    'cantidad' => $det['cantidad'],
                    'precio_unitario' => $det['precio_unitario'],
                    'venta_gravada' => $montoGravado,
                ]);

                if (!empty($det['producto_id'])) {
                    $detallesParaInventario[] = [
                        'producto_id' => $det['producto_id'],
                        'cantidad' => $det['cantidad'],
                    ];
                }
            }

            // Llamada al Inventario Service para salida
            if (!empty($detallesParaInventario)) {
                $this->inventarioService->registrarSalida([
                    'bodega_id' => $bodegaId,
                    'referencia' => 'Venta ' . $venta->numero_control,
                    'detalles' => $detallesParaInventario
                ], $usuarioId);
            }

            // Cálculos finales de la Venta
            $ivaTotal = ($venta->tipo_documento === '03') ? round($totales['gravado'] * 0.13, 2) : 0;
            $totalPagar = $totales['gravado'] + $ivaTotal;
            $venta->update([
                'total_gravado' => $totales['gravado'],
                'total_iva' => $ivaTotal,
                'monto_total_operacion' => $totalPagar,
                'total_pagar' => $totalPagar,
            ]);

            // 3. Si es venta al crédito → crear crédito comercial automáticamente
            if ($venta->condicion_operacion === '2' && $venta->cliente_id) {
                $condicionesCredito = $datosVenta['condiciones_credito'] ?? [];
                if (!empty($condicionesCredito['condicion_credito_id'])) {
                    try {
                        $this->creditoService->crearCreditoDesdeVenta($venta, $condicionesCredito);
                    } catch (\Exception $e) {
                        // El crédito no bloquea la factura; se puede crear manualmente después.
                        \Log::warning("Factura {$venta->numero_control}: no se pudo crear crédito automáticamente. " . $e->getMessage());
                    }
                }
            }

            // 4. Firmar y Transmitir si no es Ticket interno
            if (in_array($venta->tipo_documento, ['01', '03', '05', '06'])) {
                $this->firmarYTransmitir($venta);
            }

            return $venta;
        });
    }

    private function firmarYTransmitir(Venta $venta)
    {
        try {
            // Generar JSON estructural DTE (Simplificado aquí, en la vida real es un builder largo)
            $jsonEstructural = [
                'identificacion' => [
                    'version' => 1,
                    'ambiente' => '00',
                    'tipoDte' => $venta->tipo_documento,
                    'numeroControl' => $venta->numero_control,
                    'codigoGeneracion' => $venta->codigo_generacion,
                ],
                // ... más campos requeridos por MH ...
            ];

            // 1. Firmar
            $jsonFirmado = $this->firmadorService->firmarDocumento($jsonEstructural);
            $venta->json_firmado = $jsonFirmado;
            $venta->save();

            // 2. Transmitir
            $respuestaMH = $this->dteApiService->transmitirDTE($jsonFirmado, $venta->tipo_documento, $venta->codigo_generacion);

            if (isset($respuestaMH['estado']) && $respuestaMH['estado'] == 'PROCESADO') {
                $venta->estado_dte = 'procesado';
                $venta->sello_recepcion = $respuestaMH['selloRecibido'];
                $venta->mensaje_mh = 'Procesado correctamente';
            } else {
                $venta->estado_dte = 'rechazado';
                $venta->mensaje_mh = json_encode($respuestaMH);
            }

        } catch (Exception $e) {
            // ENTRAR EN CONTINGENCIA SI FALLA LA CONEXIÓN O EL FIRMADOR
            $venta->estado_dte = 'contingencia';
            $venta->mensaje_mh = $e->getMessage();
            
            // Asignar a un lote de contingencia activo o crear uno
            $lote = LoteContingencia::firstOrCreate(
                ['estado' => 'pendiente'],
                ['codigo_lote' => strtoupper((string) Str::uuid()), 'fecha_transmision' => now()->toDateString(), 'hora_transmision' => now()->toTimeString()]
            );

            $venta->lote_contingencia_id = $lote->id;
            
            // Encolar Job para intentar transmitir luego
            TransmitirDteContingencia::dispatch($venta->id)->delay(now()->addMinutes(5));
        }

        $venta->save();
    }
}

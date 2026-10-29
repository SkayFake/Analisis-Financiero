<?php

namespace App\Services;

use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\LoteContingencia;
use App\Models\Producto;
use App\Models\User;
use App\Jobs\TransmitirDteContingencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Exceptions\DteConfigurationException;
use InvalidArgumentException;
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
     * Procesa la venta, rebaja inventario, genera crédito comercial si aplica, firma y transmite al MH.
     * Garantiza que si falla una operación local requerida (venta, inventario o crédito), se reviertan los cambios.
     */
    public function procesarVenta(array $datosVenta, array $detalles, $usuarioId)
    {
        if (isset($datosVenta['condicion_operacion']) && is_scalar($datosVenta['condicion_operacion'])) {
            $datosVenta['condicion_operacion'] = (string) $datosVenta['condicion_operacion'];
        }
        Validator::make(array_merge($datosVenta, ['detalles' => $detalles]), [
            'tipo_documento' => 'required|string|size:2|in:01,03,11,05,06',
            'condicion_operacion' => 'required|string|in:1,2',
            'bodega_id' => ['required', 'integer', Rule::exists('bodegas', 'id')->where('activa', true)],
            'cliente_id' => ['nullable', 'required_if:tipo_documento,03', 'required_if:condicion_operacion,2', Rule::exists('clientes', 'id')->where('activo', true)->whereNull('deleted_at')],
            'detalles' => 'required|array|min:1|max:100',
            'detalles.*.producto_id' => ['required', 'integer', Rule::exists('productos', 'id')->where('activo', true)],
            'detalles.*.cantidad' => 'required|numeric|min:0.01|max:999999.9999|decimal:0,4',
            'detalles.*.precio_unitario' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2',
        ])->validate();

        return DB::transaction(function () use ($datosVenta, $detalles, $usuarioId) {
            $usuario = User::findOrFail($usuarioId);
            $productos = Producto::whereIn('id', array_column($detalles, 'producto_id'))->sharedLock()->get()->keyBy('id');
            $modificaPrecio = $usuario->hasRole('super-admin') || $usuario->can('facturacion.modificar_precio');
            $total = 0;
            foreach ($detalles as $i => $detalle) {
                $producto = $productos->get($detalle['producto_id']);
                if (!$producto || !$producto->activo) {
                    throw ValidationException::withMessages(["detalles.{$i}.producto_id" => 'El producto no está habilitado.']);
                }
                if (!$modificaPrecio && (int) round((float) $detalle['precio_unitario'] * 100) !== (int) round((float) $producto->precio_venta * 100)) {
                    throw ValidationException::withMessages(["detalles.{$i}.precio_unitario" => 'No tiene permiso para modificar el precio del catálogo.']);
                }
                $total += round($detalle['cantidad'] * $detalle['precio_unitario'], 2);
            }
            if ($total <= 0 || $total * 1.13 > 9999999999.99) {
                throw ValidationException::withMessages(['detalles' => 'El total de la venta debe ser positivo y estar dentro del límite admitido.']);
            }
            $condicionOperacion = (string) ($datosVenta['condicion_operacion'] ?? '1');

            // Si es venta al crédito, validar previamente que vengan los datos requeridos
            if ($condicionOperacion === '2') {
                if (empty($datosVenta['cliente_id'])) {
                    throw new InvalidArgumentException("El cliente es obligatorio para ventas al crédito comercial.");
                }

                $condicionesCredito = $datosVenta['condiciones_credito'] ?? [];
                if (empty($condicionesCredito['condicion_credito_id'])) {
                    throw new InvalidArgumentException("Debe seleccionar las condiciones de crédito para la venta al crédito.");
                }
            }

            // 1. Crear el registro de Venta
            $venta = Venta::create([
                'cliente_id' => $datosVenta['cliente_id'] ?? null,
                'vendedor_id' => $datosVenta['vendedor_id'] ?? null,
                'tipo_documento' => $datosVenta['tipo_documento'], // 01 FCF, 03 CCF, 11 Ticket
                'codigo_generacion' => strtoupper((string) Str::uuid()),
                'numero_control' => 'DTE-'. $datosVenta['tipo_documento'] . '-' . str_pad(Venta::where('tipo_documento', $datosVenta['tipo_documento'])->count() + 1, 15, '0', STR_PAD_LEFT),
                'fecha_emision' => now()->toDateString(),
                'hora_emision' => now()->toTimeString(),
                'condicion_operacion' => $condicionOperacion, // 1 Contado, 2 Crédito
                'estado_dte' => 'pendiente',
            ]);

            $totales = [
                'gravado' => 0, 'exento' => 0, 'nosujeto' => 0, 'descuento' => 0
            ];

            // 2. Procesar Detalles y Rebajar Inventario
            $detallesParaInventario = [];
            $bodegaId = $datosVenta['bodega_id'] ?? 1;

            foreach ($detalles as $index => $det) {
                $montoGravado = round($det['cantidad'] * $det['precio_unitario'], 2);
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

            // Salida de inventario
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

            // 3. Si es venta al crédito → crear crédito comercial obligatoriamente dentro de la transacción
            if ($venta->condicion_operacion === '2') {
                $condicionesCredito = $datosVenta['condiciones_credito'] ?? [];
                // Se ejecuta sin atrapar excepción para que cualquier fallo revierta venta e inventario
                $this->creditoService->crearCreditoDesdeVenta($venta, $condicionesCredito);
            }

            // Las ventas y créditos funcionan sin servicios fiscales en el proyecto académico.
            if (!config('erp.dte.enabled') || $venta->tipo_documento === '11') {
                $venta->update([
                    'estado_dte' => 'local',
                    'sello_recepcion' => null,
                    'json_firmado' => null,
                    'mensaje_mh' => 'Documento local. No transmitido a Hacienda.',
                ]);
            } elseif (in_array($venta->tipo_documento, ['01', '03', '05', '06'])) {
                $this->firmarYTransmitir($venta);
            }

            return $venta;
        });
    }

    private function firmarYTransmitir(Venta $venta)
    {
        try {
            $jsonEstructural = [
                'identificacion' => [
                    'version' => 1,
                    'ambiente' => '00',
                    'tipoDte' => $venta->tipo_documento,
                    'numeroControl' => $venta->numero_control,
                    'codigoGeneracion' => $venta->codigo_generacion,
                ],
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

        } catch (DteConfigurationException $e) {
            throw $e;
        } catch (Exception $e) {
            // ENTRAR EN CONTINGENCIA SI FALLA LA CONEXIÓN O EL FIRMADOR
            $venta->estado_dte = 'contingencia';
            $venta->mensaje_mh = $e->getMessage();
            
            $lote = LoteContingencia::firstOrCreate(
                ['estado' => 'pendiente'],
                ['codigo_lote' => strtoupper((string) Str::uuid()), 'fecha_transmision' => now()->toDateString(), 'hora_transmision' => now()->toTimeString()]
            );

            $venta->lote_contingencia_id = $lote->id;
            
            TransmitirDteContingencia::dispatch($venta->id)->delay(now()->addMinutes(5));
        }

        $venta->save();
    }
}

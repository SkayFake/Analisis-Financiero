<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Cliente;
use App\Models\CondicionCredito;
use App\Models\Venta;
use App\Models\Producto;
use App\Models\Bodega;
use App\Models\Existencia;
use App\Models\Credito;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoCuota;
use App\Services\CreditoService;
use App\Services\FacturacionService;
use App\Services\FirmadorService;
use App\Services\DteApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Carbon\Carbon;
use Mockery;

class CreditoComercialFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $agentUser;
    protected User $unauthorizedUser;
    protected Cliente $cliente;
    protected CondicionCredito $condicionSinInteres;
    protected CondicionCredito $condicionConInteres;
    protected Bodega $bodega;
    protected Producto $producto;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos
        $permisos = [
            'creditos.view', 'creditos.create', 'creditos.approve', 'creditos.manage',
            'cobros.view', 'cobros.process', 'cobros.manage',
            'facturacion.view', 'facturacion.emitir',
        ];
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // Crear usuario admin con permisos totales
        $this->adminUser = User::factory()->create(['name' => 'Admin Test', 'email' => 'admin@test.com']);
        $this->adminUser->givePermissionTo($permisos);

        // Agente de cobros (solo procesar pagos y ver créditos)
        $this->agentUser = User::factory()->create(['name' => 'Agent Test', 'email' => 'agent@test.com']);
        $this->agentUser->givePermissionTo(['creditos.view', 'cobros.process']);

        // Usuario sin permisos
        $this->unauthorizedUser = User::factory()->create(['name' => 'No Perm Test', 'email' => 'noperm@test.com']);

        // Crear Cliente habilitado con DUI
        $this->cliente = Cliente::create([
            'codigo' => 'CLI-001',
            'tipo' => 'natural',
            'nombre' => 'Juan Perez',
            'dui' => '01234567-8',
            'activo' => true,
        ]);

        // Crear Condiciones de Crédito
        $this->condicionSinInteres = CondicionCredito::create([
            'nombre' => '30 días sin interés',
            'activa' => true,
            'tasa_interes_anual' => 0.0000,
            'tasa_mora_mensual' => 3.0000,
            'dias_gracia' => 3,
            'frecuencia_pago' => 'mensual',
            'plazo_maximo_cuotas' => 12,
            'monto_auto_aprobado' => 500.00,
            'compras_minimas_cliente_frecuente' => 2,
            'requiere_fiador' => false,
            'requiere_dui' => true,
        ]);

        $this->condicionConInteres = CondicionCredito::create([
            'nombre' => 'Crédito comercial con interés 12%',
            'activa' => true,
            'tasa_interes_anual' => 12.0000,
            'tasa_mora_mensual' => 3.0000,
            'dias_gracia' => 3,
            'frecuencia_pago' => 'mensual',
            'plazo_maximo_cuotas' => 6,
            'monto_auto_aprobado' => 300.00,
            'compras_minimas_cliente_frecuente' => 2,
            'requiere_fiador' => false,
            'requiere_dui' => true,
        ]);

        // Crear Bodega y Producto con existencia
        $this->bodega = Bodega::create([
            'nombre' => 'Bodega Central',
            'activa' => true,
        ]);

        // Crear Unidad de Medida y Categoría para Producto
        $unidad = \App\Models\UnidadMedida::create([
            'nombre' => 'Unidad',
            'abreviatura' => 'UND',
        ]);

        $categoria = \App\Models\Categoria::create([
            'nombre' => 'Construcción',
        ]);

        $this->producto = Producto::create([
            'codigo' => 'PROD-001',
            'nombre' => 'Bolsa de Cemento',
            'unidad_medida_id' => $unidad->id,
            'categoria_id' => $categoria->id,
            'precio_venta' => 10.00,
            'precio_costo' => 7.00,
            'stock_minimo' => 5,
            'activo' => true,
        ]);

        Existencia::create([
            'bodega_id' => $this->bodega->id,
            'producto_id' => $this->producto->id,
            'cantidad' => 100,
        ]);

        // Mock para DTE firmador y API para no llamar servicios externos
        $mockFirmador = Mockery::mock(FirmadorService::class);
        $mockFirmador->shouldReceive('firmarDocumento')->andReturn('{"firmado": true}');
        $this->app->instance(FirmadorService::class, $mockFirmador);

        $mockDteApi = Mockery::mock(DteApiService::class);
        $mockDteApi->shouldReceive('transmitirDTE')->andReturn(['estado' => 'PROCESADO', 'selloRecibido' => 'SELLO123']);
        $this->app->instance(DteApiService::class, $mockDteApi);
    }

    /** 1. Creación de una venta al crédito con su crédito asociado */
    public function test_venta_al_credito_crea_credito_asociado_correctamente(): void
    {
        $facturacionService = app(FacturacionService::class);

        $datosVenta = [
            'cliente_id' => $this->cliente->id,
            'tipo_documento' => '01',
            'condicion_operacion' => '2', // Crédito
            'bodega_id' => $this->bodega->id,
            'condiciones_credito' => [
                'condicion_credito_id' => $this->condicionSinInteres->id,
                'numero_cuotas' => 2,
                'fecha_primera_cuota' => now()->addMonth()->toDateString(),
                'dui_verificado' => true,
                'referencia_verificada' => true,
            ],
        ];

        $detalles = [
            [
                'producto_id' => $this->producto->id,
                'cantidad' => 5,
                'precio_unitario' => 10.00,
            ]
        ];

        $venta = $facturacionService->procesarVenta($datosVenta, $detalles, $this->adminUser->id);

        $this->assertNotNull($venta->id);
        $this->assertTrue($venta->tiene_credito);

        $credito = $venta->credito;
        $this->assertEquals(50.00, (float) $credito->monto_original);
        $this->assertEquals(50.00, (float) $credito->saldo_actual);
        $this->assertEquals(2, $credito->numero_cuotas);

        // Al ser cliente nuevo (0 compras previas), queda pendiente_aprobacion
        $this->assertEquals('pendiente_aprobacion', $credito->estado);
    }

    /** 2. Reversión de operaciones locales cuando falla la creación del crédito */
    public function test_reversion_de_venta_e_inventario_cuando_falla_creacion_credito(): void
    {
        $facturacionService = app(FacturacionService::class);

        $stockInicial = Existencia::where('bodega_id', $this->bodega->id)
            ->where('producto_id', $this->producto->id)
            ->value('cantidad');

        $ventasIniciales = Venta::count();

        // Omitir condicion_credito_id para provocar el fallo
        $datosVenta = [
            'cliente_id' => $this->cliente->id,
            'tipo_documento' => '01',
            'condicion_operacion' => '2',
            'bodega_id' => $this->bodega->id,
            'condiciones_credito' => [
                'condicion_credito_id' => null, // Inválido
                'numero_cuotas' => 2,
            ],
        ];

        $detalles = [
            [
                'producto_id' => $this->producto->id,
                'cantidad' => 5,
                'precio_unitario' => 10.00,
            ]
        ];

        try {
            $facturacionService->procesarVenta($datosVenta, $detalles, $this->adminUser->id);
            $this->fail('Se esperaba excepción por falta de condicion_credito_id');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('condiciones de crédito', $e->getMessage());
        }

        // Verificar que NO se persistió venta ni se descontó inventario
        $this->assertEquals($ventasIniciales, Venta::count());
        $stockActual = Existencia::where('bodega_id', $this->bodega->id)
            ->where('producto_id', $this->producto->id)
            ->value('cantidad');
        $this->assertEquals($stockInicial, $stockActual);
    }

    /** 3. Creación manual desde factura elegible y rechazo de facturas al contado o ya vinculadas */
    public function test_creacion_manual_y_rechazo_de_facturas_al_contado_o_ya_vinculadas(): void
    {
        $creditoService = app(CreditoService::class);

        // Venta al contado
        $ventaContado = Venta::create([
            'cliente_id' => $this->cliente->id,
            'tipo_documento' => '01',
            'codigo_generacion' => (string) \Illuminate\Support\Str::uuid(),
            'numero_control' => 'DTE-01-000000000000001',
            'fecha_emision' => now()->toDateString(),
            'hora_emision' => now()->toTimeString(),
            'condicion_operacion' => '1', // Contado
            'total_pagar' => 100.00,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Solo las ventas al crédito');
        $creditoService->crearCreditoDesdeVenta($ventaContado, [
            'condicion_credito_id' => $this->condicionSinInteres->id,
            'numero_cuotas' => 1,
        ]);
    }

    public function test_rechazo_de_facturas_que_ya_tienen_credito_asociado(): void
    {
        $creditoService = app(CreditoService::class);

        $ventaCredito = Venta::create([
            'cliente_id' => $this->cliente->id,
            'tipo_documento' => '01',
            'codigo_generacion' => (string) \Illuminate\Support\Str::uuid(),
            'numero_control' => 'DTE-01-000000000000002',
            'fecha_emision' => now()->toDateString(),
            'hora_emision' => now()->toTimeString(),
            'condicion_operacion' => '2',
            'total_pagar' => 100.00,
        ]);

        $credito = $creditoService->crearCreditoDesdeVenta($ventaCredito, [
            'condicion_credito_id' => $this->condicionSinInteres->id,
            'numero_cuotas' => 1,
            'fecha_primera_cuota' => now()->addMonth()->toDateString(),
            'dui_verificado' => true,
        ]);
        $this->assertNotNull($credito);

        // Intentar crear segundo crédito sobre la misma factura
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ya tiene un crédito asociado');
        $creditoService->crearCreditoDesdeVenta($ventaCredito, [
            'condicion_credito_id' => $this->condicionSinInteres->id,
            'numero_cuotas' => 1,
        ]);
    }

    /** 4. Aprobación automática y manual, y rechazo */
    public function test_aprobacion_manual_genera_cuotas_y_rechazo_marca_estado(): void
    {
        $creditoService = app(CreditoService::class);

        $venta = Venta::create([
            'cliente_id' => $this->cliente->id,
            'tipo_documento' => '01',
            'codigo_generacion' => (string) \Illuminate\Support\Str::uuid(),
            'numero_control' => 'DTE-01-000000000000003',
            'fecha_emision' => now()->toDateString(),
            'hora_emision' => now()->toTimeString(),
            'condicion_operacion' => '2',
            'total_pagar' => 100.00,
        ]);

        $credito = $creditoService->crearCreditoDesdeVenta($venta, [
            'condicion_credito_id' => $this->condicionSinInteres->id,
            'numero_cuotas' => 2,
            'fecha_primera_cuota' => now()->addMonth()->toDateString(),
            'dui_verificado' => true,
        ]);

        $this->assertEquals('pendiente_aprobacion', $credito->estado);
        $this->assertEquals(0, $credito->cuotas()->count());

        // Aprobar manualmente
        $creditoAprobado = $creditoService->aprobarCredito($credito, 'Aprobación autorizada por Gerencia', $this->adminUser->id);
        $this->assertEquals('vigente', $creditoAprobado->estado);
        $this->assertEquals($this->adminUser->id, $creditoAprobado->aprobado_por);
        $this->assertEquals(2, $creditoAprobado->cuotas()->count());

        // Intentar volver a aprobar un crédito ya vigente debe fallar
        $this->expectException(\InvalidArgumentException::class);
        $creditoService->aprobarCredito($creditoAprobado, 'Intento redundante');
    }

    public function test_rechazar_credito_pendiente(): void
    {
        $creditoService = app(CreditoService::class);

        $venta = Venta::create([
            'cliente_id' => $this->cliente->id,
            'tipo_documento' => '01',
            'codigo_generacion' => (string) \Illuminate\Support\Str::uuid(),
            'numero_control' => 'DTE-01-000000000000004',
            'fecha_emision' => now()->toDateString(),
            'hora_emision' => now()->toTimeString(),
            'condicion_operacion' => '2',
            'total_pagar' => 100.00,
        ]);

        $credito = $creditoService->crearCreditoDesdeVenta($venta, [
            'condicion_credito_id' => $this->condicionSinInteres->id,
            'numero_cuotas' => 2,
            'fecha_primera_cuota' => now()->addMonth()->toDateString(),
            'dui_verificado' => true,
        ]);

        $creditoRechazado = $creditoService->rechazarCredito($credito, 'Capacidad crediticia insuficiente', $this->adminUser->id);
        $this->assertEquals('rechazado', $creditoRechazado->estado);
        $this->assertEquals('Capacidad crediticia insuficiente', $creditoRechazado->motivo_aprobacion);
    }

    /** 5. Permisos y autorización: 403 y ausencia de cambios */
    public function test_usuario_sin_permisos_recibe_403(): void
    {
        $venta = Venta::create([
            'cliente_id' => $this->cliente->id,
            'tipo_documento' => '01',
            'codigo_generacion' => (string) \Illuminate\Support\Str::uuid(),
            'numero_control' => 'DTE-01-000000000000005',
            'fecha_emision' => now()->toDateString(),
            'hora_emision' => now()->toTimeString(),
            'condicion_operacion' => '2',
            'total_pagar' => 100.00,
        ]);

        $credito = Credito::create([
            'numero' => Credito::generarNumero(),
            'cliente_id' => $this->cliente->id,
            'venta_id' => $venta->id,
            'condicion_credito_id' => $this->condicionSinInteres->id,
            'monto_original' => 100.00,
            'saldo_actual' => 100.00,
            'numero_cuotas' => 1,
            'frecuencia_pago' => 'mensual',
            'plazo_dias' => 30,
            'fecha_solicitud' => now(),
            'fecha_primera_cuota' => now()->addMonth(),
            'fecha_vencimiento' => now()->addMonth(),
            'estado' => 'pendiente_aprobacion',
        ]);

        // Intentar ver lista
        $response = $this->actingAs($this->unauthorizedUser)->get(route('creditos.index'));
        $response->assertStatus(403);

        // Intentar aprobar
        $respAprobar = $this->actingAs($this->unauthorizedUser)->post(route('creditos.aprobar', $credito), [
            'motivo' => 'Intento sin permiso de aprobación',
        ]);
        $respAprobar->assertStatus(403);
        $this->assertEquals('pendiente_aprobacion', $credito->fresh()->estado);

        // Intentar pagar con usuario sin permisos de cobro
        $respPago = $this->actingAs($this->unauthorizedUser)->post(route('creditos.pagos.store', $credito), [
            'monto' => 50.00,
            'forma_pago' => 'efectivo',
        ]);
        $respPago->assertStatus(403);
        $this->assertEquals(100.00, (float) $credito->fresh()->saldo_actual);
    }

    /** 6. Condiciones inactivas y límites de cuotas */
    public function test_condicion_inactiva_y_exceso_de_cuotas_son_rechazadas(): void
    {
        $creditoService = app(CreditoService::class);

        $condicionInactiva = CondicionCredito::create([
            'nombre' => 'Condición Descontinuada',
            'activa' => false,
            'tasa_interes_anual' => 0.0000,
            'tasa_mora_mensual' => 3.0000,
            'dias_gracia' => 3,
            'frecuencia_pago' => 'mensual',
            'plazo_maximo_cuotas' => 6,
            'monto_auto_aprobado' => 500.00,
            'compras_minimas_cliente_frecuente' => 2,
        ]);

        $venta = Venta::create([
            'cliente_id' => $this->cliente->id,
            'tipo_documento' => '01',
            'codigo_generacion' => (string) \Illuminate\Support\Str::uuid(),
            'numero_control' => 'DTE-01-000000000000006',
            'fecha_emision' => now()->toDateString(),
            'hora_emision' => now()->toTimeString(),
            'condicion_operacion' => '2',
            'total_pagar' => 100.00,
        ]);

        // Rechazo por inactiva
        try {
            $creditoService->crearCreditoDesdeVenta($venta, [
                'condicion_credito_id' => $condicionInactiva->id,
                'numero_cuotas' => 2,
            ]);
            $this->fail('Se esperaba rechazo por condición inactiva');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('inactiva', $e->getMessage());
        }

        // Rechazo por exceder plazo_maximo_cuotas (máximo 6 en condicionConInteres)
        try {
            $creditoService->crearCreditoDesdeVenta($venta, [
                'condicion_credito_id' => $this->condicionConInteres->id,
                'numero_cuotas' => 10,
                'fecha_primera_cuota' => now()->addMonth()->toDateString(),
            ]);
            $this->fail('Se esperaba rechazo por exceso de cuotas');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('supera el límite', $e->getMessage());
        }
    }

    /** 7. Documentación y fiador requeridos */
    public function test_documentacion_y_fiador_exigidos_para_aprobar(): void
    {
        $creditoService = app(CreditoService::class);

        $condicionConFiador = CondicionCredito::create([
            'nombre' => 'Crédito con fiador obligatorio',
            'activa' => true,
            'tasa_interes_anual' => 0.0000,
            'tasa_mora_mensual' => 3.0000,
            'dias_gracia' => 3,
            'frecuencia_pago' => 'mensual',
            'plazo_maximo_cuotas' => 6,
            'monto_auto_aprobado' => 0.00,
            'requiere_fiador' => true,
            'requiere_dui' => true,
        ]);

        $venta = Venta::create([
            'cliente_id' => $this->cliente->id,
            'tipo_documento' => '01',
            'codigo_generacion' => (string) \Illuminate\Support\Str::uuid(),
            'numero_control' => 'DTE-01-000000000000007',
            'fecha_emision' => now()->toDateString(),
            'hora_emision' => now()->toTimeString(),
            'condicion_operacion' => '2',
            'total_pagar' => 200.00,
        ]);

        // Guardar solicitud pendiente sin fiador
        $credito = $creditoService->crearCreditoDesdeVenta($venta, [
            'condicion_credito_id' => $condicionConFiador->id,
            'numero_cuotas' => 2,
            'fecha_primera_cuota' => now()->addMonth()->toDateString(),
            'dui_verificado' => true,
        ]);

        $this->assertEquals('pendiente_aprobacion', $credito->estado);
        $faltantes = $creditoService->obtenerRequisitosFaltantes($credito);
        $this->assertNotEmpty($faltantes);
        $this->assertStringContainsString('fiador', implode(' ', $faltantes));

        // Intentar aprobar sin fiador debe fallar
        try {
            $creditoService->aprobarCredito($credito, 'Aprobación sin fiador', $this->adminUser->id);
            $this->fail('Se esperaba rechazo por falta de fiador');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('requisitos', $e->getMessage());
        }

        // Agregar fiador y volver a probar aprobación
        $credito->fiadores()->create([
            'nombre' => 'Pedro Fiador',
            'dui' => '98765432-1',
            'telefono' => '7777-8888',
        ]);

        $creditoAprobado = $creditoService->aprobarCredito($credito, 'Fiador registrado y comprobado', $this->adminUser->id);
        $this->assertEquals('vigente', $creditoAprobado->estado);
    }

    /** 8. Pagos parciales sin cobrar dos veces interés o mora */
    public function test_pagos_parciales_sin_doble_cobro_de_interes_y_conciliacion_exacta(): void
    {
        $creditoService = app(CreditoService::class);

        // Caso reproducido:
        // Capital: $100.
        // Una cuota con interés de $1, total $101.
        // Pagos de $50 y $51.
        // Resultado esperado: cuota pagada y crédito con saldo de $0.
        $credito = Credito::create([
            'numero' => Credito::generarNumero(),
            'cliente_id' => $this->cliente->id,
            'condicion_credito_id' => $this->condicionConInteres->id,
            'monto_original' => 100.00,
            'saldo_actual' => 100.00,
            'numero_cuotas' => 1,
            'frecuencia_pago' => 'mensual',
            'plazo_dias' => 30,
            'fecha_solicitud' => now(),
            'fecha_aprobacion' => now(),
            'fecha_primera_cuota' => now()->addMonth(),
            'fecha_vencimiento' => now()->addMonth(),
            'estado' => 'vigente',
        ]);

        Cuota::create([
            'credito_id' => $credito->id,
            'numero_cuota' => 1,
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
            'capital' => 100.00,
            'interes' => 1.00,
            'total' => 101.00,
            'mora' => 0.00,
            'saldo_pendiente' => 0.00,
            'monto_pagado' => 0.00,
            'estado' => 'pendiente',
        ]);

        // Primer pago: $50
        $pago1 = $creditoService->procesarPago($credito, [
            'monto' => 50.00,
            'forma_pago' => 'efectivo',
        ]);

        // Pago 1: $1 va a interés, $49 a capital.
        $this->assertEquals(1.00, (float) $pago1->pago_interes);
        $this->assertEquals(49.00, (float) $pago1->abono_capital);
        $this->assertEquals(51.00, (float) $credito->fresh()->saldo_actual);

        // Segundo pago: $51
        $pago2 = $creditoService->procesarPago($credito->fresh(), [
            'monto' => 51.00,
            'forma_pago' => 'efectivo',
        ]);

        // Pago 2: el interés de la cuota ya fue cubierto ($1.00 pagado), por lo tanto 0 a interés y $51 a capital!
        $this->assertEquals(0.00, (float) $pago2->pago_interes);
        $this->assertEquals(51.00, (float) $pago2->abono_capital);

        $creditoFinal = $credito->fresh();
        $cuotaFinal = Cuota::where('credito_id', $credito->id)->first();

        // Conciliación exacta
        $this->assertEquals(0.00, (float) $creditoFinal->saldo_actual);
        $this->assertEquals('cancelado', $creditoFinal->estado);
        $this->assertEquals('pagada', $cuotaFinal->estado);
        $this->assertEquals(101.00, (float) $cuotaFinal->monto_pagado);

        // Suma de abonos de capital = monto original
        $totalCapitalPagado = Pago::where('credito_id', $credito->id)->sum('abono_capital');
        $this->assertEquals(100.00, (float) $totalCapitalPagado);

        // Suma de intereses pagados = 1.00 (sin duplicar)
        $totalInteresPagado = Pago::where('credito_id', $credito->id)->sum('pago_interes');
        $this->assertEquals(1.00, (float) $totalInteresPagado);
    }

    /** 9. Distribución de un pago entre varias cuotas en orden de vencimiento */
    public function test_distribucion_de_un_pago_entre_varias_cuotas(): void
    {
        $creditoService = app(CreditoService::class);

        // 2 cuotas de $100 sin interés (total $200). Pago de $150.
        $credito = Credito::create([
            'numero' => Credito::generarNumero(),
            'cliente_id' => $this->cliente->id,
            'condicion_credito_id' => $this->condicionSinInteres->id,
            'monto_original' => 200.00,
            'saldo_actual' => 200.00,
            'numero_cuotas' => 2,
            'frecuencia_pago' => 'mensual',
            'plazo_dias' => 60,
            'fecha_solicitud' => now(),
            'fecha_aprobacion' => now(),
            'fecha_primera_cuota' => now()->addMonth(),
            'fecha_vencimiento' => now()->addMonths(2),
            'estado' => 'vigente',
        ]);

        $cuota1 = Cuota::create([
            'credito_id' => $credito->id,
            'numero_cuota' => 1,
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
            'capital' => 100.00,
            'interes' => 0.00,
            'total' => 100.00,
            'mora' => 0.00,
            'saldo_pendiente' => 100.00,
            'monto_pagado' => 0.00,
            'estado' => 'pendiente',
        ]);

        $cuota2 = Cuota::create([
            'credito_id' => $credito->id,
            'numero_cuota' => 2,
            'fecha_vencimiento' => now()->addMonths(2)->toDateString(),
            'capital' => 100.00,
            'interes' => 0.00,
            'total' => 100.00,
            'mora' => 0.00,
            'saldo_pendiente' => 0.00,
            'monto_pagado' => 0.00,
            'estado' => 'pendiente',
        ]);

        // Pago de $150
        $pago = $creditoService->procesarPago($credito, [
            'monto' => 150.00,
            'forma_pago' => 'transferencia',
        ]);

        $this->assertEquals(150.00, (float) $pago->monto_total);
        $this->assertEquals(50.00, (float) $credito->fresh()->saldo_actual);

        // Cuota 1 debe estar pagada con $100
        $this->assertEquals('pagada', $cuota1->fresh()->estado);
        $this->assertEquals(100.00, (float) $cuota1->fresh()->monto_pagado);

        // Cuota 2 debe estar parcial con $50
        $this->assertEquals('parcial', $cuota2->fresh()->estado);
        $this->assertEquals(50.00, (float) $cuota2->fresh()->monto_pagado);

        // Verificar registros en pago_cuotas
        $aplicaciones = PagoCuota::where('pago_id', $pago->id)->get();
        $this->assertCount(2, $aplicaciones);

        $apl1 = $aplicaciones->firstWhere('cuota_id', $cuota1->id);
        $apl2 = $aplicaciones->firstWhere('cuota_id', $cuota2->id);

        $this->assertEquals(100.00, (float) $apl1->monto_total);
        $this->assertEquals(100.00, (float) $apl1->abono_capital);

        $this->assertEquals(50.00, (float) $apl2->monto_total);
        $this->assertEquals(50.00, (float) $apl2->abono_capital);
    }

    /** 10. Rechazo de sobrepago e importes mayores a la deuda exigible */
    public function test_rechazo_de_sobrepago(): void
    {
        $creditoService = app(CreditoService::class);

        $credito = Credito::create([
            'numero' => Credito::generarNumero(),
            'cliente_id' => $this->cliente->id,
            'condicion_credito_id' => $this->condicionSinInteres->id,
            'monto_original' => 100.00,
            'saldo_actual' => 100.00,
            'numero_cuotas' => 1,
            'frecuencia_pago' => 'mensual',
            'plazo_dias' => 30,
            'fecha_solicitud' => now(),
            'fecha_aprobacion' => now(),
            'fecha_primera_cuota' => now()->addMonth(),
            'fecha_vencimiento' => now()->addMonth(),
            'estado' => 'vigente',
        ]);

        Cuota::create([
            'credito_id' => $credito->id,
            'numero_cuota' => 1,
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
            'capital' => 100.00,
            'interes' => 0.00,
            'total' => 100.00,
            'mora' => 0.00,
            'saldo_pendiente' => 0.00,
            'monto_pagado' => 0.00,
            'estado' => 'pendiente',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('excede la deuda exigible');
        $creditoService->procesarPago($credito, [
            'monto' => 105.00, // Deuda es 100
            'forma_pago' => 'efectivo',
        ]);
    }

    /** 11. Fechas de vencimiento y frecuencias: semanal, quincenal, fin de mes y bisiesto */
    public function test_fechas_de_vencimiento_y_frecuencias(): void
    {
        $creditoService = app(CreditoService::class);

        // 1 cuota con vencimiento el 1 de diciembre
        // El crédito debe vencer el 1 de diciembre (no un período después!)
        $fecha1 = Carbon::parse('2026-12-01');
        $vencimiento1 = $creditoService->calcularFechaVencimiento($fecha1, 1, 'mensual', baseIsFirst: true);
        $this->assertEquals('2026-12-01', $vencimiento1->toDateString());

        // Frecuencia semanal: 4 cuotas partiendo el lunes 2026-10-05
        $baseSemanal = Carbon::parse('2026-10-05');
        $vencCuota4 = $creditoService->calcularFechaVencimiento($baseSemanal, 4, 'semanal', baseIsFirst: true);
        $this->assertEquals('2026-10-26', $vencCuota4->toDateString()); // 3 semanas después

        // Frecuencia quincenal: 3 cuotas partiendo el 2026-10-01 (intervalos de 15 días)
        $baseQuincenal = Carbon::parse('2026-10-01');
        $vencQuincenal3 = $creditoService->calcularFechaVencimiento($baseQuincenal, 3, 'quincenal', baseIsFirst: true);
        $this->assertEquals('2026-10-31', $vencQuincenal3->toDateString()); // 30 días después

        // Fin de mes y año bisiesto:
        // Caso año bisiesto: 2024 (febrero tiene 29 días).
        $baseFinEneroBisiesto = Carbon::parse('2024-01-31');
        $vencFebBisiesto = $creditoService->calcularFechaVencimiento($baseFinEneroBisiesto, 2, 'mensual', baseIsFirst: true);
        $this->assertEquals('2024-02-29', $vencFebBisiesto->toDateString());

        // Cuota 3 (marzo) a partir de fin de enero: debe quedar 31 de marzo
        $vencMarzoBisiesto = $creditoService->calcularFechaVencimiento($baseFinEneroBisiesto, 3, 'mensual', baseIsFirst: true);
        $this->assertEquals('2024-03-31', $vencMarzoBisiesto->toDateString());

        // Cuota 4 (abril): debe quedar 30 de abril
        $vencAbrilBisiesto = $creditoService->calcularFechaVencimiento($baseFinEneroBisiesto, 4, 'mensual', baseIsFirst: true);
        $this->assertEquals('2024-04-30', $vencAbrilBisiesto->toDateString());

        // Caso no bisiesto: 2025-01-31 -> 2025-02-28
        $baseFinEneroNoBisiesto = Carbon::parse('2025-01-31');
        $vencFebNoBisiesto = $creditoService->calcularFechaVencimiento($baseFinEneroNoBisiesto, 2, 'mensual', baseIsFirst: true);
        $this->assertEquals('2025-02-28', $vencFebNoBisiesto->toDateString());
    }

    /** 12. Rechazo de pagos en estados inválidos (cancelado, rechazado, pendiente_aprobacion) */
    public function test_rechazo_de_pagos_en_estados_invalidos(): void
    {
        $creditoService = app(CreditoService::class);

        $creditoPendiente = Credito::create([
            'numero' => Credito::generarNumero(),
            'cliente_id' => $this->cliente->id,
            'condicion_credito_id' => $this->condicionSinInteres->id,
            'monto_original' => 100.00,
            'saldo_actual' => 100.00,
            'numero_cuotas' => 1,
            'frecuencia_pago' => 'mensual',
            'plazo_dias' => 30,
            'fecha_solicitud' => now(),
            'fecha_primera_cuota' => now()->addMonth(),
            'fecha_vencimiento' => now()->addMonth(),
            'estado' => 'pendiente_aprobacion',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("No se pueden registrar pagos en un crédito con estado 'pendiente_aprobacion'");
        $creditoService->procesarPago($creditoPendiente, [
            'monto' => 50.00,
            'forma_pago' => 'efectivo',
        ]);
    }
}

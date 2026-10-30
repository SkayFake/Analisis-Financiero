<?php

namespace Tests\Feature;

use App\Livewire\PuntoVenta;
use App\Livewire\Vendedores\VendedoresDashboard;
use App\Models\Bodega;
use App\Models\Cartera;
use App\Models\Cliente;
use App\Models\CondicionCredito;
use App\Models\Credito;
use App\Models\Existencia;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Vendedor;
use App\Models\VendedorAuditoria;
use App\Models\VendedorMeta;
use App\Models\Venta;
use App\Services\CreditoService;
use App\Services\FacturacionService;
use App\Services\IncobrableService;
use App\Services\VendedorMetricasService;
use App\Services\VendedorService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ControlVendedoresTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Vendedor $a;

    private Vendedor $b;

    private Cliente $cliente;

    private Bodega $bodega;

    private Producto $producto;

    private CondicionCredito $condicion;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Http::preventStrayRequests();
        Queue::fake();
        config(['erp.dte.enabled' => false]);
        $this->admin = $this->usuario(['vendedores.view', 'vendedores.manage', 'vendedores.metas', 'vendedores.assign', 'vendedores.gestionar', 'vendedores.export', 'vendedores.audit', 'facturacion.emitir', 'facturacion.view', 'facturacion.modificar_precio', 'creditos.create', 'creditos.view', 'cobros.process']);
        $this->a = Vendedor::create(['codigo' => 'VEN-A', 'nombre' => 'Vendedor A', 'meta_mensual' => 100, 'activo' => true]);
        $this->b = Vendedor::create(['codigo' => 'VEN-B', 'nombre' => 'Vendedor B', 'activo' => true]);
        $this->cliente = Cliente::create(['codigo' => 'CLI-VD', 'nombre' => 'Cliente vendedor', 'tipo' => 'natural', 'activo' => true, 'vendedor_id' => $this->a->id]);
        $unidad = UnidadMedida::create(['nombre' => 'Unidad', 'abreviatura' => 'UND']);
        $this->bodega = Bodega::create(['nombre' => 'Bodega', 'activa' => true]);
        $this->producto = Producto::create(['codigo' => 'PROD-VD', 'nombre' => 'Producto vendedor', 'unidad_medida_id' => $unidad->id, 'precio_venta' => 100, 'costo_unitario' => 50, 'activo' => true]);
        Existencia::create(['producto_id' => $this->producto->id, 'bodega_id' => $this->bodega->id, 'cantidad' => 100]);
        $this->condicion = CondicionCredito::create(['nombre' => 'Sin interés', 'activa' => true, 'frecuencia_pago' => 'mensual', 'plazo_maximo_cuotas' => 12, 'tasa_interes_anual' => 0, 'monto_auto_aprobado' => 10000, 'compras_minimas_cliente_frecuente' => 0, 'requiere_dui' => false, 'requiere_fiador' => false]);
    }

    private function usuario(array $permisos): User
    {
        $usuario = User::factory()->create();
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
            $usuario->givePermissionTo($permiso);
        }

        return $usuario;
    }

    private function venta(bool $credito = false, float $monto = 300, string $tipo = '11'): Venta
    {
        return app(FacturacionService::class)->procesarVenta([
            'cliente_id' => $this->cliente->id, 'vendedor_id' => $this->a->id, 'bodega_id' => $this->bodega->id,
            'tipo_documento' => $tipo, 'condicion_operacion' => $credito ? '2' : '1',
            'condiciones_credito' => $credito ? ['condicion_credito_id' => $this->condicion->id, 'numero_cuotas' => 3, 'fecha_primera_cuota' => today()->addMonthNoOverflow()->toDateString()] : [],
        ], [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => $monto]], $this->admin->id);
    }

    private function pago(Credito $credito, float $monto, ?string $fecha = null): Pago
    {
        return app(CreditoService::class)->procesarPago($credito, ['monto' => $monto, 'recibido_por' => $this->admin->id, 'fecha_pago' => $fecha ?? today()->toDateString()]);
    }

    private function resumen(Vendedor $vendedor): array
    {
        return app(VendedorMetricasService::class)->resumenes(collect([$vendedor]), today()->startOfMonth())->get($vendedor->id);
    }

    public function test_ventas_cobros_y_metas_no_se_duplican_y_distinguen_el_iva(): void
    {
        app(VendedorService::class)->meta($this->a, now()->format('Y-m'), ['meta_ventas' => 100, 'meta_cobros' => 50], 'Metas comerciales del mes de prueba', $this->admin);
        $this->venta(false, 100, '01');
        $credito = $this->venta(true, 100, '03')->credito;
        $this->pago($credito, 10);
        $this->pago($credito, 20);
        $r = $this->resumen($this->a);
        $this->assertEquals(200, $r['ventas']);
        $this->assertEquals(213, $r['facturado']);
        $this->assertEquals(30, $r['cobros']);
        $this->assertEquals(200, $r['avance_ventas']);
        $this->assertEquals(60, $r['avance_cobros']);
        $this->assertSame(2, $r['documentos']);
        $this->assertSame(2, $r['recibos']);
        $productos = app(VendedorMetricasService::class)->productos($this->a->id, today()->startOfMonth());
        $this->assertCount(1, $productos);
        $this->assertEquals(2, $productos->first()->unidades);
        $this->assertEquals(200, $productos->first()->importe);
        Http::assertNothingSent();
    }

    public function test_excluye_solicitudes_documentos_sin_emision_y_notas_de_las_metas(): void
    {
        $this->venta(false, 100);
        foreach (['pendiente', 'rechazado', 'contingencia', 'invalidado'] as $estado) {
            $this->venta(false, 100)->update(['estado_dte' => $estado]);
        }
        $this->venta(false, 100, '05');
        $this->venta(false, 100, '06');
        $this->condicion->update(['monto_auto_aprobado' => 0]);
        $pendiente = $this->venta(true, 100)->credito;
        $rechazado = $this->venta(true, 100)->credito;
        app(CreditoService::class)->rechazarCredito($rechazado, 'Rechazado por capacidad insuficiente', $this->admin->id);
        $this->assertSame('pendiente_aprobacion', $pendiente->estado);
        $r = $this->resumen($this->a);
        $this->assertEquals(100, $r['ventas']);
        $this->assertSame(1, $r['documentos']);
    }

    public function test_reasignar_no_mueve_ventas_ni_cobros_anteriores_y_deja_evidencia_en_ambos_vendedores(): void
    {
        $venta = $this->venta(true);
        $credito = $venta->credito;
        $primero = $this->pago($credito, 30);
        $this->actingAs($this->admin)->post(route('creditos.responsable', $credito), ['vendedor_id' => $this->b->id, 'motivo' => 'Reasignación aprobada por reorganización de cartera.'])->assertSessionHasNoErrors();
        $segundo = $this->pago($credito, 20);
        $this->assertSame($this->a->id, $primero->fresh()->vendedor_id);
        $this->assertSame($this->b->id, $segundo->vendedor_id);
        $this->assertSame($this->a->id, $venta->fresh()->vendedor_id);
        $this->assertSame($this->a->id, $credito->fresh()->vendedor_origen_id);
        $this->assertSame($this->a->id, $this->cliente->fresh()->vendedor_id);
        $this->assertEquals(300, $this->resumen($this->a)['ventas']);
        $this->assertEquals(30, $this->resumen($this->a)['cobros']);
        $this->assertEquals(20, $this->resumen($this->b)['cobros']);
        $this->assertEquals(0, $this->resumen($this->a)['cartera_capital']);
        $this->assertEquals(250, $this->resumen($this->b)['cartera_capital']);
        $this->assertSame(2, VendedorAuditoria::where('accion', 'reasignacion_credito')->count());
        $this->assertEquals(30, $this->a->totalCobros(today()->startOfMonth(), today()->endOfMonth()));
    }

    public function test_cartera_vencida_se_deriva_de_cuotas_y_no_de_un_estado_o_dias_desactualizados(): void
    {
        $primero = Carbon::withTestNow(today()->subDays(45), fn () => $this->venta(true)->credito);
        $segundo = Carbon::withTestNow(today()->subDays(45), fn () => $this->venta(true)->credito);
        $this->pago($primero, 25);
        app(IncobrableService::class)->declararIncobrable($segundo, 'Sin recuperación después de gestiones documentadas', $this->admin->id);
        $this->assertSame(0, $primero->fresh()->dias_mora);
        $r = $this->resumen($this->a);
        $this->assertEquals(575, $r['cartera_capital']);
        $this->assertEquals(175, $r['vencido']);
        $this->assertEquals(300, $r['incobrable']);
        $this->assertSame(1, $r['clientes_morosos']);
        $this->assertSame(2, $r['creditos']);
    }

    public function test_metas_son_independientes_por_mes_y_registran_antes_despues_y_responsable(): void
    {
        $service = app(VendedorService::class);
        $anterior = today()->subMonthNoOverflow()->format('Y-m');
        $service->meta($this->a, $anterior, ['meta_ventas' => 200, 'meta_cobros' => 80], 'Meta documentada para el mes anterior', $this->admin);
        $service->meta($this->a, now()->format('Y-m'), ['meta_ventas' => 500, 'meta_cobros' => 100], 'Meta documentada para el mes actual', $this->admin);
        $service->meta($this->a, now()->format('Y-m'), ['meta_ventas' => 600, 'meta_cobros' => 150], 'Ajuste autorizado por ampliación de zona', $this->admin);
        $this->assertSame(2, VendedorMeta::count());
        $this->assertEquals(200, VendedorMeta::whereDate('periodo', $anterior.'-01')->first()->meta_ventas);
        $evento = VendedorAuditoria::where('accion', 'meta_mensual')->latest('id')->first();
        $this->assertEquals(500, $evento->antes['meta_ventas']);
        $this->assertEquals(600, $evento->despues['meta_ventas']);
        $this->assertSame($this->admin->id, $evento->usuario_id);
        $sinMeta = app(VendedorMetricasService::class)->resumenes(collect([$this->a]), today()->subMonthsNoOverflow(2)->startOfMonth())->first();
        $this->assertFalse($sinMeta['meta_definida']);
        $this->assertNull($sinMeta['avance_ventas']);
    }

    public function test_permisos_protegen_consulta_mutaciones_exportacion_y_bitacora(): void
    {
        $credito = $this->venta(true)->credito;
        $sinPermiso = $this->usuario([]);
        $this->actingAs($sinPermiso)->get('/vendedores')->assertForbidden();
        $this->get(route('vendedores.show', $this->a))->assertForbidden();
        $lector = $this->usuario(['vendedores.view']);
        $this->actingAs($lector)->get('/vendedores')->assertOk()->assertDontSee('Nuevo vendedor');
        $this->get(route('vendedores.show', $this->a))->assertOk()->assertDontSee('Bitácora de control')->assertDontSee('Guardar metas con historial');
        Livewire::actingAs($lector)->test(VendedoresDashboard::class)->call('create')->assertForbidden();
        $this->post(route('vendedores.metas', $this->a), ['periodo' => now()->format('Y-m'), 'meta_ventas' => 1, 'meta_cobros' => 1, 'motivo' => 'Intento de cambio no autorizado'])->assertForbidden();
        $this->post(route('creditos.responsable', $credito), ['vendedor_id' => $this->b->id, 'motivo' => 'Intento de cambio no autorizado'])->assertForbidden();
        $this->post(route('creditos.gestiones', $credito), ['medio' => 'telefono', 'resultado' => 'contactado', 'notas' => 'Intento de gestión sin autorización'])->assertForbidden();
        $this->get(route('vendedores.exportar'))->assertForbidden();
        $exportador = $this->usuario(['vendedores.view', 'vendedores.export']);
        $this->actingAs($exportador)->get(route('vendedores.reporte', [$this->a, 'tipo' => 'auditoria']))->assertForbidden();
    }

    public function test_desactivacion_con_motivo_conserva_movimientos_y_bloquea_nuevas_ventas(): void
    {
        $venta = $this->venta(true);
        Livewire::actingAs($this->admin)->test(VendedoresDashboard::class)->call('edit', $this->a->id)
            ->set('activo', false)->call('save')->assertHasErrors('motivo')
            ->set('motivo', 'Baja operativa por retiro del vendedor')->call('save')->assertHasNoErrors();
        $this->assertFalse($this->a->fresh()->activo);
        $this->assertSame($this->a->id, $venta->fresh()->vendedor_id);
        $this->pago($venta->credito, 10);
        $this->assertEquals(10, $this->resumen($this->a->fresh())['cobros']);
        try {
            $this->venta();
            $this->fail('Se aceptó vendedor inactivo.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('vendedor_id', $e->errors());
        }
        try {
            $this->a->delete();
            $this->fail('Se eliminó el vendedor.');
        } catch (LogicException) {
            $this->assertDatabaseHas('vendedores', ['id' => $this->a->id]);
        }
    }

    public function test_pos_propone_asesor_del_cliente_y_exige_un_vendedor_activo(): void
    {
        $pos = Livewire::actingAs($this->admin)->test(PuntoVenta::class)->set('cliente_id', $this->cliente->id)->assertSet('vendedor_id', $this->a->id)
            ->set('tipo_documento', '11')->set('bodega_id', $this->bodega->id)->set('detalles', [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => 100, 'nombre' => $this->producto->nombre, 'codigo' => $this->producto->codigo, 'stock' => 100]])
            ->set('vendedor_id', '')->call('facturar')->assertHasErrors('vendedor_id');
        $pos->set('vendedor_id', $this->b->id)->call('facturar')->assertHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('ventas', ['vendedor_id' => $this->b->id]);
        $this->assertDatabaseHas('vendedor_auditorias', ['vendedor_id' => $this->b->id, 'accion' => 'venta_registrada', 'usuario_id' => $this->admin->id]);
    }

    public function test_promesas_no_suman_deuda_no_cuentan_pagos_anteriores_y_se_comprueban_con_recibos(): void
    {
        $credito = $this->venta(true)->credito;
        $this->pago($credito, 20);
        $gestion = app(VendedorService::class)->registrarGestion($credito, ['medio' => 'telefono', 'resultado' => 'promesa_pago', 'notas' => 'Cliente se compromete a pagar cincuenta dólares.', 'fecha_compromiso' => today()->addDays(2)->toDateString(), 'monto_compromiso' => 50], $this->admin);
        $this->assertSame('Pendiente', $gestion->estado_compromiso);
        $this->assertSame('280.00', $credito->fresh()->saldo_actual);
        $this->pago($credito, 30);
        $this->assertSame('Pendiente', $gestion->estado_compromiso);
        $this->pago($credito, 20);
        $this->assertSame('Cumplido', $gestion->estado_compromiso);
        $this->assertSame('230.00', $credito->fresh()->saldo_actual);
        $this->expectException(LogicException::class);
        $gestion->update(['monto_compromiso' => 5]);
    }

    public function test_promesas_vencidas_y_pagadas_fuera_de_plazo_se_distinguen(): void
    {
        $credito = Carbon::withTestNow(today()->subDays(30), fn () => $this->venta(true)->credito);
        $gestion = Carbon::withTestNow(today()->subDays(5), fn () => app(VendedorService::class)->registrarGestion($credito, ['medio' => 'visita', 'resultado' => 'promesa_pago', 'notas' => 'Promesa vencida documentada en visita al cliente.', 'fecha_compromiso' => today()->addDays(2)->toDateString(), 'monto_compromiso' => 50], $this->admin));
        $this->assertSame('Incumplido', $gestion->estado_compromiso);
        $this->pago($credito, 50);
        $this->assertSame('Cumplido fuera de plazo', $gestion->estado_compromiso);
    }

    public function test_reasignacion_muestra_gestiones_previas_al_nuevo_responsable(): void
    {
        $credito = $this->venta(true)->credito;
        app(VendedorService::class)->registrarGestion($credito, ['medio' => 'telefono', 'resultado' => 'contactado', 'notas' => 'Evidencia de contacto realizada antes del traslado.'], $this->admin);
        app(VendedorService::class)->reasignarCredito($credito, $this->b, null, 'Traslado autorizado para seguimiento de cobro', $this->admin);
        $this->actingAs($this->admin)->get(route('vendedores.show', $this->b))->assertOk()->assertSee('Evidencia de contacto realizada antes del traslado.');
    }

    public static function gestionesInvalidas(): array
    {
        return [
            [['medio' => 'redsocial']], [['notas' => 'corta']], [['fecha_compromiso' => '2020-01-01']],
            [['monto_compromiso' => -1]], [['monto_compromiso' => 999999]], [['monto_compromiso' => '1.234']],
        ];
    }

    #[DataProvider('gestionesInvalidas')]
    public function test_gestion_invalida_no_se_guarda_ni_modifica_saldos(array $invalido): void
    {
        $credito = $this->venta(true)->credito;
        $this->actingAs($this->admin)->post(route('creditos.gestiones', $credito), array_replace(['medio' => 'telefono', 'resultado' => 'promesa_pago', 'notas' => 'Promesa de pago documentada correctamente.', 'fecha_compromiso' => today()->addDay()->toDateString(), 'monto_compromiso' => 50], $invalido))->assertSessionHasErrors();
        $this->assertDatabaseCount('gestiones_cobro', 0);
        $this->assertSame('300.00', $credito->fresh()->saldo_actual);
    }

    public function test_reasignacion_rechaza_cartera_ajena_destino_inactivo_y_credito_cerrado(): void
    {
        $credito = $this->venta(true)->credito;
        $cartera = Cartera::create(['codigo' => 'CAR-A', 'nombre' => 'Cartera A', 'vendedor_id' => $this->a->id, 'activa' => true]);
        $this->actingAs($this->admin)->post(route('creditos.responsable', $credito), ['vendedor_id' => $this->b->id, 'cartera_id' => $cartera->id, 'motivo' => 'Intento de asignar una cartera de otro responsable'])->assertSessionHasErrors('cartera_id');
        $this->b->update(['activo' => false]);
        $this->post(route('creditos.responsable', $credito), ['vendedor_id' => $this->b->id, 'motivo' => 'Intento de asignación a persona inactiva'])->assertSessionHasErrors('vendedor_id');
        $this->pago($credito, 300);
        $this->post(route('creditos.responsable', $credito), ['vendedor_id' => $this->a->id, 'motivo' => 'No cambiar atribuciones de créditos cerrados'])->assertSessionHasErrors('vendedor_id');
        $this->assertSame(0, VendedorAuditoria::where('accion', 'reasignacion_credito')->count());
    }

    public function test_reasignacion_de_cliente_requiere_permiso_y_no_mueve_su_credito(): void
    {
        $credito = $this->venta(true)->credito;
        $datos = ['tipo' => 'natural', 'nombre' => $this->cliente->nombre, 'vendedor_id' => $this->b->id, 'motivo_asignacion' => 'Cambio de asesor autorizado para nuevas operaciones'];
        $this->actingAs($this->usuario(['vendedores.view']))->put(route('clientes.update', $this->cliente), $datos)->assertForbidden();
        $this->actingAs($this->admin)->put(route('clientes.update', $this->cliente), $datos)->assertSessionHasNoErrors();
        $this->assertSame($this->b->id, $this->cliente->fresh()->vendedor_id);
        $this->assertSame($this->a->id, $credito->fresh()->vendedor_id);
        $this->assertSame(2, VendedorAuditoria::where('accion', 'asignacion_cliente')->count());
    }

    public function test_exportaciones_csv_pdf_y_bitacora_conservan_contexto_y_evitan_formulas(): void
    {
        $credito = $this->venta(true)->credito;
        $this->pago($credito, 20);
        $this->a->update(['nombre' => '  =SUM(1,2)']);
        $this->actingAs($this->admin);
        $csv = $this->get(route('vendedores.exportar', ['mes' => now()->format('Y-m')]))->assertOk()->streamedContent();
        $this->assertStringContainsString("'  =SUM(1,2)", $csv);
        $this->assertStringContainsString('Cartera actual al', $csv);
        foreach (['ventas', 'cartera', 'cobros', 'auditoria'] as $tipo) {
            $this->get(route('vendedores.reporte', [$this->a, 'tipo' => $tipo]))->assertOk();
        }
        $pdf = $this->get(route('vendedores.reporte', [$this->a, 'formato' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $evento = VendedorAuditoria::where('accion', 'exportacion')->latest('id')->first();
        $this->assertSame($this->admin->id, $evento->usuario_id);
        $this->assertArrayHasKey('corte', $evento->despues);
        $this->assertArrayHasKey('resumen', $evento->despues);
    }

    public function test_bitacora_rechaza_cambios_incluso_con_sql_directo(): void
    {
        $this->venta();
        $evento = VendedorAuditoria::firstOrFail();
        try {
            $evento->update(['motivo' => 'Alteración']);
            $this->fail('Se permitió editar la bitácora.');
        } catch (LogicException) {
            $this->assertNotSame('Alteración', $evento->fresh()->motivo);
        }
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('vendedor_auditorias')->where('id', $evento->id)->delete());
    }

    public function test_regularizacion_se_limita_a_registros_historicos_y_conserva_los_importes(): void
    {
        $venta = $this->venta(true);
        $pago = $this->pago($venta->credito, 20);
        $this->actingAs($this->admin)->post(route('pagos.vendedor', $pago), ['vendedor_id' => $this->b->id, 'motivo' => 'No debe cambiar un recibo con atribución de origen'])->assertSessionHasErrors('pago');
        $venta->update(['vendedor_id' => null]);
        $pago->update(['vendedor_id' => null, 'vendedor_reconstruido' => true]);
        $this->get(route('vendedores.pendientes'))->assertOk()->assertSee($pago->numero_recibo);
        $this->post(route('ventas.vendedor', $venta), ['vendedor_id' => $this->b->id, 'motivo' => 'Vendedor documentado según comprobante original revisado'])->assertSessionHasNoErrors();
        $this->post(route('pagos.vendedor', $pago), ['vendedor_id' => $this->b->id, 'motivo' => 'Responsable documentado según recibo original revisado'])->assertSessionHasNoErrors();
        $this->assertSame($this->b->id, $pago->fresh()->vendedor_id);
        $this->assertSame('20.00', $pago->fresh()->monto_total);
        $this->assertSame('280.00', $pago->fresh()->saldo_despues);
        $this->assertTrue($pago->fresh()->vendedor_reconstruido);
        $this->assertNotNull($pago->fresh()->vendedor_revisado_at);
        $this->get(route('vendedores.pendientes'))->assertOk()->assertDontSee($pago->numero_recibo);
    }

    public function test_migracion_conserva_importes_y_marca_lo_que_solo_pudo_reconstruirse(): void
    {
        $this->cliente->update(['vendedor_id' => null]);
        $venta = Venta::create([
            'cliente_id' => $this->cliente->id, 'codigo_generacion' => (string) Str::uuid(),
            'numero_control' => 'LEGADO-VENDEDOR', 'fecha_emision' => today(), 'hora_emision' => '10:00:00',
            'tipo_documento' => '11', 'condicion_operacion' => '2', 'estado_dte' => 'local', 'total_pagar' => 100,
        ]);
        $credito = app(CreditoService::class)->crearCreditoDesdeVenta($venta, [
            'condicion_credito_id' => $this->condicion->id, 'numero_cuotas' => 3,
            'fecha_primera_cuota' => today()->addMonthNoOverflow()->toDateString(),
        ]);
        $pago = $this->pago($credito, 10);
        $campos = ['numero_recibo', 'monto_total', 'abono_capital', 'pago_interes', 'pago_mora', 'saldo_despues'];
        $antes = $pago->fresh()->only($campos);
        $venta->update(['vendedor_id' => $this->b->id]);
        $credito->update(['vendedor_id' => $this->a->id]);
        $migracion = require database_path('migrations/2026_10_30_010000_integrate_vendedores_workflow.php');
        $migracion->down();
        $migracion->up();
        $this->assertSame($antes, $pago->fresh()->only($campos));
        $this->assertSame($this->a->id, $pago->fresh()->vendedor_id);
        $this->assertTrue($pago->fresh()->vendedor_reconstruido);
        $this->assertSame($this->b->id, $credito->fresh()->vendedor_origen_id);
        $this->assertFalse($credito->fresh()->vendedor_origen_reconstruido);
        $this->assertSame('90.00', $credito->fresh()->saldo_actual);
        $this->assertSame(0, VendedorMeta::where('periodo', '<', today()->startOfMonth())->count());
        $this->assertDatabaseHas('vendedor_metas', ['vendedor_id' => $this->a->id, 'meta_ventas' => 100, 'meta_cobros' => 0]);
        $this->assertDatabaseHas('vendedor_auditorias', ['vendedor_id' => $this->a->id, 'accion' => 'importacion_meta', 'usuario_id' => null]);
    }

    public function test_un_recibo_ingresado_tarde_con_fecha_anterior_no_cumple_una_promesa_nueva(): void
    {
        $credito = Carbon::withTestNow(today()->subDays(20), fn () => $this->venta(true)->credito);
        $gestion = app(VendedorService::class)->registrarGestion($credito, [
            'medio' => 'telefono', 'resultado' => 'promesa_pago', 'notas' => 'Compromiso actual distinto a pagos antiguos.',
            'fecha_compromiso' => today()->addDays(2)->toDateString(), 'monto_compromiso' => 50,
        ], $this->admin);
        $this->pago($credito, 50, today()->subDays(10)->toDateString());
        $this->assertSame('Pendiente', $gestion->estado_compromiso);
        $this->pago($credito, 50);
        $this->assertSame('Cumplido', $gestion->estado_compromiso);
    }

    public function test_las_gestiones_rechazan_edicion_directa_en_la_base(): void
    {
        $credito = $this->venta(true)->credito;
        $gestion = app(VendedorService::class)->registrarGestion($credito, ['medio' => 'telefono', 'resultado' => 'contactado', 'notas' => 'Contacto original conservado como evidencia.'], $this->admin);
        try {
            DB::transaction(fn () => DB::table('gestiones_cobro')->where('id', $gestion->id)->update(['notas' => 'Modificación indebida']));
            $this->fail('Se permitió modificar la gestión.');
        } catch (QueryException) {
            $this->assertSame('Contacto original conservado como evidencia.', $gestion->fresh()->notas);
        }
    }

    public function test_filtros_y_metas_invalidas_muestran_errores_sin_registrar_cambios(): void
    {
        Livewire::actingAs($this->admin)->test(VendedoresDashboard::class)->set('mes', 'mes-invalido')->call('filtrar')->assertHasErrors('mes');
        $this->actingAs($this->admin)->post(route('vendedores.metas', $this->a), [
            'periodo' => now()->format('Y-m'), 'meta_ventas' => -1, 'meta_cobros' => '1.234', 'motivo' => 'Ajuste que debe rechazarse por importes inválidos.',
        ])->assertSessionHasErrors(['meta_ventas', 'meta_cobros']);
        $this->assertDatabaseCount('vendedor_metas', 0);
        $this->assertDatabaseCount('vendedor_auditorias', 0);
    }

    public function test_credito_manual_exige_responsable_si_no_existe_en_la_venta_o_cliente(): void
    {
        $this->cliente->update(['vendedor_id' => null]);
        $venta = Venta::create([
            'cliente_id' => $this->cliente->id, 'codigo_generacion' => (string) Str::uuid(),
            'numero_control' => 'VENTA-SIN-ASIGNAR', 'fecha_emision' => today(), 'hora_emision' => '10:00:00',
            'tipo_documento' => '11', 'condicion_operacion' => '2', 'estado_dte' => 'local', 'total_pagar' => 100,
        ]);
        $datos = ['venta_id' => $venta->id, 'condicion_credito_id' => $this->condicion->id, 'numero_cuotas' => 3, 'fecha_primera_cuota' => today()->addMonthNoOverflow()->toDateString()];
        $this->actingAs($this->admin)->post(route('creditos.store'), $datos)->assertSessionHasErrors('vendedor_id');
        $this->assertDatabaseCount('creditos', 0);
        $this->post(route('creditos.store'), $datos + ['vendedor_id' => $this->b->id])->assertSessionHasNoErrors();
        $this->assertSame($this->b->id, $venta->credito->vendedor_id);
        $this->assertNull($venta->credito->vendedor_origen_id);
    }

    public function test_edicion_de_cliente_rechaza_asignaciones_con_tipo_manipulado(): void
    {
        $this->actingAs($this->admin)->put(route('clientes.update', $this->cliente), [
            'tipo' => 'natural', 'nombre' => $this->cliente->nombre, 'vendedor_id' => ['id' => $this->b->id],
        ])->assertSessionHasErrors('vendedor_id');
        $this->assertSame($this->a->id, $this->cliente->fresh()->vendedor_id);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CondicionCredito;
use App\Models\Credito;
use App\Models\CuentaIncobrable;
use App\Models\Pago;
use App\Models\User;
use App\Models\Venta;
use App\Services\CreditoService;
use App\Services\IncobrableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IncobrablesYRecibosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Escenarios del flujo anterior; el expediente obligatorio se cubre en ExpedientesTest.
        config(['expedientes.exigir_creditos_nuevos' => false]);
        Http::preventStrayRequests();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function usuario(array $permisos = []): User
    {
        $user = User::factory()->create();
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
            $user->givePermissionTo($permiso);
        }

        return $user;
    }

    private function credito(?Cliente $cliente = null): Credito
    {
        $cliente ??= Cliente::create([
            'codigo' => 'INC-'.Str::random(10), 'nombre' => 'Cliente de prueba incobrables', 'tipo' => 'natural',
        ]);
        $condicion = CondicionCredito::create([
            'nombre' => 'Dos cuotas sin interés', 'activa' => true, 'requiere_dui' => false, 'requiere_fiador' => false,
            'frecuencia_pago' => 'mensual', 'plazo_maximo_cuotas' => 12, 'compras_minimas_cliente_frecuente' => 0,
            'monto_auto_aprobado' => 1000, 'tasa_interes_anual' => 0,
        ]);
        $venta = Venta::create([
            'cliente_id' => $cliente->id, 'codigo_generacion' => (string) Str::uuid(),
            'numero_control' => 'INC-'.Str::random(12), 'tipo_documento' => '11', 'estado_dte' => 'local',
            'fecha_emision' => today()->toDateString(), 'hora_emision' => '12:00:00',
            'condicion_operacion' => '2', 'total_pagar' => 100,
        ]);

        return app(CreditoService::class)->crearCreditoDesdeVenta($venta, [
            'condicion_credito_id' => $condicion->id, 'numero_cuotas' => 2,
            'fecha_primera_cuota' => today()->addMonth()->toDateString(),
        ]);
    }

    public function test_traslado_registra_motivo_responsable_y_saldos_sin_alterar_pagos_ni_cuotas(): void
    {
        $credito = $this->credito();
        app(CreditoService::class)->procesarPago($credito, ['monto' => 25]);
        $antes = $credito->cuotas()->get()->map->getAttributes()->all();
        $user = $this->usuario(['creditos.view', 'incobrables.classify', 'incobrables.view']);
        $this->actingAs($user)->from(route('creditos.show', $credito))
            ->post(route('creditos.incobrable', $credito), ['motivo' => 'Cliente sin capacidad de pago comprobada.'])
            ->assertRedirect(route('creditos.show', $credito))->assertSessionHasNoErrors();
        $this->assertSame('incobrable', $credito->fresh()->estado);
        $this->assertSame('75.00', $credito->fresh()->saldo_actual);
        $this->assertSame($antes, $credito->cuotas()->get()->map->getAttributes()->all());
        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseHas('cuentas_incobrables', [
            'credito_id' => $credito->id, 'saldo_capital' => 75, 'saldo_total' => 75,
            'declarado_por' => $user->id, 'abierta' => true, 'estado_anterior' => 'vigente',
            'motivo' => 'Cliente sin capacidad de pago comprobada.',
        ]);
        $this->assertNotNull(CuentaIncobrable::first()->declarado_at);
        $this->assertSame('D', $credito->cliente->fresh()->clasificacion_cobro);
        $this->get(route('creditos.show', $credito))->assertOk()->assertSee('Historial de cuentas incobrables');
        $this->get(route('incobrables.index'))->assertOk()->assertSee($credito->numero)
            ->assertSee('Cliente sin capacidad de pago comprobada.')->assertSee('75.00');
        Http::assertNothingSent();
    }

    public function test_permisos_separan_consulta_traslado_y_reactivacion(): void
    {
        $credito = $this->credito();
        $user = $this->usuario(['creditos.view']);
        $this->actingAs($user)->get(route('incobrables.index'))->assertForbidden();
        $this->post(route('creditos.incobrable', $credito), ['motivo' => 'Motivo válido de traslado.'])->assertForbidden();
        $this->get(route('creditos.show', $credito))->assertDontSee('Trasladar a incobrables');
        app(IncobrableService::class)->declararIncobrable($credito, 'Motivo válido de traslado.');
        $this->post(route('creditos.reactivar', $credito), ['motivo' => 'Motivo válido de reactivación.'])->assertForbidden();
        $visor = $this->usuario(['incobrables.view']);
        $this->actingAs($visor)->get(route('incobrables.index'))->assertOk()->assertSee($credito->numero)
            ->assertDontSee('Confirmar reactivación');
        $this->assertSame('incobrable', $credito->fresh()->estado);
    }

    public function test_saldo_total_distingue_capital_interes_y_mora_tras_un_abono(): void
    {
        $credito = $this->credito();
        app(CreditoService::class)->procesarPago($credito, ['monto' => 25]);
        $credito->cuotas()->first()->update(['interes' => 2, 'total' => 52, 'mora' => 3]);
        app(IncobrableService::class)->declararIncobrable($credito, 'Traslado con intereses y mora pendientes.');
        $this->assertDatabaseHas('cuentas_incobrables', ['credito_id' => $credito->id, 'saldo_capital' => 75, 'saldo_total' => 80]);
        app(IncobrableService::class)->reactivarCredito($credito, 'Se retoma el mismo saldo acordado.');
        $pago = app(CreditoService::class)->procesarPago($credito, ['monto' => 80]);
        $this->assertSame('75.00', $pago->abono_capital);
        $this->assertSame('2.00', $pago->pago_interes);
        $this->assertSame('3.00', $pago->pago_mora);
        $this->assertSame('cancelado', $credito->fresh()->estado);
    }

    public static function motivosInvalidos(): array
    {
        return [[''], ['          '], ['corto'], [str_repeat('a', 2001)], [['objeto']]];
    }

    #[DataProvider('motivosInvalidos')]
    public function test_motivo_invalido_no_modifica_el_credito(mixed $motivo): void
    {
        $credito = $this->credito();
        $this->actingAs($this->usuario(['incobrables.classify']))
            ->post(route('creditos.incobrable', $credito), ['motivo' => $motivo])->assertSessionHasErrors('motivo');
        $this->assertDatabaseCount('cuentas_incobrables', 0);
        $this->assertSame('vigente', $credito->fresh()->estado);
    }

    public static function estadosInvalidos(): array
    {
        return array_map(fn ($estado) => [$estado], ['pendiente_aprobacion', 'aprobado', 'cancelado', 'rechazado', 'refinanciado']);
    }

    #[DataProvider('estadosInvalidos')]
    public function test_no_traslada_estados_invalidos_aun_con_instancia_desactualizada(string $estado): void
    {
        $credito = $this->credito();
        Credito::whereKey($credito->id)->update(['estado' => $estado]);
        try {
            app(IncobrableService::class)->declararIncobrable($credito, 'Motivo válido de traslado.');
            $this->fail('Se trasladó un crédito en estado no permitido.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('vigente o vencido', $e->getMessage());
        }
        $this->assertDatabaseCount('cuentas_incobrables', 0);
        $this->assertSame($estado, $credito->fresh()->estado);
    }

    public function test_no_traslada_sin_saldo_y_no_duplica_un_traslado(): void
    {
        $credito = $this->credito();
        $service = app(IncobrableService::class);
        $service->declararIncobrable($credito, 'Motivo válido de traslado.');
        try {
            $service->declararIncobrable($credito, 'Segundo intento de traslado.');
            $this->fail('Se duplicó el traslado.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseCount('cuentas_incobrables', 1);
        }
        $otro = $this->credito();
        $otro->update(['saldo_actual' => 0]);
        $otro->cuotas()->update(['monto_pagado' => 50, 'estado' => 'pagada']);
        $this->expectException(InvalidArgumentException::class);
        $service->declararIncobrable($otro, 'Sin saldo que trasladar.');
    }

    public function test_reactivacion_conserva_historial_saldos_y_permite_cobrar(): void
    {
        $credito = $this->credito();
        $service = app(IncobrableService::class);
        $service->declararIncobrable($credito, 'Motivo válido de traslado.');
        $user = $this->usuario(['incobrables.view', 'incobrables.reactivate', 'creditos.view', 'cobros.process']);
        $this->actingAs($user)->post(route('creditos.pagos.store', $credito), ['monto' => 10, 'forma_pago' => 'efectivo'])->assertForbidden();
        $this->post(route('creditos.reactivar', $credito), ['motivo' => '  '])->assertSessionHasErrors('motivo');
        $this->from(route('incobrables.index'))->post(route('creditos.reactivar', $credito), ['motivo' => 'Cliente retoma el acuerdo de pagos.'])
            ->assertRedirect(route('incobrables.index'))->assertSessionHasNoErrors();
        $this->assertSame('vigente', $credito->fresh()->estado);
        $this->assertSame('100.00', $credito->fresh()->saldo_actual);
        $this->assertDatabaseCount('historial_clasificaciones', 2);
        $this->assertDatabaseHas('cuentas_incobrables', [
            'credito_id' => $credito->id, 'abierta' => null, 'reactivado_por' => $user->id,
            'motivo_reactivacion' => 'Cliente retoma el acuerdo de pagos.', 'saldo_capital' => 100,
        ]);
        $this->get(route('incobrables.index'))->assertOk()->assertDontSee('Motivo válido de traslado.');
        $this->get(route('incobrables.index', ['estado' => 'reactivadas']))->assertOk()
            ->assertSee('Motivo válido de traslado.')->assertSee('Cliente retoma el acuerdo de pagos.');
        $this->post(route('creditos.pagos.store', $credito), ['monto' => 10, 'forma_pago' => 'efectivo'])->assertSessionHasNoErrors();
        $this->assertSame('90.00', $credito->fresh()->saldo_actual);
        $service->declararIncobrable($credito, 'Segundo traslado documentado.');
        $this->assertDatabaseCount('cuentas_incobrables', 2);
        $this->assertSame(1, CuentaIncobrable::where('abierta', true)->count());
        $this->assertDatabaseHas('cuentas_incobrables', ['abierta' => true, 'saldo_capital' => 90]);
    }

    public function test_reactiva_a_vencido_segun_cuotas_y_mantiene_clasificacion_si_hay_otro_incobrable(): void
    {
        $credito = $this->credito();
        $otro = $this->credito($credito->cliente);
        $credito->cuotas()->first()->update(['fecha_vencimiento' => today()->subDay()]);
        $service = app(IncobrableService::class);
        $service->declararIncobrable($credito, 'Motivo válido de traslado.');
        $service->declararIncobrable($otro, 'Otro crédito sin recuperación.');
        $this->assertSame('D', $service->clasificarCliente($credito->cliente));
        $service->reactivarCredito($credito, 'Cliente retoma el pago del primer crédito.');
        $this->assertSame('vencido', $credito->fresh()->estado);
        $this->assertSame('D', $credito->cliente->fresh()->clasificacion_cobro);
        $this->assertSame('incobrable', $otro->fresh()->estado);
        $this->assertSame('D', $service->clasificarCliente($credito->cliente));
    }

    public function test_reactivacion_repetida_no_altera_el_historial(): void
    {
        $credito = $this->credito();
        $service = app(IncobrableService::class);
        $service->declararIncobrable($credito, 'Motivo válido de traslado.');
        $service->reactivarCredito($credito, 'Cliente retoma el acuerdo de pagos.');
        $antes = CuentaIncobrable::first()->getAttributes();
        try {
            $service->reactivarCredito($credito, 'Reactivación repetida inválida.');
            $this->fail('Se permitió reactivar de nuevo.');
        } catch (InvalidArgumentException) {
            $this->assertSame($antes, CuentaIncobrable::first()->getAttributes());
        }
    }

    public function test_cartera_busca_y_valida_filtros_y_conserva_clientes_archivados(): void
    {
        $credito = $this->credito();
        $otro = $this->credito();
        $service = app(IncobrableService::class);
        $service->declararIncobrable($credito, 'Motivo válido de traslado.');
        $service->declararIncobrable($otro, 'Otro traslado de crédito.');
        $credito->cliente->delete();
        $this->actingAs($this->usuario(['incobrables.view']))
            ->get(route('incobrables.index', ['buscar' => $credito->numero]))
            ->assertOk()->assertSee($credito->numero)->assertSee('Cliente de prueba incobrables')->assertDontSee($otro->numero);
        $this->get(route('incobrables.index', ['estado' => 'cualquiera']))->assertSessionHasErrors('estado');
        $this->get(route('incobrables.index', ['buscar' => ['inyeccion']]))->assertSessionHasErrors('buscar');
    }

    public function test_migracion_incorpora_creditos_antiguos_sin_inventar_fecha_y_sin_modificar_saldos(): void
    {
        $credito = $this->credito();
        $credito->update(['estado' => 'incobrable']);
        $migracion = require database_path('migrations/2026_10_30_000000_create_cuentas_incobrables_table.php');
        $migracion->down();
        $migracion->up();
        $registro = CuentaIncobrable::firstOrFail();
        $this->assertSame($credito->id, $registro->credito_id);
        $this->assertNull($registro->declarado_at);
        $this->assertNull($registro->declarado_por);
        $this->assertSame('100.00', $registro->saldo_capital);
        $this->assertSame('100.00', $registro->saldo_total);
        $this->assertSame('100.00', $credito->fresh()->saldo_actual);
        app(IncobrableService::class)->reactivarCredito($credito, 'Reactivación documentada de registro antiguo.');
        $this->assertNotNull($registro->fresh()->reactivado_at);
    }

    public function test_recibo_imprime_el_pago_exactamente_sin_actualizar_saldos_ni_llamar_a_hacienda(): void
    {
        $credito = $this->credito();
        $user = $this->usuario(['creditos.view']);
        $primero = app(CreditoService::class)->procesarPago($credito, [
            'monto' => 60, 'forma_pago' => 'transferencia', 'referencia' => 'REF-123',
            'observaciones' => '<script>alert(1)</script>', 'recibido_por' => $user->id,
        ]);
        app(CreditoService::class)->procesarPago($credito, ['monto' => 15]);
        $antes = $primero->fresh()->getAttributes();
        $response = $this->actingAs($user)->get(route('creditos.pagos.recibo', [$credito, $primero]));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame($antes, $primero->fresh()->getAttributes());
        $this->assertDatabaseCount('pagos', 2);
        $this->assertSame('25.00', $credito->fresh()->saldo_actual);
        $pago = $primero->load('aplicaciones.cuota', 'recibidoPor', 'cuota');
        $html = view('pagos.recibo', compact('credito', 'pago'))->render();
        foreach ([$pago->numero_recibo, 'REF-123', '$60.00', '$40.00', '$50.00', '$10.00', '#1', '#2', 'Sin autorización fiscal.'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertStringNotContainsString('<script>', $html);
        $this->get(route('creditos.show', $credito))->assertOk()
            ->assertSee(route('creditos.pagos.recibo', [$credito, $primero]), false)->assertSee('Imprimir recibo');
        Http::assertNothingSent();
    }

    public function test_recibo_exige_autenticacion_permiso_y_pertenencia_del_pago(): void
    {
        $credito = $this->credito();
        $otro = $this->credito();
        $pago = app(CreditoService::class)->procesarPago($credito, ['monto' => 20]);
        $url = route('creditos.pagos.recibo', [$credito, $pago]);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->usuario())->get($url)->assertForbidden();
        $this->actingAs($this->usuario(['creditos.view']))
            ->get(route('creditos.pagos.recibo', [$otro, $pago]))->assertNotFound();
    }

    public function test_recibo_historico_se_imprime_sin_inventar_aplicaciones(): void
    {
        $credito = $this->credito();
        $pago = Pago::create([
            'credito_id' => $credito->id, 'numero_recibo' => 'REC-ANTIGUO-1', 'fecha_pago' => today(),
            'monto_total' => 20, 'abono_capital' => 18, 'pago_interes' => 2,
            'saldo_despues' => 82, 'forma_pago' => 'efectivo',
        ]);
        $this->actingAs($this->usuario(['creditos.view']))
            ->get(route('creditos.pagos.recibo', [$credito, $pago]))->assertOk();
        $html = view('pagos.recibo', compact('credito', 'pago'))->render();
        $this->assertStringContainsString('Pago histórico sin detalle conciliado por cuota.', $html);
        $this->assertStringContainsString('$2.00', $html);
        $this->assertStringNotContainsString('#1', $html);
        $this->assertDatabaseCount('pago_cuotas', 0);
    }
}

<?php

namespace Tests\Feature;

use App\Exceptions\DteConfigurationException;
use App\Jobs\TransmitirDteContingencia;
use App\Livewire\PuntoVenta;
use App\Models\Bodega;
use App\Models\Cliente;
use App\Models\CondicionCredito;
use App\Models\ConfiguracionDTE;
use App\Models\Credito;
use App\Models\Existencia;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Venta;
use App\Services\ConciliacionPagosService;
use App\Services\CreditoService;
use App\Services\DteApiService;
use App\Services\FacturacionService;
use App\Services\FirmadorService;
use App\Services\IncobrableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CorreccionesIntegridadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function cliente(): Cliente
    {
        return Cliente::create(['codigo' => 'REV-'.bin2hex(random_bytes(4)), 'nombre' => 'Cliente revisión', 'tipo' => 'natural', 'activo' => true]);
    }

    private function credito(?Cliente $cliente = null): Credito
    {
        $cliente ??= $this->cliente();
        $condicion = CondicionCredito::create([
            'nombre' => 'Condición comercial', 'activa' => true, 'requiere_dui' => false, 'requiere_fiador' => false,
            'frecuencia_pago' => 'mensual', 'plazo_maximo_cuotas' => 12, 'compras_minimas_cliente_frecuente' => 0,
            'monto_auto_aprobado' => 500, 'tasa_interes_anual' => 12,
        ]);
        $venta = Venta::create([
            'cliente_id' => $cliente->id, 'codigo_generacion' => (string) Str::uuid(),
            'numero_control' => 'REV-'.bin2hex(random_bytes(4)), 'tipo_documento' => '11',
            'fecha_emision' => now()->toDateString(), 'hora_emision' => '12:00:00', 'condicion_operacion' => '2', 'total_pagar' => 100,
        ]);

        return app(CreditoService::class)->crearCreditoDesdeVenta($venta, [
            'condicion_credito_id' => $condicion->id, 'numero_cuotas' => 1, 'fecha_primera_cuota' => now()->addMonth()->toDateString(),
        ]);
    }

    private function historico(Credito $credito, float $capital = 49, float $interes = 1): Pago
    {
        $cuota = $credito->cuotas()->first();
        $pago = Pago::create([
            'credito_id' => $credito->id, 'cuota_id' => $cuota->id, 'numero_recibo' => 'REV-'.bin2hex(random_bytes(4)),
            'fecha_pago' => now()->toDateString(), 'monto_total' => $capital + $interes, 'abono_capital' => $capital,
            'pago_interes' => $interes, 'pago_mora' => 0, 'saldo_despues' => 100 - $capital, 'forma_pago' => 'efectivo',
        ]);
        $credito->update(['saldo_actual' => 100 - $capital]);
        $cuota->update(['monto_pagado' => $capital + $interes, 'estado' => 'parcial']);

        return $pago;
    }

    private function carrito(): array
    {
        $bodega = Bodega::create(['nombre' => 'Bodega revisión', 'activa' => true]);
        $unidad = UnidadMedida::create(['nombre' => 'Unidad revisión', 'abreviatura' => 'UND']);
        $producto = Producto::create([
            'codigo' => 'REV-'.bin2hex(random_bytes(4)), 'nombre' => 'Producto revisión',
            'unidad_medida_id' => $unidad->id, 'precio_venta' => 10, 'costo_unitario' => 5,
        ]);
        Existencia::create(['bodega_id' => $bodega->id, 'producto_id' => $producto->id, 'cantidad' => 10]);

        return [$bodega, $producto, [
            'producto_id' => $producto->id, 'nombre' => $producto->nombre, 'codigo' => $producto->codigo,
            'stock' => 10, 'cantidad' => 1, 'precio_unitario' => 10,
        ]];
    }

    public function test_pagos_historicos_se_concilian_antes_del_nuevo_abono(): void
    {
        $credito = $this->credito();
        $historico = $this->historico($credito);
        $pago = app(CreditoService::class)->procesarPago($credito, ['monto' => 51]);
        $this->assertSame('0.00', $credito->fresh()->saldo_actual);
        $this->assertSame('cancelado', $credito->fresh()->estado);
        $this->assertSame('0.00', $pago->pago_interes);
        $this->assertSame('101.00', $credito->cuotas()->first()->monto_pagado);
        $this->assertCount(1, $historico->aplicaciones);
        $this->assertSame('49.00', $historico->abono_capital);
    }

    public function test_conciliacion_es_idempotente_y_no_altera_recibos_ni_saldos(): void
    {
        $credito = $this->credito();
        $pago = $this->historico($credito);
        $antes = $pago->fresh()->getAttributes();
        $this->assertSame(1, app(ConciliacionPagosService::class)->conciliar($credito));
        $this->assertSame(0, app(ConciliacionPagosService::class)->conciliar($credito));
        $this->assertSame($antes, $pago->fresh()->getAttributes());
        $this->assertSame('51.00', $credito->fresh()->saldo_actual);
        $this->assertDatabaseCount('pago_cuotas', 1);
    }

    public function test_historial_ambiguo_bloquea_pago_sin_modificaciones(): void
    {
        $credito = $this->credito();
        $pago = $this->historico($credito, 99, 2); // El recibo antiguo cobró más interés que el plan.
        try {
            app(CreditoService::class)->procesarPago($credito, ['monto' => 1]);
            $this->fail('Se aceptó un historial ambiguo.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('conciliación', $e->getMessage());
        }
        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseCount('pago_cuotas', 0);
        $this->assertSame('1.00', $credito->fresh()->saldo_actual);
        $this->assertSame('101.00', $pago->fresh()->monto_total);
    }

    public function test_comando_concilia_solo_con_aplicar(): void
    {
        $credito = $this->credito();
        $this->historico($credito);
        $this->artisan('creditos:conciliar-pagos')->assertSuccessful();
        $this->assertDatabaseCount('pago_cuotas', 0);
        $this->artisan('creditos:conciliar-pagos', ['--aplicar' => true])->assertSuccessful();
        $this->assertDatabaseCount('pago_cuotas', 1);
        $this->artisan('creditos:conciliar-pagos', ['--aplicar' => true])->assertSuccessful();
        $this->assertDatabaseCount('pago_cuotas', 1);
    }

    public function test_usuario_sin_permiso_no_accede_al_pos_ni_al_pdf(): void
    {
        $user = $this->usuario();
        $credito = $this->credito();
        $this->actingAs($user)->get('/facturacion/pos')->assertForbidden();
        $this->get('/facturacion/'.$credito->venta_id.'/pdf')->assertForbidden();
    }

    public function test_usuario_solo_lectura_no_puede_facturar_contado(): void
    {
        [$bodega, $producto, $linea] = $this->carrito();
        Livewire::actingAs($this->usuario(['facturacion.view']))->test(PuntoVenta::class)->set('vendedor_id', \App\Models\Vendedor::firstOrCreate(['codigo' => 'REV-VND'], ['nombre' => 'Vendedor revisión', 'activo' => true])->id)
            ->set('tipo_documento', '11')->set('bodega_id', $bodega->id)->set('detalles', [$linea])
            ->call('facturar')->assertForbidden();
        $this->assertDatabaseCount('ventas', 0);
        $this->assertEquals(10, $producto->stock_total);
    }

    public function test_facturacion_contado_autorizada_registra_venta_e_inventario(): void
    {
        [$bodega, $producto, $linea] = $this->carrito();
        $user = $this->usuario(['facturacion.emitir']);
        Livewire::actingAs($user)->test(PuntoVenta::class)->set('vendedor_id', \App\Models\Vendedor::firstOrCreate(['codigo' => 'REV-VND'], ['nombre' => 'Vendedor revisión', 'activo' => true])->id)
            ->set('tipo_documento', '11')->set('bodega_id', $bodega->id)->set('detalles', [$linea])
            ->call('facturar')->assertHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('ventas', ['total_pagar' => 10, 'condicion_operacion' => '1']);
        $this->assertEquals(9, $producto->stock_total);
        $this->get('/facturacion/'.Venta::first()->id.'/pdf')->assertOk();
    }

    public function test_cantidad_negativa_se_rechaza_en_livewire_y_servicio(): void
    {
        [$bodega, $producto, $linea] = $this->carrito();
        $linea['cantidad'] = -2;
        $user = $this->usuario(['facturacion.emitir']);
        Livewire::actingAs($user)->test(PuntoVenta::class)->set('vendedor_id', \App\Models\Vendedor::firstOrCreate(['codigo' => 'REV-VND'], ['nombre' => 'Vendedor revisión', 'activo' => true])->id)
            ->set('tipo_documento', '11')->set('bodega_id', $bodega->id)->set('detalles', [$linea])
            ->call('facturar')->assertHasErrors(['detalles.0.cantidad'])->assertSee('Revise los datos de la venta:');
        try {
            app(FacturacionService::class)->procesarVenta(['tipo_documento' => '11', 'condicion_operacion' => '1', 'bodega_id' => $bodega->id], [$linea], $user->id);
            $this->fail('El servicio aceptó una cantidad negativa.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('detalles.0.cantidad', $e->errors());
        }
        $this->assertDatabaseCount('ventas', 0);
        $this->assertEquals(10, $producto->stock_total);
    }

    public function test_precio_manipulado_requiere_permiso_explicito(): void
    {
        [$bodega, $producto, $linea] = $this->carrito();
        $user = $this->usuario(['facturacion.emitir']);
        $linea['precio_unitario'] = 1;
        Livewire::actingAs($user)->test(PuntoVenta::class)->set('vendedor_id', \App\Models\Vendedor::firstOrCreate(['codigo' => 'REV-VND'], ['nombre' => 'Vendedor revisión', 'activo' => true])->id)
            ->set('tipo_documento', '11')->set('bodega_id', $bodega->id)->set('detalles', [$linea])
            ->call('facturar')->assertHasErrors(['detalles.0.precio_unitario']);
        $this->assertDatabaseCount('ventas', 0);
        $user->givePermissionTo('facturacion.modificar_precio');
        Livewire::actingAs($user->fresh())->test(PuntoVenta::class)->set('vendedor_id', \App\Models\Vendedor::firstOrCreate(['codigo' => 'REV-VND'], ['nombre' => 'Vendedor revisión', 'activo' => true])->id)
            ->set('tipo_documento', '11')->set('bodega_id', $bodega->id)->set('detalles', [$linea])
            ->call('facturar')->assertHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('ventas', ['total_pagar' => 1]);
        $this->assertSame('10.00', $producto->fresh()->precio_venta);
    }

    public function test_tipo_numerico_no_permite_omitir_el_permiso_de_credito(): void
    {
        [$bodega, $producto, $linea] = $this->carrito();
        Livewire::actingAs($this->usuario(['facturacion.emitir']))->test(PuntoVenta::class)->set('vendedor_id', \App\Models\Vendedor::firstOrCreate(['codigo' => 'REV-VND'], ['nombre' => 'Vendedor revisión', 'activo' => true])->id)
            ->set('tipo_documento', '11')->set('condicion_operacion', 2)
            ->set('bodega_id', $bodega->id)->set('detalles', [$linea])->call('facturar')->assertForbidden();
        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_servicio_no_omite_el_credito_si_la_condicion_llega_como_numero(): void
    {
        [$bodega, $producto, $linea] = $this->carrito();
        $this->expectException(\InvalidArgumentException::class);
        app(FacturacionService::class)->procesarVenta([
            'tipo_documento' => '11', 'condicion_operacion' => 2, 'cliente_id' => $this->cliente()->id,
            'bodega_id' => $bodega->id,
        ], [$linea], $this->usuario()->id);
    }

    public function test_producto_inactivo_se_rechaza_sin_movimientos(): void
    {
        [$bodega, $producto, $linea] = $this->carrito();
        $producto->update(['activo' => false]);
        $this->expectException(ValidationException::class);
        app(FacturacionService::class)->procesarVenta(['tipo_documento' => '11', 'condicion_operacion' => '1', 'bodega_id' => $bodega->id], [$linea], $this->usuario()->id);
    }

    public function test_dte_sin_configuracion_revierte_venta_e_inventario(): void
    {
        config(['erp.dte.enabled' => true]);
        [$bodega, $producto, $linea] = $this->carrito();
        try {
            app(FacturacionService::class)->procesarVenta(['tipo_documento' => '01', 'condicion_operacion' => '1', 'bodega_id' => $bodega->id], [$linea], $this->usuario()->id);
            $this->fail('Se emitió un DTE sin configuración.');
        } catch (DteConfigurationException $e) {
            $this->assertStringContainsString('Configure', $e->getMessage());
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertEquals(10, $producto->stock_total);
    }

    public function test_api_sin_configuracion_no_devuelve_sello_simulado(): void
    {
        config(['erp.dte.enabled' => true]);
        $this->expectException(DteConfigurationException::class);
        app(DteApiService::class)->transmitirDTE('documento-de-prueba', '01', 'codigo-de-prueba');
    }

    public function test_firma_y_api_comparten_un_documento_string_y_solo_usan_respuestas_externas(): void
    {
        config(['erp.dte.enabled' => true]);
        ConfiguracionDTE::create(['nit' => 'NIT-DE-PRUEBA', 'password_api' => 'test-only', 'password_firmador' => 'test-only', 'url_firmador' => 'https://firmador.example.test', 'ambiente' => '00']);
        Http::fake([
            'firmador.example.test/*' => Http::response(['status' => 'OK', 'body' => 'firma.de.prueba']),
            'apitest.mh.gob.sv/*seguridad/auth' => Http::response(['status' => 'OK', 'body' => ['token' => 'token-de-prueba']]),
            'apitest.mh.gob.sv/*recepciondte/*' => Http::response(['estado' => 'PROCESADO', 'selloRecibido' => 'respuesta-de-prueba']),
        ]);
        $firmado = app(FirmadorService::class)->firmarDocumento(['identificacion' => ['ambiente' => '00']]);
        $respuesta = app(DteApiService::class)->transmitirDTE($firmado, '01', 'codigo');
        $this->assertSame('respuesta-de-prueba', $respuesta['selloRecibido']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/recepciondte/') && $request['documento'] === 'firma.de.prueba');
    }

    public function test_ficha_cliente_muestra_condicion_comercial_y_fecha(): void
    {
        $cliente = $this->cliente();
        $credito = $this->credito($cliente);
        $this->actingAs($this->usuario())->get('/clientes/'.$cliente->id)->assertOk()
            ->assertSee('Condición comercial')->assertSee($credito->fecha_solicitud->format('d/m/Y'));
    }

    public function test_proyecto_academico_completa_venta_credito_y_pago_sin_hacienda(): void
    {
        $this->assertFalse(config('erp.dte.enabled'));
        Queue::fake();
        [$bodega, $producto, $linea] = $this->carrito();
        $base = $this->credito();
        $user = $this->usuario(['facturacion.emitir', 'creditos.create', 'creditos.view', 'cobros.process']);
        Livewire::actingAs($user)->test(PuntoVenta::class)->set('vendedor_id', \App\Models\Vendedor::firstOrCreate(['codigo' => 'REV-VND'], ['nombre' => 'Vendedor revisión', 'activo' => true])->id)
            ->set('tipo_documento', '03')->set('condicion_operacion', '2')
            ->set('cliente_id', $base->cliente_id)->set('vendedor_id', \App\Models\Vendedor::where('codigo', 'REV-VND')->firstOrFail()->id)->set('condicion_credito_id', $base->condicion_credito_id)
            ->set('numero_cuotas', 2)->set('bodega_id', $bodega->id)->set('detalles', [$linea])
            ->call('facturar')->assertHasNoErrors()->assertRedirect();
        $venta = Venta::latest('id')->first();
        $credito = $venta->credito;
        $this->assertSame('local', $venta->estado_dte);
        $this->assertNull($venta->sello_recepcion);
        $this->assertNull($venta->json_firmado);
        $this->assertSame('11.30', $credito->monto_original);
        $this->assertCount(2, $credito->cuotas);
        $this->assertEquals(9, $producto->stock_total);
        $this->get('/facturacion/'.$venta->id.'/pdf')->assertOk();
        $this->assertStringContainsString('DOCUMENTO LOCAL', view('facturacion.pdf', compact('venta'))->render());
        $this->post('/creditos/'.$credito->id.'/pagos', [
            'monto' => $credito->cuotas()->sum('total'), 'forma_pago' => 'efectivo',
        ])->assertRedirect('/creditos/'.$credito->id)->assertSessionHasNoErrors();
        $this->assertSame('cancelado', $credito->fresh()->estado);
        $this->assertSame('0.00', $credito->fresh()->saldo_actual);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_modo_local_ignora_credenciales_guardadas_y_jobs_antiguos(): void
    {
        ConfiguracionDTE::create(['nit' => 'NIT-DE-PRUEBA', 'password_api' => 'test-only', 'password_firmador' => 'test-only', 'url_firmador' => 'https://firmador.example.test', 'ambiente' => '00']);
        [$bodega, $producto, $linea] = $this->carrito();
        $venta = app(FacturacionService::class)->procesarVenta([
            'tipo_documento' => '01', 'condicion_operacion' => '1', 'bodega_id' => $bodega->id,
        ], [$linea], $this->usuario()->id);
        $this->assertSame('local', $venta->estado_dte);
        $venta->update(['estado_dte' => 'contingencia']);
        (new TransmitirDteContingencia($venta->id))->handle(app(DteApiService::class));
        $this->assertSame('contingencia', $venta->fresh()->estado_dte);
        Http::assertNothingSent();
    }

    public function test_migracion_reclasifica_solo_sellos_ficticios_antiguos(): void
    {
        $demo = $this->credito()->venta;
        $real = $this->credito()->venta;
        $demo->update(['estado_dte' => 'procesado', 'sello_recepcion' => 'SELLO_DUMMY_TESTING']);
        $real->update(['estado_dte' => 'procesado', 'sello_recepcion' => 'sello-historico-preservado']);
        $migracion = require database_path('migrations/2026_10_29_200002_allow_local_sales_documents.php');
        $migracion->down();
        $migracion->up();
        $this->assertSame('local', $demo->fresh()->estado_dte);
        $this->assertNull($demo->fresh()->sello_recepcion);
        $this->assertSame('procesado', $real->fresh()->estado_dte);
        $this->assertSame('sello-historico-preservado', $real->fresh()->sello_recepcion);
    }

    public function test_reclasificacion_guarda_historial_y_no_duplica_cambios(): void
    {
        $cliente = $this->cliente();
        $credito = $this->credito($cliente);
        $credito->update(['dias_mora' => 100]);
        $servicio = app(IncobrableService::class);
        $this->assertSame('D', $servicio->clasificarCliente($cliente));
        $this->assertSame('D', $servicio->clasificarCliente($cliente->fresh()));
        $this->assertDatabaseCount('historial_clasificaciones', 1);
        $this->assertDatabaseHas('historial_clasificaciones', ['cliente_id' => $cliente->id, 'clasificacion_nueva' => 'D', 'fecha' => now()->toDateString()]);
    }

    public function test_migracion_conserva_historial_crediticio_existente(): void
    {
        $credito = $this->credito();
        $migracion = require database_path('migrations/2026_10_29_200000_restore_cliente_classification_history.php');
        $migracion->down();
        $id = DB::table('historial_clasificaciones')->insertGetId([
            'credito_id' => $credito->id, 'clasificacion_anterior' => 'A', 'clasificacion_nueva' => 'B',
            'dias_mora' => 35, 'saldo_momento' => 100, 'fecha' => now()->toDateString(), 'observaciones' => 'Historial conservado',
        ]);
        $migracion->up();
        $this->assertDatabaseHas('historial_clasificaciones', ['id' => $id, 'cliente_id' => $credito->cliente_id, 'observaciones' => 'Historial conservado', 'motivo' => 'Historial conservado']);
    }

    public function test_login_limita_cuenta_sin_depender_de_mayusculas_y_se_recupera(): void
    {
        $user = $this->usuario();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $i % 2 ? strtoupper($user->email) : $user->email, 'password' => 'incorrecta'])->assertRedirect();
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_limita_ip_aunque_cambie_la_cuenta(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', ['email' => "cuenta{$i}@example.test", 'password' => 'incorrecta'])->assertRedirect();
        }
        $this->post('/login', ['email' => 'otra@example.test', 'password' => 'incorrecta'])->assertStatus(429);
    }

    public function test_login_correcto_limpia_los_intentos_de_la_cuenta(): void
    {
        $user = $this->usuario();
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])->assertRedirect();
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->post('/logout');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])->assertRedirect();
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])->assertStatus(429);
    }
}

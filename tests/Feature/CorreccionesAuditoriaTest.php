<?php

namespace Tests\Feature;

use App\Exceptions\DteConfigurationException;
use App\Livewire\ActivoFijoDashboard;
use App\Livewire\Configuracion\CatalogosDashboard;
use App\Livewire\MovimientoInventarioModal;
use App\Models\Bodega;
use App\Models\CategoriaActivo;
use App\Models\Cliente;
use App\Models\CondicionCredito;
use App\Models\ConfiguracionDTE;
use App\Models\Credito;
use App\Models\Existencia;
use App\Models\Institucion;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Services\ActivoFijoService;
use App\Services\CorrelativoService;
use App\Services\CreditoService;
use App\Services\DevengoCreditoService;
use App\Services\FacturacionService;
use App\Services\FirmadorService;
use App\Services\InventarioService;
use App\Services\LimiteCreditoService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CorreccionesAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private function stock(string $metodo = 'PEPS'): array
    {
        $b = Bodega::create(['nombre' => 'Auditoría', 'activa' => true]);
        $u = UnidadMedida::create(['nombre' => 'Unidad', 'abreviatura' => 'UND']);
        $p = Producto::create(['codigo' => 'AUD', 'nombre' => 'Producto', 'metodo_costeo' => $metodo, 'unidad_medida_id' => $u->id, 'precio_venta' => 113, 'activo' => true]);

        return [$b, $p, User::factory()->create()];
    }

    private function credito(): Credito
    {
        $cliente = Cliente::create(['codigo' => 'AUD', 'nombre' => 'Cliente', 'tipo' => 'natural']);

        return Credito::create(['numero' => 'AUD', 'cliente_id' => $cliente->id, 'monto_original' => 100, 'saldo_actual' => 100,
            'numero_cuotas' => 1, 'plazo_dias' => 30, 'frecuencia_pago' => 'mensual', 'tasa_interes_anual' => 12,
            'tasa_mora_mensual' => 3, 'dias_gracia' => 0, 'fecha_aprobacion' => today(), 'fecha_solicitud' => today(),
            'fecha_primera_cuota' => today()->addMonth(), 'fecha_vencimiento' => today()->addMonth(), 'estado' => 'vigente', 'calculo_interes' => 'diario']);
    }

    public function test_usuario_sin_permisos_no_edita_ni_borra_clientes_ni_catalogos_ni_activos(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $c = Cliente::create(['codigo' => 'AUD', 'nombre' => 'Original', 'tipo' => 'natural']);
        $this->put('/clientes/'.$c->id, ['nombre' => 'Alterado'])->assertForbidden();
        $this->delete('/clientes/'.$c->id)->assertForbidden();
        Livewire::test(CatalogosDashboard::class)->assertForbidden();
        Livewire::test(ActivoFijoDashboard::class)->assertForbidden();
        Livewire::test(MovimientoInventarioModal::class)->assertForbidden();
        $this->assertSame('Original', $c->fresh()->nombre);
        $permiso = Permission::firstOrCreate(['name' => 'configuracion.manage', 'guard_name' => 'web']);
        $u->givePermissionTo($permiso);
        $componente = Livewire::test(CatalogosDashboard::class);
        $u->revokePermissionTo($permiso);
        $componente->call('save')->assertForbidden();
    }

    public function test_peps_conserva_costos_y_antiguedad_al_transferir_y_vender(): void
    {
        [$b,$p,$u] = $this->stock();
        $s = app(InventarioService::class);
        foreach ([['A', 5, 10], ['B', 20, 10]] as [$lote,$coste,$q]) {
            $s->registrarEntrada(['bodega_id' => $b->id, 'detalles' => [['producto_id' => $p->id, 'cantidad' => $q, 'costo_unitario' => $coste, 'lote' => $lote]]], $u->id);
        }
        $dest = Bodega::create(['nombre' => 'Destino', 'activa' => true]);
        $m = $s->transferirBodega(['bodega_origen_id' => $b->id, 'bodega_destino_id' => $dest->id, 'detalles' => [['producto_id' => $p->id, 'cantidad' => 12]]], $u->id);
        $this->assertEquals(90, $m->costo_total);
        $venta = $s->registrarSalida(['bodega_id' => $dest->id, 'detalles' => [['producto_id' => $p->id, 'cantidad' => 11]]], $u->id);
        $this->assertEquals(70, $venta->costo_total);
        $this->assertEquals(9, $p->fresh()->stock_total);
        $this->assertEquals(180, DB::table('capas_inventario')->selectRaw('SUM(cantidad*costo_unitario) AS v')->value('v'));
    }

    public function test_aligacion_pondera_las_cinco_ultimas_compras_y_salida_insuficiente_revierte(): void
    {
        [$b,$p,$u] = $this->stock('PROMEDIO_ALIGACION');
        $s = app(InventarioService::class);
        foreach ([[100, 1], [1, 10], [2, 20], [3, 30], [4, 40], [5, 50]] as [$q,$c]) {
            $s->registrarEntrada(['bodega_id' => $b->id, 'detalles' => [['producto_id' => $p->id, 'cantidad' => $q, 'costo_unitario' => $c]]], $u->id);
        }
        $this->assertEqualsWithDelta(550 / 15, (float) $p->fresh()->costo_unitario, 0.0001);
        try {
            $s->registrarSalida(['bodega_id' => $b->id, 'detalles' => [['producto_id' => $p->id, 'cantidad' => 999]]], $u->id);
            $this->fail();
        } catch (ValidationException $e) {
        }
        $this->assertEquals(115, $p->fresh()->stock_total);
        $this->assertDatabaseCount('movimientos_inventario', 6);
    }

    public function test_peps_historico_sin_capas_no_inventa_costos(): void
    {
        [$b,$p,$u] = $this->stock();
        Existencia::create(['bodega_id' => $b->id, 'producto_id' => $p->id, 'cantidad' => 10]);
        $this->expectException(ValidationException::class);
        app(InventarioService::class)->registrarSalida(['bodega_id' => $b->id, 'detalles' => [['producto_id' => $p->id, 'cantidad' => 1]]], $u->id);
    }

    public function test_liquidacion_el_dia_de_aprobacion_no_cobra_intereses_futuros(): void
    {
        $c = $this->credito();
        $s = app(CreditoService::class);
        $s->generarPlanPago($c);
        $p = $s->procesarPago($c, ['monto' => 100]);
        $this->assertSame('0.00', $p->pago_interes);
        $this->assertSame('100.00', $p->abono_capital);
        $this->assertSame('cancelado', $c->fresh()->estado);
    }

    public function test_devengo_diario_usa_saldos_reales_y_no_repite_interes_ni_mora(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');
        try {
            $c = $this->credito();
            $c->update(['tasa_interes_anual' => 36.5, 'tasa_mora_mensual' => 0]);
            $s = app(CreditoService::class);
            $s->generarPlanPago($c);
            Carbon::setTestNow('2026-06-11 12:00:00');
            $p = $s->procesarPago($c, ['monto' => 51]);
            $this->assertSame('1.00', $p->pago_interes);
            $this->assertSame('50.00', $p->abono_capital);
            Carbon::setTestNow('2026-06-21 12:00:00');
            app(DevengoCreditoService::class)->actualizar($c);
            $this->assertSame('1.50', $c->cuotas()->first()->interes);
            $p = $s->procesarPago($c, ['monto' => 50.50]);
            $this->assertSame('0.50', $p->pago_interes);
            $this->assertSame('cancelado', $c->fresh()->estado);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_cuota_vencida_calcula_mora_solo_en_capital_y_es_idempotente(): void
    {
        $c = $this->credito();
        $c->update(['fecha_aprobacion' => today()->subDays(60), 'fecha_solicitud' => today()->subDays(60), 'tasa_interes_anual' => 0, 'fecha_primera_cuota' => today()->subDays(30)]);
        app(CreditoService::class)->generarPlanPago($c);
        $c->cuotas()->update(['estado' => 'vencida']);
        $r = app(CreditoService::class)->calcularMora($c);
        $antes = $c->cuotas()->first()->mora;
        $this->assertSame(30, $r['dias_mora']);
        $this->assertGreaterThan(0, (float) $antes);
        app(CreditoService::class)->calcularMora($c);
        $this->assertSame($antes, $c->cuotas()->first()->mora);
    }

    public function test_iva_incluido_y_dte_no_homologado_bloqueado_sin_movimientos(): void
    {
        [$b,$p,$u] = $this->stock('PROMEDIO');
        app(InventarioService::class)->registrarEntrada(['bodega_id' => $b->id, 'detalles' => [['producto_id' => $p->id, 'cantidad' => 10, 'costo_unitario' => 50]]], $u->id);
        $datos = ['bodega_id' => $b->id, 'tipo_documento' => '01', 'condicion_operacion' => '1'];
        $lineas = [['producto_id' => $p->id, 'cantidad' => 1, 'precio_unitario' => 113]];
        $v = app(FacturacionService::class)->procesarVenta($datos, $lineas, $u->id);
        $this->assertEquals(113, $v->total_pagar);
        $this->assertEquals(13, $v->total_iva);
        config(['erp.dte.enabled' => true]);
        Http::preventStrayRequests();
        try {
            app(FacturacionService::class)->procesarVenta($datos, $lineas, $u->id);
            $this->fail();
        } catch (DteConfigurationException $e) {
        }
        $this->assertDatabaseCount('ventas', 1);
        $this->assertEquals(9, $p->fresh()->stock_total);
        Http::assertNothingSent();
    }

    public function test_correlativo_no_reutiliza_el_numero_y_migraciones_no_borran_historial(): void
    {
        $s = app(CorrelativoService::class);
        $this->assertSame(11, $s->siguiente('auditoria', 10));
        $this->assertSame(12, $s->siguiente('auditoria', 1));
        $c = $this->credito();
        $m = require database_path('migrations/2026_09_13_030001_recreate_creditos_table_comercial.php');
        try {
            $m->up();
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('contiene datos', $e->getMessage());
        }
        $this->assertDatabaseHas('creditos', ['id' => $c->id]);
    }

    public function test_credito_con_cargos_requiere_tope_vigente(): void
    {
        $c = CondicionCredito::create(['nombre' => 'Condición', 'tasa_interes_anual' => 12, 'tasa_mora_mensual' => 3]);
        config(['erp.creditos.limites_legales' => []]);
        $this->expectException(ValidationException::class);
        app(LimiteCreditoService::class)->validar($c, 100);
    }

    public function test_baja_excluye_activo_y_depreciacion_usa_inicio_real(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $i = Institucion::create(['codigo' => 'AUD', 'nombre' => 'Auditoría']);
        $u = Unidad::create(['institucion_id' => $i->id, 'codigo' => 'AUD', 'nombre' => 'Unidad']);
        $cat = CategoriaActivo::create(['codigo' => 'AUD', 'nombre' => 'Equipo', 'porcentaje_depreciacion' => 20]);
        $s = app(ActivoFijoService::class);
        $a = $s->registrarActivo(['unidad_id' => $u->id, 'categoria_id' => $cat->id, 'nombre' => 'Equipo', 'fecha_adquisicion' => '2025-01-01', 'clase_fiscal' => 'otros_muebles', 'fecha_inicio_uso' => '2025-03-16', 'valor_adquisicion' => 1000]);
        $this->assertSame(0, $s->calcularDepreciacionMasiva('2025-02-28'));
        $this->assertSame(1, $s->calcularDepreciacionMasiva('2025-03-31'));
        $this->assertEqualsWithDelta(200 * 16 / 365, (float) $a->fresh()->depreciacion_acumulada, 0.0001);
        $this->assertSame(0, $s->calcularDepreciacionMasiva('2025-03-31'));
        $s->desincorporarActivo($a->id, 'vendido', 'Venta documentada');
        Livewire::actingAs($user)->test(ActivoFijoDashboard::class)->assertSet('totalAdquisicion', 0)->assertSet('valorNetoLibros', 0);
    }

    public function test_correlativos_son_unicos_con_dos_procesos_postgresql(): void
    {
        $conexion = config('database.connections.pgsql');
        $clave = 'concurrencia-'.bin2hex(random_bytes(8));
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => $conexion['database'],
            'DB_HOST' => $conexion['host'], 'DB_PORT' => $conexion['port'], 'DB_USERNAME' => $conexion['username'],
            'DB_PASSWORD' => $conexion['password'], 'PHPRC' => php_ini_loaded_file()];
        $comando = [PHP_BINARY, base_path('tests/Fixtures/correlativo_worker.php'), $clave];
        $a = new Process($comando, base_path(), $env);
        $b = new Process($comando, base_path(), $env);
        $a->start();
        $b->start();
        $a->wait();
        $b->wait();
        $this->assertTrue($a->isSuccessful(), $a->getErrorOutput());
        $this->assertTrue($b->isSuccessful(), $b->getErrorOutput());
        $numeros = array_merge(json_decode($a->getOutput(), true), json_decode($b->getOutput(), true));
        sort($numeros);
        $this->assertSame(range(1, 50), $numeros);
    }

    public function test_datos_del_firmador_se_cifran_y_no_se_serializan(): void
    {
        $c = ConfiguracionDTE::create(['password_api' => 'clave-sintetica', 'password_firmador' => 'firma-sintetica']);
        $this->assertNotSame('clave-sintetica', DB::table('configuracion_dte')->where('id', $c->id)->value('password_api'));
        $this->assertSame('clave-sintetica', $c->fresh()->password_api);
        $this->assertArrayNotHasKey('password_api', $c->toArray());
    }

    public function test_interes_diario_separa_anios_de_365_y_366_dias(): void
    {
        Carbon::setTestNow('2024-12-31');
        try {
            $c = $this->credito();
            $c->update(['tasa_interes_anual' => 36.6, 'tasa_mora_mensual' => 0]);
            app(CreditoService::class)->generarPlanPago($c);
            Carbon::setTestNow('2025-01-02');
            app(DevengoCreditoService::class)->actualizar($c);
            $this->assertSame('0.20', $c->cuotas()->first()->interes);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_actualizacion_cifra_credenciales_anteriores_sin_perder_su_valor(): void
    {
        $id = DB::table('configuracion_dte')->insertGetId(['password_api' => 'credencial-anterior', 'ambiente' => '00']);
        $migracion = require database_path('migrations/2026_11_15_000002_proteger_credenciales_dte.php');
        $migracion->up();
        $this->assertSame('credencial-anterior', ConfiguracionDTE::findOrFail($id)->password_api);
        $this->assertNotSame('credencial-anterior', DB::table('configuracion_dte')->where('id', $id)->value('password_api'));
    }

    public function test_modo_universitario_permite_demo_sin_topes_reales_ni_conexion_a_hacienda(): void
    {
        config(['erp.modo_academico' => true, 'erp.dte.enabled' => true, 'erp.creditos.limites_legales' => [], 'expedientes.exigir_creditos_nuevos' => false]);
        Http::preventStrayRequests();
        [$b, $p, $u] = $this->stock('PROMEDIO');
        app(InventarioService::class)->registrarEntrada(['bodega_id' => $b->id, 'detalles' => [['producto_id' => $p->id, 'cantidad' => 10, 'costo_unitario' => 50]]], $u->id);
        $cliente = Cliente::create(['codigo' => 'FICTICIO', 'nombre' => 'Cliente ficticio del proyecto', 'tipo' => 'natural', 'activo' => true]);
        $condicion = CondicionCredito::create(['nombre' => 'Demostración', 'activa' => true, 'requiere_dui' => false, 'requiere_fiador' => false,
            'frecuencia_pago' => 'mensual', 'plazo_maximo_cuotas' => 12, 'compras_minimas_cliente_frecuente' => 0,
            'monto_auto_aprobado' => 1000, 'tasa_interes_anual' => 12, 'tasa_mora_mensual' => 3]);
        $venta = app(FacturacionService::class)->procesarVenta(['bodega_id' => $b->id, 'cliente_id' => $cliente->id,
            'tipo_documento' => '01', 'condicion_operacion' => '2', 'condiciones_credito' => ['condicion_credito_id' => $condicion->id, 'numero_cuotas' => 2]],
            [['producto_id' => $p->id, 'cantidad' => 1, 'precio_unitario' => 113]], $u->id);
        $this->assertSame('local', $venta->estado_dte);
        $this->assertNull($venta->sello_recepcion);
        $this->assertSame('simulacion_academica', $venta->credito->limite_legal['modo']);
        $permiso = Permission::firstOrCreate(['name' => 'facturacion.view', 'guard_name' => 'web']);
        $u->givePermissionTo($permiso);
        $this->actingAs($u)->get(route('facturacion.json-academico', $venta))->assertOk()
            ->assertJsonPath('es_simulacion', true)->assertJsonPath('validez_fiscal', false)
            ->assertJsonPath('transmitido', false)->assertJsonPath('sello_hacienda', null)->assertJsonPath('resumen.iva', 13);
        Http::assertNothingSent();
        $this->expectException(DteConfigurationException::class);
        app(FirmadorService::class)->firmarDocumento(['ejemplo' => true]);
    }

    public function test_json_academico_requiere_permiso_y_no_se_publica_fuera_del_simulador(): void
    {
        [$b, $p, $u] = $this->stock('PROMEDIO');
        app(InventarioService::class)->registrarEntrada(['bodega_id' => $b->id, 'detalles' => [['producto_id' => $p->id, 'cantidad' => 1, 'costo_unitario' => 50]]], $u->id);
        $v = app(FacturacionService::class)->procesarVenta(['bodega_id' => $b->id, 'tipo_documento' => 'TL', 'condicion_operacion' => '1'],
            [['producto_id' => $p->id, 'cantidad' => 1, 'precio_unitario' => 113]], $u->id);
        config(['erp.modo_academico' => true]);
        $this->actingAs($u)->get(route('facturacion.json-academico', $v))->assertForbidden();
        $u->givePermissionTo(Permission::firstOrCreate(['name' => 'facturacion.view', 'guard_name' => 'web']));
        config(['erp.modo_academico' => false]);
        $this->get(route('facturacion.json-academico', $v))->assertNotFound();
    }
}

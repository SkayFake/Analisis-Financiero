<?php

namespace Tests\Feature;

use App\Models\ActivoFijo;
use App\Models\CategoriaActivo;
use App\Models\Cliente;
use App\Models\ClienteJuridico;
use App\Models\ConfiguracionDTE;
use App\Models\Credito;
use App\Models\CuentaIncobrable;
use App\Models\Existencia;
use App\Models\Fiador;
use App\Models\Institucion;
use App\Models\MovimientoInventario;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Services\ActivoFijoService;
use App\Services\ConciliacionPagosService;
use App\Services\FacturacionService;
use App\Support\DatosSalvador;
use Carbon\Carbon;
use Database\Seeders\PruebasIntegralesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PruebasIntegralesSeederTest extends TestCase
{
    use RefreshDatabase;

    private const TABLAS = [
        'users', 'roles', 'permissions', 'model_has_roles', 'role_has_permissions', 'model_has_permissions',
        'zonas', 'vendedores', 'carteras', 'politicas_cobro', 'condiciones_credito',
        'clientes', 'clientes_juridicos', 'creditos', 'cuotas', 'pagos', 'pago_cuotas', 'fiadores',
        'cuentas_incobrables', 'historial_clasificaciones', 'refinanciamientos', 'embargos',
        'proveedores', 'categorias', 'marcas', 'unidades_medida', 'bodegas', 'productos', 'existencias',
        'movimientos_inventario', 'detalle_movimientos', 'ventas', 'detalle_ventas',
        'instituciones', 'unidades', 'categoria_activos', 'activos_fijos', 'depreciaciones',
        'configuracion_dte', 'lotes_contingencia', 'eventos_invalidacion',
        'vendedor_metas', 'vendedor_auditorias', 'gestiones_cobro', 'expediente_documentos', 'expediente_eventos',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Storage::fake('expedientes');
        Http::preventStrayRequests();
        Queue::fake();
    }

    private function escenario(string $caso): Credito
    {
        return Credito::where('observaciones', 'like', 'QA-'.$caso.' |%')->firstOrFail();
    }

    public function test_carga_modulos_y_concilia_las_relaciones_financieras(): void
    {
        config(['erp.dte.enabled' => true]);
        $this->seed(PruebasIntegralesSeeder::class);
        $this->assertTrue(config('erp.dte.enabled'));
        foreach (self::TABLAS as $tabla) {
            $this->assertGreaterThan(0, DB::table($tabla)->count(), 'Tabla sin datos: '.$tabla);
        }
        $this->assertEqualsCanonicalizing([
            'pendiente_aprobacion', 'aprobado', 'vigente', 'vencido', 'incobrable', 'refinanciado', 'cancelado', 'rechazado',
        ], Credito::distinct()->pluck('estado')->all());
        foreach (Credito::with('cuotas', 'pagos.aplicaciones')->get() as $credito) {
            if ($credito->cuotas->isNotEmpty()) {
                $this->assertEquals(round((float) $credito->monto_original, 2), round($credito->cuotas->sum('capital'), 2), $credito->numero);
                $this->assertSame($credito->fecha_vencimiento->toDateString(), $credito->cuotas->last()->fecha_vencimiento->toDateString());
            }
            $this->assertEquals(round((float) $credito->monto_original - $credito->pagos->sum('abono_capital'), 2), (float) $credito->saldo_actual);
            foreach ($credito->pagos as $pago) {
                $this->assertEquals((float) $pago->monto_total, round((float) $pago->abono_capital + (float) $pago->pago_interes + (float) $pago->pago_mora, 2));
                if ($credito->observaciones !== 'QA-HISTORICO | Escenario de pruebas integrales.') {
                    $this->assertEquals((float) $pago->monto_total, round($pago->aplicaciones->sum('monto_total'), 2));
                }
                $this->assertTrue($pago->fecha_pago->gte($credito->fecha_solicitud));
                $this->assertTrue($pago->fecha_pago->lte(today()));
            }
        }
        foreach (Venta::with('detalles')->get() as $venta) {
            $this->assertEquals(round($venta->detalles->sum('venta_gravada') + (float) $venta->total_iva, 2), round((float) $venta->total_pagar, 2));
            $this->assertNull($venta->sello_recepcion);
            $this->assertNull($venta->json_firmado);
            $this->assertNotSame('procesado', $venta->estado_dte);
        }
        foreach (Cliente::withTrashed()->get() as $cliente) {
            if ($cliente->dui !== null) {
                $this->assertTrue(DatosSalvador::duiValido($cliente->dui), $cliente->codigo);
            }
            if ($cliente->nit !== null) {
                $this->assertTrue(DatosSalvador::nitValido($cliente->nit, $cliente->tipo === 'natural'), $cliente->codigo);
            }
            $this->assertMatchesRegularExpression('/^2[0-9]{3}-[0-9]{4}$/', $cliente->telefono);
            $this->assertMatchesRegularExpression('/^[67][0-9]{3}-[0-9]{4}$/', $cliente->celular);
        }
        foreach (Fiador::all() as $fiador) {
            $this->assertTrue(DatosSalvador::duiValido($fiador->dui));
        }
        foreach (ClienteJuridico::all() as $juridico) {
            $balance = $juridico->balance_general;
            $activo = array_sum($balance['activo_corriente']) + $balance['activo_no_corriente']['propiedad_planta_equipo'] - $balance['activo_no_corriente']['depreciacion_acumulada'] + $balance['activo_no_corriente']['otros_activos'];
            $this->assertEquals($activo, array_sum($balance['pasivo_corriente']) + array_sum($balance['pasivo_no_corriente']) + array_sum($balance['patrimonio']));
            $this->assertTrue(DatosSalvador::duiValido($juridico->dui_representante));
            $this->assertNotEmpty($juridico->ratios_financieros);
        }
        $this->assertLessThan(0, Cliente::where('nombre', 'like', '%PERDIDA%')->first()->datosJuridicos->ratios_financieros['resumen']['utilidad_neta']);
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_escenarios_de_pagos_mora_e_incobrables_tienen_resultados_esperados(): void
    {
        $this->seed(PruebasIntegralesSeeder::class);
        $parcial = $this->escenario('PARCIAL-300');
        $this->assertSame('829.98', $parcial->saldo_actual);
        $this->assertSame('188.33', $parcial->cuotas->first()->monto_pagado);
        $this->assertSame('111.67', $parcial->cuotas[1]->monto_pagado);
        $this->assertSame(76.66, $parcial->cuotas[1]->saldo_por_pagar);
        $this->assertSame('0.01', $this->escenario('REDONDEO')->saldo_actual);
        $this->assertSame('cancelado', $this->escenario('LIQUIDADO')->estado);
        $this->assertSame('vigente', $this->escenario('FRECUENTE-AUTO')->estado);
        $this->assertSame('pendiente_aprobacion', $this->escenario('NUEVO-REVISION')->estado);
        $this->assertSame('pendiente_aprobacion', $this->escenario('SIN-DUI')->estado);
        $this->assertSame('vigente', $this->escenario('CON-FIADOR')->estado);
        $this->assertSame(1, $this->escenario('CON-FIADOR')->fiadores()->count());
        foreach (['A', 'B', 'C', 'D'] as $letra) {
            $credito = $this->escenario('MORA-'.$letra);
            $this->assertSame($letra, $credito->cliente->clasificacion_cobro);
            $this->assertSame('vencido', $credito->estado);
            $this->assertGreaterThan(0, $credito->mora_acumulada);
        }
        $this->assertEqualsCanonicalizing(['efectivo', 'transferencia', 'cheque', 'tarjeta'], Pago::distinct()->pluck('forma_pago')->all());
        $this->assertSame(4, CuentaIncobrable::where('abierta', true)->count());
        $this->assertSame(2, CuentaIncobrable::whereNotNull('reactivado_at')->count());
        $this->assertSame(2, $this->escenario('REINCIDENTE')->cuentasIncobrables()->count());
        $this->assertSame('vencido', $this->escenario('REACTIVADO')->estado);
        $this->assertGreaterThan(0, $this->escenario('REACTIVADO')->pagos()->count());
        $this->assertSame('D', Cliente::where('codigo', 'QA-DOS-INCOBRABLES')->first()->clasificacion_cobro);
        $historico = $this->escenario('HISTORICO');
        $antes = $historico->saldo_actual;
        $this->assertSame(1, app(ConciliacionPagosService::class)->conciliar($historico));
        $this->assertSame($antes, $historico->fresh()->saldo_actual);
        $this->assertSame(0, app(ConciliacionPagosService::class)->conciliar($historico));
    }

    public function test_existencias_movimientos_y_depreciaciones_concilian(): void
    {
        $this->seed(PruebasIntegralesSeeder::class);
        $saldos = [];
        foreach (MovimientoInventario::with('detalles')->get() as $movimiento) {
            $this->assertEqualsWithDelta((float) $movimiento->costo_total, (float) $movimiento->detalles->sum('costo_total'), 0.0001);
            foreach ($movimiento->detalles as $detalle) {
                foreach (['bodega_origen_id' => -1, 'bodega_destino_id' => 1] as $campo => $signo) {
                    if ($movimiento->$campo) {
                        $clave = $detalle->producto_id.'|'.$movimiento->$campo.'|'.$detalle->lote;
                        $saldos[$clave] = ($saldos[$clave] ?? 0) + $signo * (float) $detalle->cantidad;
                    }
                }
            }
        }
        foreach (Existencia::all() as $existencia) {
            $clave = $existencia->producto_id.'|'.$existencia->bodega_id.'|'.$existencia->lote;
            $this->assertEqualsWithDelta($saldos[$clave], (float) $existencia->cantidad, 0.0001);
            $this->assertGreaterThanOrEqual(0, (float) $existencia->cantidad);
        }
        $this->assertEquals(0, Producto::where('codigo', 'QA-AGOTADO')->first()->stock_total);
        $this->assertEquals(2.5, Producto::where('codigo', 'QA-BAJO')->first()->stock_total);
        $this->assertEqualsCanonicalizing(['entrada', 'salida', 'transferencia', 'ajuste'], MovimientoInventario::distinct()->pluck('tipo')->all());
        $this->assertEqualsCanonicalizing(['activo', 'depreciado', 'vendido', 'donado', 'botado'], ActivoFijo::distinct()->pluck('estado')->all());
        foreach (ActivoFijo::with('depreciaciones')->get() as $activo) {
            $this->assertEqualsWithDelta((float) $activo->depreciacion_acumulada, (float) $activo->depreciaciones->sum('monto_depreciado'), 0.0002);
            $this->assertLessThanOrEqual((float) $activo->valor_sujeto_depreciacion, (float) $activo->depreciacion_acumulada);
            $this->assertTrue($activo->depreciaciones->every(fn ($d) => $d->fecha_calculo->lt(today()->startOfMonth())));
        }
    }

    public function test_puede_repetirse_y_respeta_datos_usuarios_roles_y_configuracion_existentes(): void
    {
        $existente = User::factory()->create();
        $role = Role::create(['name' => 'operador-existente', 'guard_name' => 'web']);
        $existente->assignRole($role);
        $configuracion = ConfiguracionDTE::create(['nombre_comercial' => 'Emisor existente', 'ambiente' => '00']);
        $institucion = Institucion::create(['codigo' => 'EX01', 'nombre' => 'Institución existente']);
        $unidad = Unidad::create(['institucion_id' => $institucion->id, 'codigo' => 'EX01', 'nombre' => 'Unidad existente']);
        $categoria = CategoriaActivo::create(['codigo' => 'EX01', 'nombre' => 'Categoría existente', 'porcentaje_depreciacion' => 20]);
        $activo = app(ActivoFijoService::class)->registrarActivo([
            'unidad_id' => $unidad->id, 'categoria_id' => $categoria->id, 'nombre' => 'Activo existente',
            'fecha_adquisicion' => today()->subYear()->toDateString(), 'valor_adquisicion' => 500,
        ]);
        $antes = [$existente->fresh()->getAttributes(), $configuracion->fresh()->getAttributes(), $activo->fresh()->getAttributes(), $role->fresh()->getAttributes()];
        $this->seed(PruebasIntegralesSeeder::class);
        $this->assertSame($antes, [$existente->fresh()->getAttributes(), $configuracion->fresh()->getAttributes(), $activo->fresh()->getAttributes(), $role->fresh()->getAttributes()]);
        $this->assertSame(0, $activo->depreciaciones()->count());
        $cliente = Cliente::where('codigo', 'QA-SIN-ABONOS')->firstOrFail();
        $cliente->update(['direccion' => 'Edición manual que debe conservarse']);
        $snapshot = [];
        foreach (self::TABLAS as $tabla) {
            $snapshot[$tabla] = DB::table($tabla)->get()->map(fn ($row) => (array) $row)->all();
        }
        $this->seed(PruebasIntegralesSeeder::class);
        foreach ($snapshot as $tabla => $datos) {
            $this->assertEqualsCanonicalizing($datos, DB::table($tabla)->get()->map(fn ($row) => (array) $row)->all(), $tabla);
        }
        $this->assertSame('Edición manual que debe conservarse', $cliente->fresh()->direccion);
        $admin = User::where('email', 'qa.admin@example.test')->firstOrFail();
        $this->assertTrue(Hash::check(PruebasIntegralesSeeder::PASSWORD, $admin->password));
        $this->assertTrue($admin->can('incobrables.classify'));
        $this->assertFalse(User::where('email', 'qa.lector@example.test')->first()->can('cobros.process'));
        $this->assertSame(0, User::where('email', 'qa.sinpermisos@example.test')->first()->getAllPermissions()->count());
        $this->assertSame(0, app(ActivoFijoService::class)->calcularDepreciacionMasiva(today()->subMonth(), []));
        $this->assertSame(1, app(ActivoFijoService::class)->calcularDepreciacionMasiva(today()->subMonth()));
        $this->assertSame(1, $activo->depreciaciones()->count());
    }

    public function test_error_revierte_todo_y_restaura_la_fecha_y_la_configuracion(): void
    {
        config(['erp.dte.enabled' => true]);
        $fechaOriginal = Carbon::getTestNow();
        $this->mock(FacturacionService::class)->shouldReceive('procesarVenta')->once()->andThrow(new RuntimeException('Fallo de prueba controlado'));
        try {
            $this->seed(PruebasIntegralesSeeder::class);
            $this->fail('Se esperaba una reversión completa.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo de prueba controlado', $e->getMessage());
        }
        $this->assertTrue(config('erp.dte.enabled'));
        $this->assertEquals($fechaOriginal, Carbon::getTestNow());
        foreach (array_diff(self::TABLAS, ['permissions']) as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), $tabla);
        }
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_los_datos_se_pueden_consultar_en_los_modulos_y_reimprimir_recibos(): void
    {
        $this->seed(PruebasIntegralesSeeder::class);
        $admin = User::where('email', 'qa.admin@example.test')->firstOrFail();
        $this->actingAs($admin);
        foreach (['/dashboard', '/clientes', '/creditos', '/cobros', '/incobrables', '/inventario', '/facturacion/pos', '/activos-fijos', '/configuracion/catalogos', '/vendedores'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (Credito::all() as $credito) {
            $this->get(route('creditos.show', $credito))->assertOk();
        }
        foreach (Cliente::all() as $cliente) {
            $this->get(route('clientes.show', $cliente))->assertOk();
        }
        $pago = $this->escenario('PARCIAL-300')->pagos()->firstOrFail();
        $this->get(route('creditos.pagos.recibo', [$pago->credito_id, $pago]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('facturacion.pdf', $this->escenario('PARCIAL-300')->venta_id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_se_rechaza_en_produccion_sin_escribir_datos(): void
    {
        $entorno = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            (new PruebasIntegralesSeeder)->run();
            $this->fail('Se permitió cargar datos de pruebas en producción.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('nunca en producción', $e->getMessage());
        } finally {
            $this->app['env'] = $entorno;
        }
        $this->assertDatabaseCount('users', 0);
    }

    public static function fechasLimite(): array
    {
        return [['2027-01-31'], ['2028-02-29']];
    }

    #[DataProvider('fechasLimite')]
    public function test_los_escenarios_siguen_siendo_validos_en_fin_de_mes_y_anio_bisiesto(string $fecha): void
    {
        $this->travelTo(Carbon::parse($fecha.' 12:00:00'));
        try {
            $this->seed(PruebasIntegralesSeeder::class);
            foreach (['A', 'B', 'C', 'D'] as $letra) {
                $this->assertSame($letra, $this->escenario('MORA-'.$letra)->cliente->clasificacion_cobro);
            }
            foreach (['FIN-MES', 'BISIESTO', 'REF-NUEVO'] as $caso) {
                $credito = $this->escenario($caso);
                $this->assertSame($credito->fecha_vencimiento->toDateString(), $credito->cuotas->last()->fecha_vencimiento->toDateString());
                $this->assertEquals((float) $credito->monto_original, round($credito->cuotas->sum('capital'), 2));
            }
            $this->assertTrue(now()->isSameDay($fecha));
        } finally {
            $this->travelBack();
        }
    }
}

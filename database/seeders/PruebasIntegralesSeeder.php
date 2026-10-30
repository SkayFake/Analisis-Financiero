<?php

namespace Database\Seeders;

use App\Models\Bodega;
use App\Models\Cartera;
use App\Models\Categoria;
use App\Models\CategoriaActivo;
use App\Models\Cliente;
use App\Models\ClienteJuridico;
use App\Models\CondicionCredito;
use App\Models\ConfiguracionDTE;
use App\Models\Credito;
use App\Models\Embargo;
use App\Models\EventoInvalidacion;
use App\Models\Institucion;
use App\Models\LoteContingencia;
use App\Models\Marca;
use App\Models\MovimientoInventario;
use App\Models\PoliticaCobro;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Refinanciamiento;
use App\Models\Unidad;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Vendedor;
use App\Models\Venta;
use App\Models\Zona;
use App\Services\ActivoFijoService;
use App\Services\CreditoService;
use App\Services\FacturacionService;
use App\Services\IncobrableService;
use App\Services\InventarioService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Datos sintéticos completos. Ejecutar explícitamente; nunca borra ni reinicia tablas. */
class PruebasIntegralesSeeder extends Seeder
{
    public const PASSWORD = 'PruebasSV!2026';

    private User $admin;

    private Bodega $central;

    private Bodega $sucursal;

    private Producto $producto;

    private Proveedor $proveedor;

    private array $zonas = [];

    private array $vendedores = [];

    private array $carteras = [];

    private array $condiciones = [];

    private PoliticaCobro $politica;

    private int $identidad = 77000000;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('PruebasIntegralesSeeder contiene datos y accesos de prueba. Ejecútelo en un entorno local o de pruebas, nunca en producción.');
        }
        $dteAnterior = config('erp.dte.enabled');
        config(['erp.dte.enabled' => false]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        try {
            $creado = DB::transaction(function () {
                // Serializar ejecuciones simultáneas del mismo conjunto en PostgreSQL.
                if (DB::getDriverName() === 'pgsql') {
                    DB::select("SELECT pg_advisory_xact_lock(hashtext('PruebasIntegralesSeeder:v1'))");
                }
                if (User::where('email', 'qa.admin@example.test')->exists()) {
                    $this->call(VendedoresPruebasSeeder::class);
                    return false;
                }
                $this->usuarios();
                $this->catalogos();
                $this->inventario();
                $this->clientesJuridicos();
                $this->creditos();
                $this->activos();
                $this->simulacionesDte();
                $this->call(VendedoresPruebasSeeder::class);

                return true;
            });
        } finally {
            config(['erp.dte.enabled' => $dteAnterior]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
        $this->command?->info($creado
            ? 'Datos QA creados. Consulte docs/pruebas-integrales.md para escenarios, accesos y resultados esperados.'
            : 'El conjunto QA ya existe. Se conservaron sus datos y las pruebas manuales realizadas; no se duplicó ni restableció nada.');
        $this->command?->table(['Escenario', 'Crédito', 'Estado', 'Saldo capital'],
            Credito::where('observaciones', 'like', 'QA-%')->orderBy('id')->get()->map(fn ($credito) => [
                Str::before($credito->observaciones, ' |'), $credito->numero, $credito->estado, $credito->saldo_actual,
            ])->all());
        $this->command?->info('Acceso: qa.admin@example.test. Contraseña de prueba y demás perfiles: docs/pruebas-integrales.md.');
    }

    private function usuarios(): void
    {
        $permisos = [
            'clientes.view', 'clientes.create', 'clientes.edit', 'clientes.delete',
            'creditos.view', 'creditos.create', 'creditos.approve', 'creditos.manage', 'creditos.refinanciar', 'creditos.embargar',
            'cobros.view', 'cobros.process', 'cobros.manage', 'incobrables.view', 'incobrables.classify', 'incobrables.reactivate',
            'inventario.view', 'inventario.create', 'inventario.edit', 'inventario.delete',
            'facturacion.view', 'facturacion.emitir', 'facturacion.anular', 'facturacion.modificar_precio',
            'activos.view', 'activos.create', 'activos.edit', 'activos.baja',
            'usuarios.view', 'usuarios.create', 'usuarios.edit', 'usuarios.delete',
            'roles.manage', 'configuracion.manage', 'reportes.view', 'reportes.export',
            'vendedores.view', 'vendedores.manage', 'vendedores.metas', 'vendedores.assign', 'vendedores.gestionar', 'vendedores.export', 'vendedores.audit',
        ];
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }
        $roles = [
            'admin' => $permisos,
            'gerente' => ['clientes.view', 'creditos.view', 'creditos.create', 'creditos.approve', 'creditos.manage', 'cobros.view', 'cobros.process', 'cobros.manage', 'incobrables.view', 'incobrables.classify', 'incobrables.reactivate'],
            'cobros' => ['clientes.view', 'creditos.view', 'cobros.view', 'cobros.process', 'incobrables.view'],
            'facturacion' => ['facturacion.view', 'facturacion.emitir', 'clientes.view', 'creditos.view', 'inventario.view'],
            'inventario' => ['inventario.view', 'inventario.create', 'inventario.edit'],
            'activos' => ['activos.view', 'activos.create', 'activos.edit', 'activos.baja'],
            'lector' => ['clientes.view', 'creditos.view', 'cobros.view', 'incobrables.view', 'inventario.view', 'facturacion.view', 'activos.view'],
            'sinpermisos' => [],
        ];
        foreach ($roles as $nombre => $asignados) {
            $role = Role::firstOrCreate(['name' => 'qa-'.$nombre, 'guard_name' => 'web']);
            // Solo se modifican roles propios de este conjunto de pruebas.
            $role->syncPermissions($asignados);
            $user = User::create([
                'name' => 'QA '.ucfirst($nombre), 'email' => 'qa.'.$nombre.'@example.test',
                'password' => Hash::make(self::PASSWORD), 'email_verified_at' => now(),
            ]);
            $user->assignRole($role);
            if ($nombre === 'admin') {
                $this->admin = $user;
                $user->givePermissionTo('reportes.export');
            }
        }
    }

    private function catalogos(): void
    {
        foreach (['San Salvador', 'Santa Ana', 'San Miguel'] as $i => $nombre) {
            $this->zonas[] = Zona::create(['codigo' => 'QA-Z'.($i + 1), 'nombre' => 'QA '.$nombre]);
            $this->vendedores[] = Vendedor::create([
                'codigo' => 'QA-V'.($i + 1), 'nombre' => 'QA Vendedor '.$nombre,
                'telefono' => '7000-100'.($i + 1), 'email' => 'vendedor'.($i + 1).'@example.test',
                'zona_id' => $this->zonas[$i]->id, 'meta_mensual' => 5000 + $i * 2500,
            ]);
            $this->carteras[] = Cartera::create([
                'codigo' => 'QA-CAR'.($i + 1), 'nombre' => 'QA Cartera '.$nombre,
                'vendedor_id' => $this->vendedores[$i]->id,
            ]);
        }
        Zona::create(['codigo' => 'QA-Z0', 'nombre' => 'QA Zona inactiva', 'activa' => false]);
        Vendedor::create(['codigo' => 'QA-V0', 'nombre' => 'QA Vendedor inactivo', 'activo' => false, 'telefono' => '7000-1000']);
        Cartera::create(['codigo' => 'QA-CAR0', 'nombre' => 'QA Cartera inactiva', 'activa' => false]);
        $this->politica = PoliticaCobro::create([
            'nombre' => 'QA Cobro con tres días de gracia', 'dias_gracia' => 3,
            'dias_cobro_30' => 30, 'dias_cobro_60' => 60, 'interes_moratorio' => 12, 'dias_incobrable' => 180,
        ]);
        PoliticaCobro::create(['nombre' => 'QA Política inactiva', 'activa' => false]);
        foreach ([
            'SIN-INTERES' => ['tasa_interes_anual' => 0],
            'INTERES' => ['tasa_interes_anual' => 18],
            'SEMANAL' => ['frecuencia_pago' => 'semanal', 'tasa_interes_anual' => 12],
            'QUINCENAL' => ['frecuencia_pago' => 'quincenal', 'tasa_interes_anual' => 12],
            'MANUAL' => ['monto_auto_aprobado' => 0],
            'EXPEDIENTE' => ['requiere_dui' => true, 'requiere_fiador' => true, 'monto_auto_aprobado' => 0],
            'FRECUENTE' => ['compras_minimas_cliente_frecuente' => 1],
            'INACTIVA' => ['activa' => false],
        ] as $clave => $opciones) {
            $this->condiciones[$clave] = CondicionCredito::create(array_replace([
                'nombre' => 'QA '.$clave, 'descripcion' => 'Condición sintética para pruebas, no una tasa legal de referencia.',
                'activa' => true, 'tasa_interes_anual' => 0, 'tasa_mora_mensual' => 3, 'dias_gracia' => 3,
                'frecuencia_pago' => 'mensual', 'plazo_maximo_cuotas' => 120, 'monto_auto_aprobado' => 10000,
                'compras_minimas_cliente_frecuente' => 0, 'requiere_dui' => false, 'requiere_fiador' => false,
            ], $opciones));
        }
    }

    private function inventario(): void
    {
        $this->proveedor = Proveedor::create([
            'nombre' => 'QA Suministros de prueba', 'nit' => $this->nit(301), 'nrc' => '930001',
            'direccion' => 'Dirección ficticia, San Salvador', 'telefono' => '2200-1001',
            'email' => 'proveedor@example.test', 'dias_credito' => 30,
        ]);
        Proveedor::create(['nombre' => 'QA Proveedor inactivo', 'nit' => $this->nit(302), 'activo' => false, 'dias_credito' => 0]);
        $this->central = Bodega::create(['nombre' => 'QA Bodega central', 'direccion' => 'San Salvador']);
        $this->sucursal = Bodega::create(['nombre' => 'QA Bodega sucursal', 'direccion' => 'Santa Ana']);
        Bodega::create(['nombre' => 'QA Bodega inactiva', 'activa' => false]);
        $categoria = Categoria::create(['nombre' => 'QA Mercadería']);
        $marca = Marca::create(['nombre' => 'QA Marca de prueba']);
        $unidad = UnidadMedida::create(['nombre' => 'QA Unidad', 'abreviatura' => 'UND']);
        $kilos = UnidadMedida::create(['nombre' => 'QA Kilogramo', 'abreviatura' => 'KG']);
        $service = app(InventarioService::class);
        foreach ([
            'BASE' => ['PROMEDIO', 200, false], 'ULTIMA' => ['ULTIMA_COMPRA', 20, false],
            'ALIGACION' => ['PROMEDIO_ALIGACION', 20, false], 'PEPS' => ['PEPS', 20, false],
            'BAJO' => ['PROMEDIO', 2, false], 'AGOTADO' => ['PROMEDIO', 1, false],
            'INACTIVO' => ['PROMEDIO', 5, false], 'FRACCION' => ['PROMEDIO', 12.5, false],
            'VENCE' => ['PEPS', 10, true], 'VENCIDO' => ['PEPS', 10, true],
        ] as $clave => [$metodo, $cantidad, $perecedero]) {
            $producto = Producto::create([
                'codigo' => 'QA-'.$clave, 'nombre' => 'QA Producto '.$clave,
                'categoria_id' => $categoria->id, 'marca_id' => $marca->id,
                'unidad_medida_id' => ($clave === 'FRACCION' ? $kilos : $unidad)->id,
                'proveedor_id' => $this->proveedor->id, 'metodo_costeo' => $metodo,
                'costo_unitario' => 50, 'precio_venta' => 100, 'stock_minimo' => 5,
                'stock_maximo' => 500, 'tiempo_espera_dias' => 7, 'perecedero' => $perecedero,
            ]);
            $detalle = ['producto_id' => $producto->id, 'cantidad' => $cantidad, 'costo_unitario' => 50, 'lote' => 'QA-'.$clave.'-A'];
            if ($perecedero) {
                $detalle['fecha_vencimiento'] = today()->addDays($clave === 'VENCIDO' ? -5 : 10)->toDateString();
            }
            $service->registrarEntrada([
                'bodega_id' => $this->central->id, 'proveedor_id' => $this->proveedor->id,
                'fecha' => today()->subDays(420)->toDateString(), 'referencia' => 'QA Apertura '.$clave, 'detalles' => [$detalle],
            ], $this->admin->id);
            if (in_array($clave, ['ULTIMA', 'ALIGACION', 'PEPS'])) {
                $service->registrarEntrada([
                    'bodega_id' => $this->central->id, 'proveedor_id' => $this->proveedor->id,
                    'fecha' => today()->subDays(400)->toDateString(), 'referencia' => 'QA Segundo costo '.$clave,
                    'detalles' => [['producto_id' => $producto->id, 'cantidad' => 10, 'costo_unitario' => 60, 'lote' => 'QA-'.$clave.'-B']],
                ], $this->admin->id);
            }
            if ($clave === 'BASE') {
                $this->producto = $producto;
                $service->transferirBodega([
                    'bodega_origen_id' => $this->central->id, 'bodega_destino_id' => $this->sucursal->id,
                    'fecha' => today()->subDays(390)->toDateString(), 'referencia' => 'QA Traslado entre sucursales',
                    'detalles' => [['producto_id' => $producto->id, 'cantidad' => 20, 'lote' => 'QA-BASE-A']],
                ], $this->admin->id);
            } elseif ($clave === 'AGOTADO') {
                $service->registrarSalida([
                    'bodega_id' => $this->central->id, 'referencia' => 'QA Merma con stock agotado',
                    'detalles' => [['producto_id' => $producto->id, 'cantidad' => 1]],
                ], $this->admin->id);
            } elseif ($clave === 'INACTIVO') {
                $producto->update(['activo' => false]);
            } elseif ($clave === 'BAJO') {
                $ajuste = $service->registrarEntrada([
                    'bodega_id' => $this->central->id, 'referencia' => 'QA Ajuste positivo por conteo físico',
                    'detalles' => [['producto_id' => $producto->id, 'cantidad' => 0.5, 'costo_unitario' => 50, 'lote' => 'QA-BAJO-A']],
                ], $this->admin->id);
                // No hay una acción de ajuste en la interfaz; conservar su efecto y detalle de inventario.
                $ajuste->update(['tipo' => 'ajuste', 'numero_documento' => MovimientoInventario::generarNumeroDocumento('ajuste')]);
            }
        }
    }

    private function cliente(string $caso, array $opciones = []): Cliente
    {
        do {
            $dui = $this->dui(++$this->identidad);
            $digitos = str_replace('-', '', $dui);
        } while (Cliente::withTrashed()->whereRaw("REPLACE(dui, '-', '') = ? OR REPLACE(nit, '-', '') = ?", [$digitos, $digitos])->exists());
        $i = $this->identidad % 3;

        return Cliente::create(array_replace([
            'codigo' => strlen($caso) <= 17 ? 'QA-'.$caso : 'QA-'.substr($caso, 0, 11).'-'.substr(sha1($caso), 0, 5),
            'tipo' => 'natural', 'nombre' => 'QA '.$caso,
            'direccion' => 'Dirección sintética, colonia de pruebas', 'departamento' => ['San Salvador', 'Santa Ana', 'San Miguel'][$i],
            'municipio' => ['San Salvador Centro', 'Santa Ana Centro', 'San Miguel Centro'][$i],
            'telefono' => '2200-2000', 'celular' => '7000-2000', 'email' => strtolower($caso).'@example.test',
            'dui' => $dui, 'nit' => $dui, 'estado_civil' => 'soltero', 'lugar_trabajo' => 'QA Empresa ficticia',
            'ingresos' => 1500, 'egresos' => 600, 'activo' => true,
            'zona_id' => $this->zonas[$i]->id, 'cartera_id' => $this->carteras[$i]->id, 'vendedor_id' => $this->vendedores[$i]->id,
            'observaciones' => 'Datos sintéticos para pruebas; no acreditan identidad ni registro oficial.',
        ], $opciones));
    }

    private function clientesJuridicos(): void
    {
        foreach (['SOLVENTE', 'PERDIDA', 'SIN-OPERACION'] as $i => $caso) {
            $cliente = $this->cliente('EMPRESA-'.$caso, [
                'tipo' => 'juridica', 'dui' => null, 'nit' => $this->nit(401 + $i), 'nrc' => '94000'.($i + 1),
                'nombre' => 'QA Empresa '.$caso.' S.A. de C.V.', 'estado_civil' => null, 'lugar_trabajo' => null,
                'ingresos' => $i === 2 ? 0 : 10000, 'egresos' => $i === 2 ? 0 : 5000,
            ]);
            $balance = ClienteJuridico::estructuraBalanceGeneral();
            $resultados = ClienteJuridico::estructuraEstadoResultados();
            if ($i < 2) {
                $balance['activo_corriente'] = ['efectivo' => 10000, 'cuentas_por_cobrar' => 15000, 'inventarios' => 20000, 'otros_activos_corrientes' => 0];
                $balance['activo_no_corriente']['propiedad_planta_equipo'] = 45000;
                $balance['pasivo_corriente']['cuentas_por_pagar'] = 12000;
                $balance['pasivo_no_corriente']['prestamos_largo_plazo'] = $i === 0 ? 18000 : 68000;
                $balance['patrimonio'] = ['capital_social' => 50000, 'reserva_legal' => 2000, 'utilidades_retenidas' => $i === 0 ? 8000 : -42000];
                $resultados = array_replace($resultados, [
                    'ingresos_operacionales' => 120000, 'costo_ventas' => $i === 0 ? 70000 : 110000,
                    'gastos_operacionales' => 12000, 'gastos_administrativos' => 8000,
                    'gastos_financieros' => 3000, 'impuesto_renta' => $i === 0 ? 8100 : 0,
                ]);
            }
            $datos = ClienteJuridico::create([
                'cliente_id' => $cliente->id, 'nombre_comercial' => $cliente->nombre, 'giro' => 'Comercio de mercadería de prueba',
                'representante_legal' => 'QA Representante de prueba', 'dui_representante' => $this->dui(78000001 + $i),
                'fecha_constitucion' => today()->subYears(5), 'fecha_balance' => today()->subMonth()->endOfMonth(),
                'numero_empleados' => $i === 2 ? 0 : 12, 'balance_general' => $balance, 'estado_resultados' => $resultados,
            ]);
            $datos->recalcularRatios();
        }
        $this->cliente('INACTIVO', ['activo' => false]);
        $this->cliente('ARCHIVADO')->delete();
        $this->cliente('NUEVO-SIN-DUI', ['dui' => null, 'nit' => null]);
    }

    private function venta(Cliente $cliente, float $monto = 100, string $tipo = '11', ?array $condiciones = null): Venta
    {
        return app(FacturacionService::class)->procesarVenta([
            'cliente_id' => $cliente->id, 'vendedor_id' => $cliente->vendedor_id,
            'bodega_id' => $this->central->id, 'tipo_documento' => $tipo,
            'condicion_operacion' => $condiciones === null ? '1' : '2', 'condiciones_credito' => $condiciones,
        ], [['producto_id' => $this->producto->id, 'cantidad' => 1, 'precio_unitario' => $monto]], $this->admin->id);
    }

    private function credito(string $caso, string $condicion = 'SIN-INTERES', int $cuotas = 6, int $antiguedad = 0, float $monto = 600, array $extras = [], ?Cliente $cliente = null): Credito
    {
        $cliente ??= $this->cliente($caso);
        $fecha = today()->subDays($antiguedad)->setTime(10, 0);

        return Carbon::withTestNow($fecha, function () use ($cliente, $caso, $condicion, $cuotas, $monto, $extras) {
            $venta = $this->venta($cliente, $monto, '11', array_replace([
                'condicion_credito_id' => $this->condiciones[$condicion]->id, 'numero_cuotas' => $cuotas,
                'fecha_primera_cuota' => now()->addMonthNoOverflow()->toDateString(),
                'cartera_id' => $cliente->cartera_id, 'politica_cobro_id' => $this->politica->id,
                'observaciones' => 'QA-'.$caso.' | Escenario de pruebas integrales.',
            ], $extras));

            return $venta->credito;
        });
    }

    private function pago(Credito $credito, float $monto, string $forma = 'efectivo', ?Carbon $fecha = null): void
    {
        Carbon::withTestNow($fecha ?? now(), fn () => app(CreditoService::class)->procesarPago($credito, [
            'monto' => $monto, 'forma_pago' => $forma, 'fecha_pago' => today()->toDateString(),
            'referencia' => 'QA-'.$forma, 'observaciones' => 'Pago sintético para reimpresión y conciliación.',
            'recibido_por' => $this->admin->id,
        ]));
    }

    private function actualizarMora(Credito $credito): void
    {
        app(CreditoService::class)->calcularMora($credito);
        foreach ($credito->cuotas()->get() as $cuota) {
            if ($cuota->fecha_vencimiento->lt(today()) && $cuota->saldo_por_pagar > 0 && $cuota->estado === 'pendiente') {
                $cuota->update(['estado' => 'vencida']);
            }
        }
        if ($credito->cuotas()->where('fecha_vencimiento', '<', today())->where('estado', '!=', 'pagada')->exists()) {
            $credito->update(['estado' => 'vencido']);
        }
        app(IncobrableService::class)->clasificarCliente($credito->cliente, $this->admin->id);
    }

    private function creditos(): void
    {
        $service = app(CreditoService::class);
        $incobrables = app(IncobrableService::class);
        $this->credito('SIN-ABONOS');
        $this->credito('UNA-CUOTA', cuotas: 1, monto: 100);
        $this->credito('MINIMO', cuotas: 1, monto: 0.01);
        $this->credito('FIN-MES', cuotas: 3, extras: ['fecha_primera_cuota' => today()->endOfMonth()->toDateString()]);
        $anioBisiesto = today()->year;
        while (! checkdate(2, 29, $anioBisiesto) || Carbon::create($anioBisiesto, 2, 29)->lt(today())) {
            $anioBisiesto++;
        }
        $this->credito('BISIESTO', cuotas: 3, extras: ['fecha_primera_cuota' => Carbon::create($anioBisiesto, 2, 29)->toDateString()]);
        $historico = $this->credito('HISTORICO', antiguedad: 10);
        $this->pago($historico, 25, fecha: $historico->fecha_solicitud->copy()->addDay());
        // Caso de datos anteriores a pago_cuotas: conceptos y saldos concilian; el servicio puede reconstruirlos.
        $historico->pagos()->firstOrFail()->aplicaciones()->delete();
        $parcial = $this->credito('PARCIAL-300', cuotas: 6, monto: 1129.98);
        $this->pago($parcial, 300);
        $conInteres = $this->credito('CON-INTERES', 'INTERES', monto: 1000);
        $this->pago($conInteres, 100, 'transferencia');
        $this->credito('SEMANAL', 'SEMANAL', 12);
        $this->credito('QUINCENAL', 'QUINCENAL', 8);
        $this->credito('PLAZO-120', cuotas: 120, monto: 120);
        $centavos = $this->credito('REDONDEO', cuotas: 3, monto: 100);
        $this->pago($centavos, 99.99, 'tarjeta');
        $liquidado = $this->credito('LIQUIDADO', 'INTERES');
        foreach (['efectivo', 'transferencia', 'cheque', 'tarjeta'] as $forma) {
            $this->pago($liquidado, 25, $forma);
        }
        $this->pago($liquidado, round($liquidado->cuotas()->get()->sum('saldo_por_pagar'), 2));
        $clienteFrecuente = $this->cliente('FRECUENTE');
        $anterior = $this->credito('ANTECEDENTE', cliente: $clienteFrecuente, antiguedad: 120);
        $this->pago($anterior, 600, fecha: $anterior->fecha_solicitud->copy()->addDays(20));
        $this->credito('FRECUENTE-AUTO', 'FRECUENTE', cliente: $clienteFrecuente);
        $this->credito('PENDIENTE', 'MANUAL');
        $this->credito('NUEVO-REVISION', 'FRECUENTE');
        $rechazado = $this->credito('RECHAZADO', 'MANUAL');
        $service->rechazarCredito($rechazado, 'QA Solicitud rechazada por capacidad de pago insuficiente.', $this->admin->id);
        $aprobado = $this->credito('APROBADO-TRANSITORIO', 'MANUAL');
        $aprobado->update(['estado' => 'aprobado', 'fecha_aprobacion' => today(), 'aprobado_por' => $this->admin->id, 'motivo_aprobacion' => 'QA Estado transitorio aprobado, pendiente de activación.']);
        $manual = $this->credito('APROBACION-MANUAL', 'MANUAL');
        $service->aprobarCredito($manual, 'QA Capacidad de pago y referencias revisadas manualmente.', $this->admin->id);
        $this->credito('EXPEDIENTE-INCOMPLETO', 'EXPEDIENTE', extras: ['dui_verificado' => false]);
        $this->credito('SIN-DUI', 'EXPEDIENTE', cliente: Cliente::where('codigo', 'QA-NUEVO-SIN-DUI')->firstOrFail());
        $fiador = ['nombre' => 'QA Fiador de prueba', 'dui' => $this->dui(79000001), 'telefono' => '7000-9000', 'direccion' => 'Dirección ficticia del fiador'];
        $expediente = $this->credito('CON-FIADOR', 'EXPEDIENTE', extras: ['dui_verificado' => true, 'referencia_verificada' => true, 'fiador' => $fiador]);
        $service->aprobarCredito($expediente, 'QA Expediente completo con DUI y fiador verificados.', $this->admin->id);
        foreach (['MORA-A' => 40, 'MORA-B' => 75, 'MORA-C' => 105, 'MORA-D' => 145] as $caso => $dias) {
            $credito = $this->credito($caso, 'INTERES', antiguedad: $dias);
            $this->actualizarMora($credito);
            if ($caso === 'MORA-B') {
                $this->pago($credito, 20, 'cheque');
            }
        }
        $incobrable = $this->credito('INCOBRABLE', 'INTERES', antiguedad: 260);
        $this->pago($incobrable, 50, fecha: $incobrable->fecha_solicitud->copy()->addDays(25));
        $this->actualizarMora($incobrable);
        $incobrables->declararIncobrable($incobrable, 'QA Gestión de cobro agotada; saldo con interés, mora y un abono anterior.', $this->admin->id);
        $reactivado = $this->credito('REACTIVADO', antiguedad: 200);
        Carbon::withTestNow(today()->subDays(5), function () use ($reactivado, $incobrables) {
            $this->actualizarMora($reactivado);
            $incobrables->declararIncobrable($reactivado, 'QA Primer traslado por ausencia de recuperación.', $this->admin->id);
        });
        $incobrables->reactivarCredito($reactivado, 'QA Cliente retoma convenio de pago sin modificar el plan.', $this->admin->id);
        $this->actualizarMora($reactivado);
        $this->pago($reactivado, 100, 'transferencia');
        $reincidente = $this->credito('REINCIDENTE', antiguedad: 230);
        $this->actualizarMora($reincidente);
        foreach (['QA Primer traslado por impago documentado.', 'QA Segundo traslado después de incumplir el convenio.'] as $i => $motivo) {
            $incobrables->declararIncobrable($reincidente, $motivo, $this->admin->id);
            if ($i === 0) {
                $incobrables->reactivarCredito($reincidente, 'QA Se acordó reanudar el cobro del crédito.', $this->admin->id);
                $this->pago($reincidente, 30);
            }
        }
        $clienteMultiple = $this->cliente('DOS-INCOBRABLES');
        foreach (['MULTIPLE-1', 'MULTIPLE-2'] as $caso) {
            $credito = $this->credito($caso, antiguedad: 250, cliente: $clienteMultiple);
            $this->actualizarMora($credito);
            $incobrables->declararIncobrable($credito, 'QA Cliente con más de un crédito incobrable simultáneo.', $this->admin->id);
        }
        $this->auxiliaresCredito();
        foreach (['SOLVENTE', 'PERDIDA', 'SIN-OPERACION'] as $caso) {
            $cliente = Cliente::where('nombre', 'QA Empresa '.$caso.' S.A. de C.V.')->firstOrFail();
            $this->venta($cliente, 100, '03');
            if ($caso === 'SOLVENTE') {
                $this->credito('CORPORATIVO', monto: 2500, cliente: $cliente);
            }
        }
        $contado = $this->cliente('CONTADO');
        $this->venta($contado, 99.99, '01');
        $this->venta($contado, 10, '11');
    }

    /** Fixtures de tablas sin un flujo de operación expuesto en la aplicación. */
    private function auxiliaresCredito(): void
    {
        $original = $this->credito('REFINANCIADO', antiguedad: 150);
        $this->actualizarMora($original);
        $interesPendiente = (float) $original->cuotas()->sum('interes');
        $monto = round((float) $original->saldo_actual + $interesPendiente + (float) $original->fresh()->mora_acumulada, 2);
        $nuevo = $original->replicate();
        $nuevo->fill([
            'numero' => 'QA-REF-NUEVO', 'venta_id' => null, 'monto_original' => $monto, 'saldo_actual' => $monto,
            'interes_acumulado' => 0, 'mora_acumulada' => 0, 'dias_mora' => 0, 'estado' => 'vigente',
            'fecha_solicitud' => today(), 'fecha_aprobacion' => today(), 'fecha_cancelacion' => null,
            'fecha_primera_cuota' => today()->addMonthNoOverflow(),
            'fecha_vencimiento' => app(CreditoService::class)->calcularFechaVencimiento(today()->addMonthNoOverflow(), 6, 'mensual', true),
            'aprobado_por' => $this->admin->id, 'observaciones' => 'QA-REF-NUEVO | Fixture de refinanciamiento; no representa otra venta de mercadería.',
        ])->save();
        app(CreditoService::class)->generarPlanPago($nuevo);
        $original->update(['estado' => 'refinanciado']);
        Refinanciamiento::create([
            'credito_original_id' => $original->id, 'credito_nuevo_id' => $nuevo->id,
            'saldo_anterior' => $original->saldo_actual, 'nuevo_monto' => $monto, 'intereses_pendientes' => $interesPendiente,
            'motivo' => 'QA Fixture para consultar la relación entre crédito original y refinanciado; incluye mora registrada.',
            'fecha' => today(), 'aprobado_por' => $this->admin->id,
        ]);
        foreach (['activo', 'ejecutado', 'liberado'] as $estado) {
            $credito = $this->credito('EMBARGO-'.strtoupper($estado), antiguedad: 180);
            $this->actualizarMora($credito);
            Embargo::create([
                'credito_id' => $credito->id, 'fecha_embargo' => today()->subDays(10),
                'descripcion_bienes' => 'QA Bien mueble ficticio sujeto a gestión de recuperación.', 'valor_estimado' => 300,
                'estado' => $estado, 'registrado_por' => $this->admin->id,
                'observaciones' => 'Fixture de consulta. No registra venta de bienes ni abono automático al crédito.',
            ]);
        }
    }

    private function activos(): void
    {
        $institucion = Institucion::create(['codigo' => 'QA01', 'nombre' => 'QA Institución de prueba']);
        $unidad = Unidad::create(['institucion_id' => $institucion->id, 'codigo' => 'QA01', 'nombre' => 'QA Administración']);
        Unidad::create(['institucion_id' => $institucion->id, 'codigo' => 'QA02', 'nombre' => 'QA Operaciones']);
        $categorias = [];
        foreach (['EDIF' => 5, 'MAQU' => 20, 'VEHI' => 25, 'OTRO' => 50] as $codigo => $porcentaje) {
            $codigoQa = ['EDIF' => 'QEDF', 'MAQU' => 'QMAQ', 'VEHI' => 'QVEH', 'OTRO' => 'QOTR'][$codigo];
            $categorias[$codigo] = CategoriaActivo::create(['codigo' => $codigoQa, 'nombre' => 'QA '.$codigo, 'porcentaje_depreciacion' => $porcentaje]);
        }
        $service = app(ActivoFijoService::class);
        $activos = [];
        foreach ([
            'NUEVO' => ['OTRO', 0, 12], 'USADO-1' => ['VEHI', 1, 12], 'USADO-2' => ['VEHI', 2, 12],
            'USADO-3' => ['VEHI', 3, 12], 'USADO-4' => ['VEHI', 4, 12], 'IMPORTADO' => ['MAQU', 0, 12],
            'EDIFICIO' => ['EDIF', 0, 12], 'DEPRECIADO' => ['OTRO', 0, 36],
            'VENDIDO' => ['OTRO', 0, 12], 'DONADO' => ['OTRO', 0, 12], 'BOTADO' => ['OTRO', 0, 12],
        ] as $caso => [$categoria, $aniosUso, $meses]) {
            $activos[$caso] = $service->registrarActivo([
                'unidad_id' => $unidad->id, 'categoria_id' => $categorias[$categoria]->id, 'nombre' => 'QA Activo '.$caso,
                'descripcion' => 'Activo sintético para pruebas de depreciación y baja.',
                'fecha_adquisicion' => today()->startOfMonth()->subMonthsNoOverflow($meses)->addDays(9)->toDateString(),
                'valor_adquisicion' => 1200, 'valor_residual' => 100, 'es_usado' => $aniosUso > 0,
                'anios_uso_previo' => $aniosUso, 'maquinaria_importada_exenta' => $caso === 'IMPORTADO',
            ]);
        }
        // Calcular únicamente sobre los activos QA; jamás tocar activos existentes.
        for ($i = 36; $i >= 1; $i--) {
            $service->calcularDepreciacionMasiva(today()->startOfMonth()->subMonthsNoOverflow($i), array_map(fn ($a) => $a->id, $activos));
        }
        foreach (['VENDIDO', 'DONADO', 'BOTADO'] as $caso) {
            $service->desincorporarActivo($activos[$caso]->id, strtolower($caso), 'QA Baja documentada por '.strtolower($caso));
        }
    }

    private function simulacionesDte(): void
    {
        if (! ConfiguracionDTE::exists()) {
            ConfiguracionDTE::create([
                'nit' => $this->nit(501), 'nrc' => '950001', 'nombre_comercial' => 'QA Emisor ficticio sin Hacienda',
                'actividad_economica' => '47190', 'url_firmador' => 'https://firmador.example.invalid', 'ambiente' => '00',
            ]);
        }
        $cliente = $this->cliente('DTE-SIMULADO');
        foreach (['pendiente', 'contingencia', 'rechazado'] as $estado) {
            $venta = $this->venta($cliente, 10, '01');
            $venta->update(['estado_dte' => $estado, 'mensaje_mh' => 'QA SIMULACIÓN LOCAL. No firmado ni transmitido; no es respuesta de Hacienda.']);
            if ($estado === 'contingencia') {
                $lote = LoteContingencia::create([
                    'codigo_lote' => (string) Str::uuid(), 'fecha_transmision' => today(), 'hora_transmision' => now()->toTimeString(),
                    'estado' => 'pendiente', 'motivo_contingencia' => 'QA Fixture de contingencia; no debe transmitirse.',
                ]);
                $venta->update(['lote_contingencia_id' => $lote->id]);
            } elseif ($estado === 'rechazado') {
                EventoInvalidacion::create([
                    'venta_id' => $venta->id, 'codigo_generacion' => $venta->codigo_generacion,
                    // El esquema exige este campo. Es un marcador explícitamente inválido, nunca un sello MH.
                    'sello_recepcion' => 'QA-SIN-SELLO-NO-TRANSMITIR', 'tipo_documento_anulado' => 1,
                    'motivo_invalidacion' => 2, 'estado' => 'rechazado', 'responsable_nit' => str_replace('-', '', $this->nit(501)),
                ]);
            }
        }
    }

    private function dui(int $numero): string
    {
        $base = sprintf('%08d', $numero);
        $suma = 0;
        foreach (str_split($base) as $i => $digito) {
            $suma += (int) $digito * (9 - $i);
        }

        return $base.'-'.((10 - $suma % 10) % 10);
    }

    private function nit(int $secuencia): string
    {
        $base = '0614010101'.sprintf('%03d', $secuencia);
        $suma = 0;
        foreach ([2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2] as $i => $peso) {
            $suma += (int) $base[$i] * $peso;
        }
        $residuo = $suma % 11;
        $control = $residuo > 1 ? 11 - $residuo : 0;

        return substr($base, 0, 4).'-'.substr($base, 4, 6).'-'.substr($base, 10, 3).'-'.$control;
    }
}

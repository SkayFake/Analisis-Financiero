<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\CondicionCredito;
use App\Models\Producto;
use App\Models\User;
use App\Models\Vendedor;
use App\Models\VendedorMeta;
use App\Models\Zona;
use App\Services\CreditoService;
use App\Services\FacturacionService;
use App\Services\VendedorService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class VendedoresPruebasSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los escenarios QA de vendedores no se cargan en producción.');
        }
        $dte = config('erp.dte.enabled');
        config(['erp.dte.enabled' => false]);
        try {
            DB::transaction(function () {
                if (DB::getDriverName() === 'pgsql') {
                    DB::select("SELECT pg_advisory_xact_lock(hashtext('VendedoresPruebasSeeder:v1'))");
                }
                if (Vendedor::where('codigo', 'QA-META')->exists()) {
                    return;
                }
                $admin = User::where('email', 'qa.admin@example.test')->first();
                if (! $admin) {
                    throw new RuntimeException('Ejecute primero PruebasIntegralesSeeder para cargar los datos base QA.');
                }
                $permisos = ['vendedores.view', 'vendedores.manage', 'vendedores.metas', 'vendedores.assign', 'vendedores.gestionar', 'vendedores.export', 'vendedores.audit'];
                foreach (['qa-admin', 'qa-gerente'] as $role) {
                    Role::findByName($role)->givePermissionTo($permisos);
                }
                Role::findByName('qa-cobros')->givePermissionTo(['vendedores.view', 'vendedores.gestionar']);
                Role::findByName('qa-lector')->givePermissionTo('vendedores.view');
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $service = app(VendedorService::class);
                foreach (Vendedor::where('codigo', 'like', 'QA-V%')->get() as $existente) {
                    if (! VendedorMeta::where('vendedor_id', $existente->id)->whereDate('periodo', today()->startOfMonth())->exists()) {
                        $service->meta($existente, now()->format('Y-m'), ['meta_ventas' => $existente->meta_mensual, 'meta_cobros' => 0], 'Meta inicial del mes para el conjunto de pruebas QA', $admin);
                    }
                }
                $vendedor = $service->guardar(null, ['codigo' => 'QA-META', 'nombre' => 'QA Metas y seguimiento', 'telefono' => '7000-4500', 'email' => 'metas@example.test', 'zona_id' => Zona::where('codigo', 'QA-Z1')->firstOrFail()->id, 'activo' => true], 'Alta del escenario de control comercial QA', $admin);
                $service->meta($vendedor, now()->format('Y-m'), ['meta_ventas' => 500, 'meta_cobros' => 50], 'Metas de prueba para verificar cumplimiento', $admin);
                $service->meta($vendedor, today()->subMonthNoOverflow()->format('Y-m'), ['meta_ventas' => 300, 'meta_cobros' => 30], 'Meta de ejemplo registrada ahora para probar consulta de otro período', $admin);
                $producto = Producto::where('codigo', 'QA-BASE')->firstOrFail();
                $bodega = $producto->existencias()->where('cantidad', '>', 5)->firstOrFail()->bodega_id;
                $cliente = Cliente::where('codigo', 'QA-CONTADO')->firstOrFail();
                $emitir = function ($credito = false, $caso = 'VENDEDOR-REASIGNADO') use ($vendedor, $producto, $bodega, $cliente, $admin) {
                    return app(FacturacionService::class)->procesarVenta([
                        'cliente_id' => $cliente->id, 'vendedor_id' => $vendedor->id, 'bodega_id' => $bodega, 'tipo_documento' => '11',
                        'condicion_operacion' => $credito ? '2' : '1',
                        'condiciones_credito' => $credito ? ['condicion_credito_id' => CondicionCredito::where('nombre', 'QA SIN-INTERES')->firstOrFail()->id, 'numero_cuotas' => 3, 'fecha_primera_cuota' => today()->addMonthNoOverflow()->toDateString(), 'observaciones' => 'QA-'.$caso.' | Escenario de control comercial.'] : [],
                    ], [['producto_id' => $producto->id, 'cantidad' => 1, 'precio_unitario' => $credito ? 600 : 1000]], $admin->id);
                };
                $emitir();
                $credito = $emitir(true)->credito;
                $service->registrarGestion($credito, ['medio' => 'telefono', 'resultado' => 'promesa_pago', 'notas' => 'QA Cliente promete un abono de cincuenta dólares.', 'fecha_compromiso' => today()->toDateString(), 'monto_compromiso' => 50], $admin);
                app(CreditoService::class)->procesarPago($credito, ['monto' => 50, 'forma_pago' => 'transferencia', 'recibido_por' => $admin->id]);
                $service->registrarGestion($credito, ['medio' => 'visita', 'resultado' => 'promesa_pago', 'notas' => 'QA Convenio de cien dólares para los próximos días.', 'fecha_compromiso' => today()->addDays(5)->toDateString(), 'monto_compromiso' => 100], $admin);
                $destino = Vendedor::where('codigo', 'QA-V2')->firstOrFail();
                $service->reasignarCredito($credito, $destino, null, 'QA Cambio de responsable: conserva vendedor original y recibo anterior.', $admin);
                $anterior = Carbon::withTestNow(today()->subDays(45), fn () => $emitir(true, 'VENDEDOR-PROMESA-VENCIDA')->credito);
                Carbon::withTestNow(today()->subDays(10), fn () => $service->registrarGestion($anterior, ['medio' => 'mensaje', 'resultado' => 'promesa_pago', 'notas' => 'QA Promesa vencida sin ningún pago posterior.', 'fecha_compromiso' => today()->addDays(5)->toDateString(), 'monto_compromiso' => 40], $admin));
                app(CreditoService::class)->calcularMora($anterior);
                $anterior->update(['estado' => 'vencido']);
                $anterior->cuotas()->where('fecha_vencimiento', '<', today())->update(['estado' => 'vencida']);
            });
        } finally {
            config(['erp.dte.enabled' => $dte]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}

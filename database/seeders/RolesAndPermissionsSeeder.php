<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Permisos por módulo ──────────────────────────────

        // Clientes
        $clientePermisos = [
            'clientes.view', 'clientes.create', 'clientes.edit', 'clientes.delete',
        ];

        // Créditos
        $creditoPermisos = [
            'creditos.view', 'creditos.create', 'creditos.approve', 'creditos.manage',
            'creditos.refinanciar', 'creditos.embargar',
        ];

        // Cobros
        $cobroPermisos = [
            'cobros.view', 'cobros.process', 'cobros.manage',
        ];

        // Incobrables
        $incobrablePermisos = [
            'incobrables.view', 'incobrables.classify', 'incobrables.reactivate',
        ];

        // Inventarios (futuro)
        $inventarioPermisos = [
            'inventario.view', 'inventario.create', 'inventario.edit', 'inventario.delete',
        ];

        // Facturación (futuro)
        $facturacionPermisos = [
            'facturacion.view', 'facturacion.emitir', 'facturacion.anular',
        ];

        // Activos (futuro)
        $activoPermisos = [
            'activos.view', 'activos.create', 'activos.edit', 'activos.baja',
        ];

        // Sistema
        $sistemaPermisos = [
            'usuarios.view', 'usuarios.create', 'usuarios.edit', 'usuarios.delete',
            'roles.manage', 'configuracion.manage', 'reportes.view', 'reportes.export',
        ];

        $vendedorPermisos = ['vendedores.view', 'vendedores.manage', 'vendedores.metas', 'vendedores.assign', 'vendedores.gestionar', 'vendedores.export', 'vendedores.audit'];

        $expedientePermisos = ['expedientes.view', 'expedientes.upload', 'expedientes.review', 'expedientes.manage'];

        // Crear todos los permisos
        $todosPermisos = array_merge(
            $clientePermisos, $creditoPermisos, $cobroPermisos,
            $incobrablePermisos, $inventarioPermisos, $facturacionPermisos,
            $activoPermisos, $sistemaPermisos, $vendedorPermisos, $expedientePermisos
        );

        $todosPermisos[] = 'facturacion.modificar_precio';

        foreach ($todosPermisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        // ─── Roles ────────────────────────────────────────────

        // Super Admin - acceso total
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // Administrador - gestión de módulos
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(array_merge(
            $clientePermisos, $creditoPermisos, $cobroPermisos,
            $incobrablePermisos, $sistemaPermisos, $vendedorPermisos, $expedientePermisos
        ));

        // Gerente de Cobros
        $cobrosManager = Role::firstOrCreate(['name' => 'cobros-manager']);
        $cobrosManager->givePermissionTo(array_merge(
            $clientePermisos, $creditoPermisos, $cobroPermisos,
            $incobrablePermisos, $vendedorPermisos, $expedientePermisos, ['reportes.view', 'reportes.export']
        ));

        // Agente de Cobros
        $cobrosAgent = Role::firstOrCreate(['name' => 'cobros-agent']);
        $cobrosAgent->givePermissionTo([
            'clientes.view', 'clientes.create', 'clientes.edit',
            'creditos.view', 'creditos.create',
            'cobros.view', 'cobros.process',
            'incobrables.view', 'vendedores.view', 'vendedores.gestionar', 'expedientes.view', 'expedientes.upload',
        ]);

        // Gerente de Inventarios
        $inventarioManager = Role::firstOrCreate(['name' => 'inventario-manager']);
        $inventarioManager->givePermissionTo(array_merge(
            $inventarioPermisos, ['reportes.view']
        ));

        // Operador de Facturación
        $facturacionOperator = Role::firstOrCreate(['name' => 'facturacion-operator']);
        $facturacionOperator->givePermissionTo(array_merge(
            $facturacionPermisos, ['clientes.view', 'inventario.view', 'creditos.view']
        ));

        // Gerente de Activos
        $activosManager = Role::firstOrCreate(['name' => 'activos-manager']);
        $activosManager->givePermissionTo(array_merge(
            $activoPermisos, ['reportes.view']
        ));

        // Visor (solo lectura)
        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $viewer->givePermissionTo([
            'clientes.view', 'creditos.view', 'cobros.view',
            'incobrables.view', 'inventario.view', 'facturacion.view',
            'activos.view', 'reportes.view', 'vendedores.view',
        ]);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::table(config('permission.table_names.permissions'))->insertOrIgnore([
            'name' => 'facturacion.modificar_precio',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retain a permission that may already have been assigned by an administrator.
    }
};

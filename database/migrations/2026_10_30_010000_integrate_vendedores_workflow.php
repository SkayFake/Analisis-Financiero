<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creditos', function (Blueprint $table) {
            $table->foreignId('vendedor_origen_id')->nullable()->constrained('vendedores')->restrictOnDelete();
            $table->boolean('vendedor_origen_reconstruido')->default(false);
        });
        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('vendedor_id')->nullable()->constrained('vendedores')->restrictOnDelete();
            $table->boolean('vendedor_reconstruido')->default(false);
            $table->timestamp('vendedor_revisado_at')->nullable();
        });
        DB::statement('UPDATE creditos SET vendedor_origen_id = COALESCE((SELECT vendedor_id FROM ventas WHERE ventas.id = creditos.venta_id), vendedor_id)');
        DB::statement('UPDATE creditos SET vendedor_origen_reconstruido = true WHERE vendedor_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM ventas WHERE ventas.id = creditos.venta_id AND ventas.vendedor_id IS NOT NULL)');
        DB::statement('UPDATE pagos SET vendedor_id = (SELECT vendedor_id FROM creditos WHERE creditos.id = pagos.credito_id), vendedor_reconstruido = true');

        Schema::create('vendedor_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('vendedores')->restrictOnDelete();
            $table->date('periodo');
            $table->decimal('meta_ventas', 14, 2)->default(0);
            $table->decimal('meta_cobros', 14, 2)->default(0);
            $table->unique(['vendedor_id', 'periodo']);
            $table->timestamps();
        });
        Schema::create('vendedor_auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendedor_id')->constrained('vendedores')->restrictOnDelete();
            $table->string('accion', 50);
            $table->string('entidad', 50);
            $table->unsignedBigInteger('entidad_id');
            $table->json('antes')->nullable();
            $table->json('despues')->nullable();
            $table->text('motivo');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('usuario_nombre');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['vendedor_id', 'created_at']);
        });
        Schema::create('gestiones_cobro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->restrictOnDelete();
            $table->foreignId('vendedor_id')->constrained('vendedores')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('usuario_nombre');
            $table->string('medio', 30);
            $table->string('resultado', 30);
            $table->text('notas');
            $table->date('fecha_compromiso')->nullable();
            $table->decimal('monto_compromiso', 14, 2)->nullable();
            $table->unsignedBigInteger('pago_base_id')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['vendedor_id', 'created_at']);
        });
        DB::table('vendedores')->orderBy('id')->chunkById(200, function ($vendedores) {
            foreach ($vendedores as $vendedor) {
                $id = DB::table('vendedor_metas')->insertGetId([
                    'vendedor_id' => $vendedor->id, 'periodo' => now()->startOfMonth()->toDateString(),
                    'meta_ventas' => $vendedor->meta_mensual, 'meta_cobros' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('vendedor_auditorias')->insert([
                    'vendedor_id' => $vendedor->id, 'accion' => 'importacion_meta', 'entidad' => 'vendedor_metas', 'entidad_id' => $id,
                    'despues' => json_encode(['periodo' => now()->format('Y-m'), 'meta_ventas' => $vendedor->meta_mensual, 'meta_cobros' => 0]),
                    'motivo' => 'Importación de la meta de ficha para el mes actual. No existe evidencia de metas de meses anteriores.',
                    'usuario_nombre' => 'Migración del sistema', 'created_at' => now(),
                ]);
            }
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION erp_bitacora_vendedor_inmutable() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'La bitácora de vendedores solo permite insertar registros'; END; $$ LANGUAGE plpgsql");
            foreach (['vendedor_auditorias', 'gestiones_cobro'] as $tabla) {
                DB::unprepared("CREATE TRIGGER impedir_cambios BEFORE UPDATE OR DELETE ON {$tabla} FOR EACH ROW EXECUTE FUNCTION erp_bitacora_vendedor_inmutable()");
            }
        }
        $permisos = ['vendedores.view', 'vendedores.manage', 'vendedores.metas', 'vendedores.assign', 'vendedores.gestionar', 'vendedores.export', 'vendedores.audit'];
        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }
        foreach (['super-admin', 'admin', 'cobros-manager', 'qa-admin', 'qa-gerente'] as $nombre) {
            Role::where('name', $nombre)->where('guard_name', 'web')->first()?->givePermissionTo($permisos);
        }
        foreach (['cobros-agent', 'qa-cobros'] as $nombre) {
            Role::where('name', $nombre)->where('guard_name', 'web')->first()?->givePermissionTo(['vendedores.view', 'vendedores.gestionar']);
        }
        foreach (['viewer', 'qa-lector'] as $nombre) {
            Role::where('name', $nombre)->where('guard_name', 'web')->first()?->givePermissionTo('vendedores.view');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (DB::table('vendedor_auditorias')->exists() || DB::table('gestiones_cobro')->exists()) {
            throw new RuntimeException('Conserve la bitácora de vendedores antes de revertir esta migración.');
        }
        Schema::dropIfExists('gestiones_cobro');
        Schema::dropIfExists('vendedor_auditorias');
        Schema::dropIfExists('vendedor_metas');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS erp_bitacora_vendedor_inmutable()');
        }
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendedor_id');
            $table->dropColumn(['vendedor_reconstruido', 'vendedor_revisado_at']);
        });
        Schema::table('creditos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendedor_origen_id');
            $table->dropColumn('vendedor_origen_reconstruido');
        });
    }
};

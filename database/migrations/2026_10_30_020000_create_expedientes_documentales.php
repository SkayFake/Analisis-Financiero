<?php

use App\Models\Cliente;
use App\Services\ExpedienteService;
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
        Schema::table('creditos', function (Blueprint $t) {
            // Los créditos ya otorgados conservan su aprobación y su flujo de pagos.
            $t->boolean('expediente_requerido')->default(false);
            $t->json('requisitos_expediente')->nullable();
        });
        Schema::table('fiadores', function (Blueprint $t) {
            $t->string('nit', 20)->nullable();
            $t->string('lugar_trabajo')->nullable();
            $t->decimal('ingresos', 14, 2)->default(0);
            $t->decimal('egresos', 14, 2)->default(0);
        });
        Schema::create('expediente_documentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $t->foreignId('credito_id')->nullable()->constrained('creditos')->restrictOnDelete();
            $t->foreignId('fiador_id')->nullable()->constrained('fiadores')->restrictOnDelete();
            $t->string('categoria', 50);
            $t->uuid('grupo');
            $t->unsignedInteger('version');
            $t->foreignId('reemplaza_id')->nullable()->constrained('expediente_documentos')->restrictOnDelete();
            $t->boolean('vigente')->default(true);
            $t->string('nombre_original', 180);
            $t->string('ruta')->unique();
            $t->string('mime', 60);
            $t->unsignedBigInteger('bytes');
            $t->string('sha256', 64);
            $t->string('titular');
            $t->string('identidad_huella', 64);
            $t->date('fecha_emision')->nullable();
            $t->date('fecha_vencimiento')->nullable();
            $t->text('notas')->nullable();
            $t->string('estado', 20)->default('pendiente');
            $t->foreignId('subido_por')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('revisado_por')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('revisado_at')->nullable();
            $t->string('identidad_revisada', 64)->nullable();
            $t->text('motivo_revision')->nullable();
            $t->timestamps();
            $t->unique(['grupo', 'version']);
            $t->index(['cliente_id', 'credito_id', 'fiador_id']);
        });
        Schema::create('expediente_eventos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $t->foreignId('credito_id')->nullable()->constrained('creditos')->restrictOnDelete();
            $t->foreignId('fiador_id')->nullable()->constrained('fiadores')->restrictOnDelete();
            $t->foreignId('documento_id')->nullable()->constrained('expediente_documentos')->restrictOnDelete();
            $t->string('accion', 40);
            $t->json('antes')->nullable();
            $t->json('despues')->nullable();
            $t->text('motivo');
            $t->foreignId('usuario_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('usuario_nombre');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['cliente_id', 'created_at']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE OR REPLACE FUNCTION erp_expediente_inmutable() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'El historial documental solo permite insertar registros'; END; $$ LANGUAGE plpgsql");
            DB::unprepared('CREATE TRIGGER impedir_cambios BEFORE UPDATE OR DELETE ON expediente_eventos FOR EACH ROW EXECUTE FUNCTION erp_expediente_inmutable()');
        }
        $this->actualizarSolicitudesPendientes();
        $permisos = ['expedientes.view', 'expedientes.upload', 'expedientes.review', 'expedientes.manage'];
        foreach ($permisos as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        foreach (['super-admin', 'admin', 'cobros-manager', 'qa-admin', 'qa-gerente'] as $r) {
            Role::where('name', $r)->where('guard_name', 'web')->first()?->givePermissionTo($permisos);
        }
        foreach (['cobros-agent', 'qa-cobros'] as $r) {
            Role::where('name', $r)->where('guard_name', 'web')->first()?->givePermissionTo(['expedientes.view', 'expedientes.upload']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Aplicar el control antes de aprobar solicitudes que todavía estaban pendientes. */
    public function actualizarSolicitudesPendientes(): void
    {
        DB::table('creditos')->where('estado', 'pendiente_aprobacion')->where('expediente_requerido', false)->orderBy('id')->chunkById(100, function ($creditos) {
            foreach ($creditos as $fila) {
                DB::transaction(function () use ($fila) {
                    $cliente = Cliente::withTrashed()->whereKey($fila->cliente_id)->lockForUpdate()->firstOrFail();
                    $credito = DB::table('creditos')->where('id', $fila->id)->lockForUpdate()->first();
                    if ($credito->estado !== 'pendiente_aprobacion' || $credito->expediente_requerido) {
                        return;
                    }
                    $politica = app(ExpedienteService::class)->politica($cliente);
                    DB::table('creditos')->where('id', $fila->id)->update(['expediente_requerido' => true, 'requisitos_expediente' => json_encode($politica), 'dui_verificado' => false]);
                    DB::table('expediente_eventos')->insert([
                        'cliente_id' => $cliente->id, 'credito_id' => $fila->id, 'accion' => 'control_activado',
                        'despues' => json_encode(['expediente_requerido' => true, 'requisitos_expediente' => $politica]),
                        'motivo' => 'Control documental incorporado a una solicitud todavía pendiente. No constituye revisión ni aprobación de comprobantes.',
                        'usuario_nombre' => 'Migración del sistema', 'created_at' => now(),
                    ]);
                });
            }
        });
    }

    public function down(): void
    {
        if (DB::table('expediente_eventos')->exists() || DB::table('expediente_documentos')->exists()) {
            throw new RuntimeException('Respalde y conserve el expediente documental antes de revertir.');
        }
        Schema::dropIfExists('expediente_eventos');
        Schema::dropIfExists('expediente_documentos');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS erp_expediente_inmutable()');
        }
        Schema::table('fiadores', fn (Blueprint $t) => $t->dropColumn(['nit', 'lugar_trabajo', 'ingresos', 'egresos']));
        Schema::table('creditos', fn (Blueprint $t) => $t->dropColumn(['expediente_requerido', 'requisitos_expediente']));
    }
};

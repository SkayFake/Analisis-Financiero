<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historial_clasificaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('credito_id')->nullable()->change();
            $table->foreignId('cliente_id')->nullable()->index()->constrained('clientes')->nullOnDelete();
            $table->text('motivo')->nullable();
            $table->boolean('reactivacion')->default(false);
            $table->foreignId('realizado_por')->nullable()->constrained('users')->nullOnDelete();
        });

        // Preserve credit-based records and expose them through the client history as well.
        DB::statement('UPDATE historial_clasificaciones SET cliente_id = (SELECT cliente_id FROM creditos WHERE creditos.id = historial_clasificaciones.credito_id), motivo = observaciones, realizado_por = registrado_por');
    }

    public function down(): void
    {
        if (DB::table('historial_clasificaciones')->whereNull('credito_id')->exists()) {
            throw new RuntimeException('No se puede revertir el historial sin perder clasificaciones de clientes. Conserve estos registros antes de revertir.');
        }

        Schema::table('historial_clasificaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
            $table->dropConstrainedForeignId('realizado_por');
            $table->dropColumn(['motivo', 'reactivacion']);
            $table->unsignedBigInteger('credito_id')->nullable(false)->change();
        });
    }
};

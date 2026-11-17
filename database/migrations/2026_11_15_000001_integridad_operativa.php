<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correlativos', function (Blueprint $t) {
            $t->string('clave')->primary();
            $t->unsignedBigInteger('valor')->default(0);
        });
        Schema::create('capas_inventario', function (Blueprint $t) {
            $t->id();
            $t->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $t->foreignId('bodega_id')->constrained('bodegas')->restrictOnDelete();
            $t->string('lote', 50)->nullable();
            $t->date('fecha_vencimiento')->nullable();
            $t->date('fecha_ingreso');
            $t->decimal('cantidad', 12, 4);
            $t->decimal('costo_unitario', 14, 4);
            $t->index(['producto_id', 'bodega_id', 'fecha_ingreso']);
        });
        Schema::table('activos_fijos', function (Blueprint $t) {
            $t->string('clase_fiscal', 30)->nullable();
            $t->decimal('porcentaje_fiscal', 5, 2)->nullable();
            $t->date('fecha_inicio_uso')->nullable();
            $t->decimal('valor_bien_nuevo', 14, 4)->nullable();
            $t->decimal('valor_aduana', 14, 4)->nullable();
            $t->boolean('base_fiscal_verificada')->default(false);
        });
        Schema::table('creditos', function (Blueprint $t) {
            $t->string('calculo_interes', 20)->default('historico');
            $t->json('limite_legal')->nullable();
            $t->date('fecha_ultimo_devengo')->nullable();
        });
        Schema::table('ventas', function (Blueprint $t) {
            $t->string('tipo_documento_original', 2)->nullable();
        });
        DB::table('ventas')->where('tipo_documento', '11')->where('estado_dte', 'local')->update(['tipo_documento_original' => '11', 'tipo_documento' => 'TL']);
    }

    public function down(): void
    {
        throw new RuntimeException('Migración con evidencia financiera: restaure un respaldo validado; no se elimina historial automáticamente.');
    }
};

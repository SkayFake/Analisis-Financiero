<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina la tabla productos_credito (concepto de crédito financiero/bancario).
 * Se reemplaza por condiciones_credito (crédito comercial configurable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('productos_credito');
    }

    public function down(): void
    {
        // Recrear si se hace rollback
        Schema::create('productos_credito', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->enum('tipo', ['personal', 'comercial', 'linea_credito']);
            $table->decimal('tasa_interes', 8, 4);
            $table->decimal('comision', 8, 4)->default(0);
            $table->decimal('monto_minimo', 14, 2)->default(100);
            $table->decimal('monto_maximo', 14, 2)->default(100000);
            $table->integer('plazo_min_dias')->default(30);
            $table->integer('plazo_max_dias')->default(1825);
            $table->integer('dias_mora_incobrable')->default(180);
            $table->decimal('interes_moratorio', 8, 4)->default(12.0000);
            $table->boolean('requiere_fiador')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }
};

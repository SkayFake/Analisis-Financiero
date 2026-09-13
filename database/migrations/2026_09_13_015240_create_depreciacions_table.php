<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depreciaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_fijo_id')->constrained('activos_fijos')->cascadeOnDelete();
            
            $table->date('fecha_calculo');
            $table->integer('anio');
            $table->integer('mes');
            
            $table->integer('dias_depreciados')->comment('Útil para el cálculo proporcional del primer año LISR');
            $table->decimal('monto_depreciado', 14, 4);
            $table->decimal('depreciacion_acumulada_historica', 14, 4);
            $table->decimal('valor_en_libros', 14, 4);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciaciones');
    }
};

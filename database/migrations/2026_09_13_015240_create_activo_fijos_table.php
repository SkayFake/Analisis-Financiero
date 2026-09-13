<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activos_fijos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('unidades');
            $table->foreignId('categoria_id')->constrained('categoria_activos');
            
            $table->string('correlativo', 4)->comment('0001, 0002, etc.');
            $table->string('codigo_inventario', 20)->unique()->comment('Ej: 2322-5676-8871-0001');
            
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            
            $table->date('fecha_adquisicion');
            $table->decimal('valor_adquisicion', 14, 4);
            $table->decimal('valor_residual', 14, 4)->default(0);
            
            // Reglas de la LISR
            $table->boolean('es_usado')->default(false);
            $table->integer('anios_uso_previo')->default(0);
            $table->boolean('maquinaria_importada_exenta')->default(false);
            
            // El valor sobre el cual realmente se calculará la depreciación luego del ajuste LISR
            $table->decimal('valor_sujeto_depreciacion', 14, 4);
            $table->decimal('depreciacion_acumulada', 14, 4)->default(0);
            
            $table->enum('estado', ['activo', 'depreciado', 'donado', 'vendido', 'botado'])->default('activo');
            $table->date('fecha_baja')->nullable();
            $table->text('motivo_baja')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activos_fijos');
    }
};

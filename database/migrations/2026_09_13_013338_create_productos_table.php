<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            
            // Relaciones Catálogos
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->nullOnDelete();
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();

            // Configuración Contable/Costeo
            $table->enum('metodo_costeo', ['ULTIMA_COMPRA', 'PROMEDIO_ALIGACION', 'PROMEDIO', 'PEPS'])->default('PROMEDIO');
            $table->decimal('costo_unitario', 12, 4)->default(0);
            $table->decimal('precio_venta', 12, 2)->default(0);
            
            // Controles de Stock
            $table->decimal('stock_minimo', 10, 2)->default(0);
            $table->decimal('stock_maximo', 10, 2)->nullable();
            $table->integer('tiempo_espera_dias')->default(0)->comment('Lead Time');
            $table->boolean('perecedero')->default(false);

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};

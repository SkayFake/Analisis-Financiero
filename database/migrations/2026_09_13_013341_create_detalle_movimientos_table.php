<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movimiento_id')->constrained('movimientos_inventario')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            
            $table->string('lote', 50)->nullable();
            $table->date('fecha_vencimiento')->nullable();
            
            $table->decimal('cantidad', 12, 4);
            $table->decimal('costo_unitario', 12, 4)->default(0);
            $table->decimal('costo_total', 14, 4)->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_movimientos');
    }
};

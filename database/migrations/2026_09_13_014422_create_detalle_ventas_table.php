<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos');
            
            $table->integer('num_item');
            $table->decimal('cantidad', 10, 4);
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('monto_descuento', 14, 4)->default(0);
            $table->decimal('venta_nosujeta', 14, 4)->default(0);
            $table->decimal('venta_exenta', 14, 4)->default(0);
            $table->decimal('venta_gravada', 14, 4)->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_ventas');
    }
};

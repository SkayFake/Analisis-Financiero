<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('existencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('bodega_id')->constrained('bodegas')->cascadeOnDelete();
            
            // Para productos perecederos/con lote
            $table->string('lote', 50)->nullable();
            $table->date('fecha_vencimiento')->nullable();
            
            $table->decimal('cantidad', 12, 4)->default(0);
            $table->timestamps();

            // Evitar duplicados de lote por bodega/producto si se controla por lote
            $table->unique(['producto_id', 'bodega_id', 'lote']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('existencias');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['entrada', 'salida', 'transferencia', 'ajuste']);
            $table->string('numero_documento', 50)->unique();
            $table->date('fecha');
            
            $table->foreignId('bodega_origen_id')->nullable()->constrained('bodegas');
            $table->foreignId('bodega_destino_id')->nullable()->constrained('bodegas');
            
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores');
            $table->foreignId('usuario_id')->constrained('users');
            
            $table->string('referencia')->nullable();
            $table->text('observaciones')->nullable();
            
            $table->decimal('costo_total', 14, 4)->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};

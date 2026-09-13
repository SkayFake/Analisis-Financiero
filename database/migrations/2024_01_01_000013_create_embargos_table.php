<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embargos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->restrictOnDelete();
            $table->date('fecha_embargo');
            $table->text('descripcion_bienes');
            $table->decimal('valor_estimado', 14, 2);
            $table->enum('estado', ['activo', 'resuelto', 'vendido'])->default('activo');
            $table->date('fecha_resolucion')->nullable();
            $table->decimal('monto_recuperado', 14, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('credito_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embargos');
    }
};

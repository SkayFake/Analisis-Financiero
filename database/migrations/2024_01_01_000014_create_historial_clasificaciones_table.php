<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_clasificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('credito_id')->nullable()->constrained('creditos')->nullOnDelete();
            $table->enum('clasificacion_anterior', ['A', 'B', 'C', 'D']);
            $table->enum('clasificacion_nueva', ['A', 'B', 'C', 'D']);
            $table->string('motivo');
            $table->integer('dias_mora')->default(0);
            $table->boolean('reactivacion')->default(false);
            $table->foreignId('realizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('cliente_id');
            $table->index('credito_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_clasificaciones');
    }
};

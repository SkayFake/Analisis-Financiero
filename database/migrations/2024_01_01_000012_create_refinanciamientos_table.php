<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refinanciamientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_original_id')->constrained('creditos')->restrictOnDelete();
            $table->foreignId('credito_nuevo_id')->constrained('creditos')->restrictOnDelete();
            $table->decimal('saldo_anterior', 14, 2);
            $table->decimal('nuevo_monto', 14, 2);
            $table->decimal('intereses_pendientes', 14, 2)->default(0);
            $table->decimal('comisiones_pendientes', 14, 2)->default(0);
            $table->string('motivo');
            $table->date('fecha');
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('credito_original_id');
            $table->index('credito_nuevo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refinanciamientos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes_contingencia', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_lote', 36)->unique();
            $table->date('fecha_transmision');
            $table->time('hora_transmision');
            $table->enum('estado', ['pendiente', 'enviado', 'rechazado'])->default('pendiente');
            $table->string('sello_recepcion', 40)->nullable();
            $table->text('motivo_contingencia')->nullable();
            $table->timestamps();
        });
        
        // Agregar foránea en ventas para vincular la contingencia
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('lote_contingencia_id')->nullable()->constrained('lotes_contingencia')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['lote_contingencia_id']);
            $table->dropColumn('lote_contingencia_id');
        });
        Schema::dropIfExists('lotes_contingencia');
    }
};

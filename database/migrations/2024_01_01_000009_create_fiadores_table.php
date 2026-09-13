<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('credito_id')->constrained('creditos')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('dui', 12)->nullable();
            $table->string('nit', 20)->nullable();
            $table->text('direccion')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('lugar_trabajo')->nullable();
            $table->decimal('ingresos', 14, 2)->default(0);
            $table->decimal('egresos', 14, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('cliente_id');
            $table->index('credito_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiadores');
    }
};

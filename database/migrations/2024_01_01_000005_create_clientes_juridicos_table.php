<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes_juridicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('nombre_comercial')->nullable();
            $table->string('giro')->nullable();
            $table->string('representante_legal')->nullable();
            $table->string('dui_representante', 12)->nullable();
            $table->jsonb('balance_general')->nullable();
            $table->jsonb('estado_resultados')->nullable();
            $table->jsonb('ratios_financieros')->nullable();
            $table->date('fecha_balance')->nullable();
            $table->integer('numero_empleados')->nullable();
            $table->date('fecha_constitucion')->nullable();
            $table->timestamps();

            $table->unique('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes_juridicos');
    }
};

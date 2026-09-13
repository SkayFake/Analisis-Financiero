<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_credito', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->enum('tipo', ['personal', 'comercial', 'linea_credito']);
            $table->decimal('tasa_interes', 8, 4); // Anual
            $table->decimal('comision', 8, 4)->default(0);
            $table->decimal('monto_minimo', 14, 2)->default(100);
            $table->decimal('monto_maximo', 14, 2)->default(100000);
            $table->integer('plazo_min_dias')->default(30);
            $table->integer('plazo_max_dias')->default(1825); // 5 años
            $table->integer('dias_mora_incobrable')->default(180);
            $table->decimal('interes_moratorio', 8, 4)->default(12.0000);
            $table->boolean('requiere_fiador')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos_credito');
    }
};
